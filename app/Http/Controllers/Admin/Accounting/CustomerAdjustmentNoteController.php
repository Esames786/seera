<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerInvoice;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use App\Support\Workspace\CustomerAdjustmentNoteWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Customer credit / debit notes under Accounts Receivable: register, draft
 * form (always against one posted invoice the user may see), read-only
 * workspace, explicit Post action and a print view. Permissions are the
 * Accounts Receivable actions (view / create / edit / approve); ZATCA actions
 * run through the ZATCA record routes.
 */
class CustomerAdjustmentNoteController extends Controller
{
    public function __construct(private readonly CustomerNoteService $notes, private readonly PostingService $posting) {}

    public function index(Request $request): View
    {
        $notes = CustomerAdjustmentNote::with(['customer', 'invoice', 'project', 'zatcaRecord'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('note_number', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('note_type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->integer('customer')))
            ->orderByDesc('note_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();
        $posted = CustomerAdjustmentNote::where('status', CustomerAdjustmentNote::STATUS_POSTED);

        return view('admin.accounting.customer-notes.index', [
            'notes' => $notes,
            'creditedTotal' => round((float) (clone $posted)->where('note_type', CustomerAdjustmentNote::TYPE_CREDIT)->sum('total_amount'), 2),
            'debitedTotal' => round((float) (clone $posted)->where('note_type', CustomerAdjustmentNote::TYPE_DEBIT)->sum('total_amount'), 2),
            'unappliedCredit' => round((float) (clone $posted)->sum('unapplied_amount'), 2),
            'draftCount' => CustomerAdjustmentNote::where('status', CustomerAdjustmentNote::STATUS_DRAFT)->count(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'types' => CustomerAdjustmentNote::TYPES,
            'statuses' => CustomerAdjustmentNote::STATUSES,
        ]);
    }

    /** The form always starts from one posted invoice (?invoice=ID&type=credit|debit). */
    public function create(Request $request): View
    {
        $request->validate(['invoice' => ['required', 'integer'], 'type' => ['required', Rule::in(CustomerAdjustmentNote::TYPES)]]);
        $invoice = CustomerInvoice::with(['customer', 'lines.revenueAccount', 'project'])->findOrFail($request->integer('invoice'));
        if (! $invoice->isCorrectable()) {
            throw ValidationException::withMessages(['invoice' => __('customer_notes.err_invoice_not_posted')]);
        }

        return view('admin.accounting.customer-notes.form', $this->formData($invoice, $request->string('type')->toString(), null, SaveAction::returnTo($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $invoice = CustomerInvoice::findOrFail((int) $data['customer_invoice_id']);
        $this->assertRelation($request, $invoice);
        $note = $this->notes->createDraft($invoice, $data['note_type'], $data, $data['lines'], $request->user());
        ActivityLog::record($request, 'Accounting', 'Created customer '.$note->note_type.' note', $note->note_number.' against '.$invoice->invoice_number);

        return redirect()->route('admin.accounting.accounts-receivable.notes.show', ['note' => $note, 'return_to' => SaveAction::returnTo($request)])
            ->with('status', __('customer_notes.flash_saved', ['number' => $note->note_number]));
    }

    public function show(Request $request, CustomerAdjustmentNote $note): View
    {
        $note->load(['customer', 'invoice.customer', 'project', 'costCenter', 'lines.invoiceLine', 'lines.revenueAccount', 'lines.costCenter',
            'journalEntry.lines.account', 'journalEntry.lines.costCenter', 'zatcaRecord', 'postedBy']);

        return view('admin.accounting.customer-notes.show', CustomerAdjustmentNoteWorkspace::data($note, $request->user(), SaveAction::returnTo($request)));
    }

    public function edit(Request $request, CustomerAdjustmentNote $note): View
    {
        if (! $note->isEditable() || ! $request->user()->hasPermission('Accounts Receivable', 'edit')) {
            abort(403, __('customer_notes.err_not_editable'));
        }
        $note->load(['lines', 'invoice.lines.revenueAccount', 'invoice.customer']);

        return view('admin.accounting.customer-notes.form', $this->formData($note->invoice, $note->note_type, $note, SaveAction::returnTo($request)));
    }

    public function update(Request $request, CustomerAdjustmentNote $note): RedirectResponse
    {
        $data = $this->validated($request);
        if ((int) $data['customer_invoice_id'] !== $note->customer_invoice_id || $data['note_type'] !== $note->note_type) {
            throw ValidationException::withMessages(['customer_invoice_id' => __('customer_notes.err_relation_fixed')]);
        }
        $this->assertRelation($request, $note->invoice);
        $note = $this->notes->updateDraft($note, $data, $data['lines'], $request->user());
        ActivityLog::record($request, 'Accounting', 'Updated customer '.$note->note_type.' note', $note->note_number);

        return redirect()->route('admin.accounting.accounts-receivable.notes.show', ['note' => $note, 'return_to' => SaveAction::returnTo($request)])
            ->with('status', __('customer_notes.flash_saved', ['number' => $note->note_number]));
    }

    /** Post: journal, VAT correction, balance application, snapshots and the ZATCA record. Idempotent. */
    public function approve(Request $request, CustomerAdjustmentNote $note): RedirectResponse
    {
        $wasDraft = $note->isDraft();
        $note = $this->notes->post($note, $request->user());
        $message = $wasDraft
            ? __('customer_notes.flash_posted', ['number' => $note->note_number, 'journal' => $note->journalEntry ? $this->posting->describe($note->journalEntry) : '-'])
            : __('customer_notes.flash_already_posted', ['number' => $note->note_number]);

        return redirect()->route('admin.accounting.accounts-receivable.notes.show', ['note' => $note, 'return_to' => SaveAction::returnTo($request)])->with('status', $message);
    }

    /** Print-friendly bilingual note (HTML; the browser prints it). */
    public function print(CustomerAdjustmentNote $note): View
    {
        $note->load(['customer', 'invoice', 'lines', 'zatcaRecord']);

        return view('admin.accounting.customer-notes.print', ['note' => $note, 'company' => \App\Models\CompanyProfile::first()]);
    }

    /** @return array<string, mixed> */
    private function formData(CustomerInvoice $invoice, string $type, ?CustomerAdjustmentNote $note, ?string $returnTo): array
    {
        $remaining = $this->notes->remainingCredit($invoice, $note?->id);

        return [
            'invoice' => $invoice,
            'type' => $type,
            'note' => $note,
            'returnTo' => $returnTo,
            'remaining' => $remaining,
            'reasonCodes' => CustomerAdjustmentNote::REASON_CODES,
            'revenueAccounts' => ChartOfAccount::where('account_type', 'revenue')->where('status', 'active')->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'costCenters' => CostCenter::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_invoice_id' => ['required', 'integer'],
            'note_type' => ['required', Rule::in(CustomerAdjustmentNote::TYPES)],
            'customer_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'note_date' => ['required', 'date', 'before_or_equal:today'],
            'reason_code' => ['required', Rule::in(CustomerAdjustmentNote::REASON_CODES)],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_line_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.revenue_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
        ], [
            'lines.required' => __('customer_notes.err_no_lines'),
            'reason.required' => __('customer_notes.err_reason_required'),
        ]);
    }

    /** The customer and project are the original invoice's; a client claiming otherwise is refused. */
    private function assertRelation(Request $request, CustomerInvoice $invoice): void
    {
        if ($request->filled('customer_id') && $request->integer('customer_id') !== (int) $invoice->customer_id) {
            throw ValidationException::withMessages(['customer_id' => __('customer_notes.err_customer_mismatch')]);
        }
        if ($request->filled('project_id') && $request->integer('project_id') !== (int) $invoice->project_id) {
            throw ValidationException::withMessages(['project_id' => __('customer_notes.err_project_mismatch')]);
        }
    }
}
