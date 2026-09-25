<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VatController extends Controller
{
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
        $draftOutputVat = (float) \App\Models\CustomerInvoice::where('payment_status', 'draft')->sum('vat_amount');
        $draftInputVat = (float) \App\Models\SupplierBill::where('status', 'draft')->sum('vat_amount');

        return view('admin.accounting.vat.index', [
            'periods' => $periods,
            'outputVat' => round($outputVat, 2),
            'inputVat' => round($inputVat, 2),
            'vatPayable' => round($outputVat - $inputVat, 2),
            'draftOutputVat' => round($draftOutputVat, 2),
            'draftInputVat' => round($draftInputVat, 2),
            'draftInvoices' => \App\Models\CustomerInvoice::where('payment_status', 'draft')->count(),
            'draftBills' => \App\Models\SupplierBill::where('status', 'draft')->count(),
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
        // Once finalized the stored totals are the return; they are not recomputed (F03).
        if ($vat->status !== 'draft') {
            return back()->withErrors(['vat' => 'A '.$vat->status.' VAT period can no longer be recalculated.']);
        }

        $vat->recalculate();

        ActivityLog::record($request, 'Accounting', 'Recalculated VAT period', $vat->period_name);

        return back()->with('status', 'VAT period "'.$vat->period_name.'" recalculated. VAT payable is SAR '.number_format((float) $vat->fresh()->vat_payable, 2).'.');
    }

    public function finalize(Request $request, VatPeriod $vat): RedirectResponse
    {
        // Lock the period row and re-check its status inside the transaction so two
        // finalize clicks, or a finalize racing a posting, cannot both succeed (F03/F11).
        \Illuminate\Support\Facades\DB::transaction(function () use ($vat) {
            $period = VatPeriod::whereKey($vat->id)->lockForUpdate()->firstOrFail();

            if ($period->status !== 'draft') {
                throw \Illuminate\Validation\ValidationException::withMessages(['vat' => 'Only a draft VAT period can be finalized; this one is already '.$period->status.'.']);
            }

            $period->recalculate();
            $period->update(['status' => 'finalized']);
        });

        ActivityLog::record($request, 'Accounting', 'Finalized VAT period', $vat->period_name);

        return back()->with('status', 'VAT period "'.$vat->period_name.'" finalized.');
    }
}
