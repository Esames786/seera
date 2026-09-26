<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CustomerInvoice;
use App\Models\SupplierBill;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VatController extends Controller implements HasMiddleware
{
    /** VAT returns are company-level figures: no project, site or warehouse scoped view exists (F07). */
    public static function middleware(): array
    {
        return [
            function (Request $request, \Closure $next) {
                abort_unless($request->user()?->effectiveAccessScope() === 'company', 403, 'VAT returns are company-level figures and are not available to a project, site or warehouse scoped account.');

                return $next($request);
            },
        ];
    }

    public function index(Request $request): View
    {
        $periods = VatPeriod::withCount('transactions')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        $outputVat = (float) VatTransaction::where('vat_type', 'output')->sum('vat_amount');
        $inputVat = (float) VatTransaction::where('vat_type', 'input')->sum('vat_amount');

        // Draft documents are not in the return yet (client change request NR-30); show
        // them separately so the VAT screen and the invoice list never look out of step.
        $draftOutputVat = (float) CustomerInvoice::where('payment_status', 'draft')->sum('vat_amount');
        $draftInputVat = (float) SupplierBill::where('status', 'draft')->sum('vat_amount');

        return view('admin.accounting.vat.index', [
            'periods' => $periods,
            'outputVat' => round($outputVat, 2),
            'inputVat' => round($inputVat, 2),
            'vatPayable' => round($outputVat - $inputVat, 2),
            'draftOutputVat' => round($draftOutputVat, 2),
            'draftInputVat' => round($draftInputVat, 2),
            'draftInvoices' => CustomerInvoice::where('payment_status', 'draft')->count(),
            'draftBills' => SupplierBill::where('status', 'draft')->count(),
            'exceptions' => VatTransaction::whereNull('vat_period_id')->count(),
            'recentTransactions' => VatTransaction::latest('transaction_date')->latest('id')->limit(10)->get(),
            'statuses' => VatPeriod::STATUSES,
        ]);
    }

    public function show(Request $request, VatPeriod $vat): View
    {
        $vat->loadCount('transactions');

        $transactions = $vat->transactions()
            ->when($request->filled('type'), fn ($q) => $q->where('vat_type', $request->string('type')))
            ->orderBy('transaction_date')
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounting.vat.show', [
            'period' => $vat,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Roll the period totals up from its VAT transactions.
     */
    public function recalculate(Request $request, VatPeriod $vat): RedirectResponse
    {
        // The model reloads and checks status under the same lock as posting/finalize.
        $vat->recalculate();

        ActivityLog::record($request, 'Accounting', 'Recalculated VAT period', $vat->period_name);

        return back()->with('status', 'VAT period "'.$vat->period_name.'" recalculated. VAT payable is SAR '.number_format((float) $vat->fresh()->vat_payable, 2).'.');
    }

    public function finalize(Request $request, VatPeriod $vat): RedirectResponse
    {
        // Lock the period row and re-check its status inside the transaction so two
        // finalize clicks, or a finalize racing a posting, cannot both succeed (F03/F11).
        DB::transaction(function () use ($vat) {
            $period = VatPeriod::whereKey($vat->id)->lockForUpdate()->firstOrFail();

            if ($period->status !== 'draft') {
                throw ValidationException::withMessages(['vat' => 'Only a draft VAT period can be finalized; this one is already '.$period->status.'.']);
            }

            $period->recalculate();
            $period->update(['status' => 'finalized']);
        });

        ActivityLog::record($request, 'Accounting', 'Finalized VAT period', $vat->period_name);

        return back()->with('status', 'VAT period "'.$vat->period_name.'" finalized.');
    }
}
