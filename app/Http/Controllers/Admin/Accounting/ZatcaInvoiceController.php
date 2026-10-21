<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Contracts\ZatcaDocument;
use App\Http\Controllers\Controller;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Zatca\ZatcaEnvironment;
use App\Services\Zatca\ZatcaException;
use App\Services\Zatca\ZatcaSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ZATCA compliance records: register, record page with readiness and
 * attempts, and the explicit actions (generate / validate, stamp + submit,
 * retry, regenerate, private XML downloads). Every action answers with the
 * record's real state; nothing here invents a ZATCA result.
 */
class ZatcaInvoiceController extends Controller
{
    public function __construct(private readonly ZatcaSubmissionService $service, private readonly ZatcaEnvironment $environment) {}

    public function index(Request $request): View
    {
        $records = ZatcaInvoiceRecord::with(['customerInvoice.customer', 'adjustmentNote', 'unit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('customerInvoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('adjustmentNote', fn ($n) => $n->where('note_number', 'like', "%{$search}%")));
            })
            ->when($request->filled('kind'), fn ($q) => $q->where('document_type', $request->string('kind')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
        $counts = ZatcaInvoiceRecord::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.accounting.zatca.index', [
            'records' => $records,
            'counts' => $counts,
            'statuses' => ZatcaInvoiceRecord::STATUSES,
            'environment' => $this->environment->current(),
            'unit' => $this->service->unit(),
            'canConfigure' => $request->user()->hasPermission('ZATCA Invoicing', 'edit'),
        ]);
    }

    public function show(Request $request, ZatcaInvoiceRecord $zatca): View
    {
        $zatca->load(['customerInvoice.customer', 'customerInvoice.lines', 'adjustmentNote.customer', 'adjustmentNote.lines', 'adjustmentNote.invoice', 'unit', 'submissions']);
        $invoice = $zatca->document();
        $readiness = $invoice ? $this->service->readiness($invoice) : ['errors' => [], 'warnings' => [], 'unit' => null];
        $user = $request->user();

        return view('admin.accounting.zatca.show', [
            'record' => $zatca,
            'invoice' => $invoice,
            'readiness' => $readiness,
            'environment' => $this->environment->current(),
            'canPost' => $user->hasPermission('ZATCA Invoicing', 'post'),
            'canRetry' => $user->hasPermission('ZATCA Invoicing', 'retry'),
            'canExport' => $user->hasPermission('ZATCA Invoicing', 'export'),
        ]);
    }

    /** Build and validate locally; stores the unsigned XML. Consumes nothing. */
    public function generate(Request $request, ZatcaInvoiceRecord $zatca): RedirectResponse
    {
        return $this->act($zatca, fn (ZatcaDocument $invoice) => $this->service->generate($invoice, $request->user()), 'generated');
    }

    /** Stamp (if needed) and submit: clearance for standard, reporting for simplified. */
    public function submit(Request $request, ZatcaInvoiceRecord $zatca): RedirectResponse
    {
        return $this->act($zatca, fn (ZatcaDocument $invoice) => $this->service->process($invoice, $request->user()), 'submitted');
    }

    /** Resubmit the identical signed document (rejected after correction of nothing, outcome unknown, transient failure). */
    public function retry(Request $request, ZatcaInvoiceRecord $zatca): RedirectResponse
    {
        return $this->act($zatca, fn (ZatcaDocument $invoice) => $this->service->submit($invoice, $request->user()), 'submitted');
    }

    /** After a rejection or local failure: refresh snapshots and start over with the same UUID. */
    public function regenerate(Request $request, ZatcaInvoiceRecord $zatca): RedirectResponse
    {
        return $this->act($zatca, fn (ZatcaDocument $invoice) => $this->service->regenerate($invoice, $request->user()), 'reset');
    }

    /** Private XML artifacts (unsigned, signed, cleared) for authorised users only. */
    public function xml(ZatcaInvoiceRecord $zatca, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['unsigned', 'signed', 'cleared'], true), 404);
        $path = $this->service->artifactPath($zatca, $kind);
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $invoice = $zatca->document();
        $name = $invoice ? $this->service->fileName($invoice, $zatca, $kind) : $zatca->uuid.'-'.$kind.'.xml';

        return Storage::disk('local')->download($path, $name, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function act(ZatcaInvoiceRecord $record, callable $action, string $done): RedirectResponse
    {
        $invoice = $record->document();
        if (! $invoice) {
            return back()->withErrors(['zatca' => __('zatca.no_record')]);
        }
        try {
            $result = $action($invoice);
        } catch (ZatcaException $exception) {
            return redirect()->route('admin.accounting.zatca.show', $record)->withErrors(['zatca' => $exception->getMessage()])
                ->with('zatca_details', $exception->details);
        }
        $result = $result->fresh();
        $message = __('zatca.status_'.$result->status).($result->last_error_message ? ' — '.$result->last_error_message : '');

        return redirect()->route('admin.accounting.zatca.show', $record)->with($result->isAccepted() || in_array($result->status, ['validated', 'signed', 'not_generated'], true) ? 'status' : 'warning', $message);
    }
}
