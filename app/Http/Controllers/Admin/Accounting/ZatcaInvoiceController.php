<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ZatcaInvoiceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZatcaInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $records = ZatcaInvoiceRecord::with('customerInvoice.customer')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('customerInvoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%")));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('clearance_status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.accounting.zatca.index', [
            'records' => $records,
            'clearedCount' => ZatcaInvoiceRecord::where('clearance_status', 'cleared')->count(),
            'pendingCount' => ZatcaInvoiceRecord::whereIn('clearance_status', ['pending', 'generated'])->count(),
            'failedCount' => ZatcaInvoiceRecord::where('clearance_status', 'failed')->count(),
            'draftCount' => ZatcaInvoiceRecord::where('clearance_status', 'draft')->count(),
            'statuses' => ZatcaInvoiceRecord::CLEARANCE_STATUSES,
        ]);
    }

    public function show(ZatcaInvoiceRecord $zatca): View
    {
        $zatca->load('customerInvoice.customer');

        return view('admin.accounting.zatca.show', ['record' => $zatca]);
    }

    /**
     * Foundation-only retry: re-queues the record for clearance and bumps the
     * retry counter. The live ZATCA clearance call arrives in the ZATCA phase.
     */
    public function retry(Request $request, ZatcaInvoiceRecord $zatca): RedirectResponse
    {
        if ($zatca->clearance_status !== 'failed') {
            return back()->withErrors(['zatca' => 'Only a failed ZATCA record can be retried.']);
        }

        $zatca->update([
            'clearance_status' => 'pending',
            'retry_count' => $zatca->retry_count + 1,
            'zatca_response_code' => null,
            'zatca_response_message' => 'Re-queued for clearance from the ERP admin panel.',
            'failed_reason' => null,
        ]);

        $zatca->customerInvoice?->update(['zatca_status' => 'pending_clearance']);

        ActivityLog::record($request, 'Accounting', 'Retried ZATCA clearance', $zatca->uuid);

        return back()->with('status', 'ZATCA record re-queued for clearance. Retry count is now '.$zatca->retry_count.'.');
    }
}
