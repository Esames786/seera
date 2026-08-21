<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Project;
use App\Services\Accounting\PostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsReceivableController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(Request $request): View
    {
        $invoices = CustomerInvoice::with(['customer', 'project', 'zatcaRecord'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->integer('customer')))
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->string('status')))
            ->when($request->filled('zatca'), fn ($q) => $q->where('zatca_status', $request->string('zatca')))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $open = CustomerInvoice::whereIn('payment_status', ['unpaid', 'partially_paid']);

        return view('admin.accounting.accounts-receivable.index', [
            'invoices' => $invoices,
            'totalReceivable' => round((float) (clone $open)->sum('balance_amount'), 2),
            'overdueCount' => (clone $open)->whereDate('due_date', '<', now())->count(),
            'draftCount' => CustomerInvoice::where('payment_status', 'draft')->count(),
            'receivedThisMonth' => round((float) CustomerReceipt::whereDate('receipt_date', '>=', now()->startOfMonth())->sum('amount'), 2),
            'recentReceipts' => CustomerReceipt::with(['customer', 'invoice'])->latest('id')->limit(8)->get(),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.accounting.accounts-receivable.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $invoice = DB::transaction(function () use ($data, $lines) {
            $invoice = CustomerInvoice::create($data);
            $invoice->lines()->createMany($lines);

            return $invoice;
        });

        ActivityLog::record($request, 'Accounting', 'Created customer invoice', $invoice->invoice_number);

        return redirect()->route('admin.accounting.accounts-receivable.show', $invoice)
            ->with('status', 'Invoice "'.$invoice->invoice_number.'" saved. Approve it to post the accounting entry and create the ZATCA record.');
    }

    public function show(CustomerInvoice $accounts_receivable): View
    {
        $accounts_receivable->load([
            'customer', 'project', 'costCenter', 'lines.revenueAccount',
            'receipts.receiptAccount', 'journalEntry.lines.account', 'zatcaRecord',
        ]);

        return view('admin.accounting.accounts-receivable.show', ['invoice' => $accounts_receivable]);
    }

    public function edit(CustomerInvoice $accounts_receivable): View
    {
        if (! $accounts_receivable->isEditable()) {
            abort(403, 'An approved invoice can no longer be edited.');
        }

        $accounts_receivable->load('lines');

        return view('admin.accounting.accounts-receivable.edit', ['invoice' => $accounts_receivable] + $this->formOptions());
    }

    public function update(Request $request, CustomerInvoice $accounts_receivable): RedirectResponse
    {
        if (! $accounts_receivable->isEditable()) {
            return back()->withErrors(['invoice' => 'An approved invoice can no longer be edited.']);
        }

        [$data, $lines] = $this->validated($request, $accounts_receivable);

        DB::transaction(function () use ($accounts_receivable, $data, $lines) {
            $accounts_receivable->update($data);
            $accounts_receivable->lines()->delete();
            $accounts_receivable->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Accounting', 'Updated customer invoice', $accounts_receivable->invoice_number);

        return redirect()->route('admin.accounting.accounts-receivable.show', $accounts_receivable)
            ->with('status', 'Invoice "'.$accounts_receivable->invoice_number.'" updated successfully.');
    }

    public function destroy(Request $request, CustomerInvoice $accounts_receivable): RedirectResponse
    {
        if (! $accounts_receivable->isEditable()) {
            return back()->withErrors(['invoice' => 'An approved invoice cannot be deleted.']);
        }

        $number = $accounts_receivable->invoice_number;
        $accounts_receivable->delete();

        ActivityLog::record($request, 'Accounting', 'Deleted customer invoice', $number);

        return redirect()->route('admin.accounting.accounts-receivable.index')
            ->with('status', 'Invoice "'.$number.'" deleted successfully.');
    }

    /**
     * Approving an invoice posts AR against revenue + output VAT and opens the
     * ZATCA record for this invoice.
     */
    public function approve(Request $request, CustomerInvoice $accounts_receivable): RedirectResponse
    {
        [$entry, $record] = DB::transaction(function () use ($accounts_receivable, $request) {
            $accounts_receivable = CustomerInvoice::whereKey($accounts_receivable->id)->lockForUpdate()->firstOrFail();
            if ($accounts_receivable->payment_status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only a draft invoice can be approved.']);
            }

            $entry = $this->posting->postCustomerInvoice($accounts_receivable, $request->user()->id);
            $accounts_receivable->update([
                'payment_status' => 'unpaid',
                'received_amount' => 0,
                'balance_amount' => $accounts_receivable->total_amount,
                'journal_entry_id' => $entry?->id,
            ]);

            return [$entry, $this->posting->createZatcaRecord($accounts_receivable)];
        });

        ActivityLog::record($request, 'Accounting', 'Approved customer invoice', $accounts_receivable->invoice_number);

        return redirect()->route('admin.accounting.accounts-receivable.show', $accounts_receivable)
            ->with('status', 'Invoice approved, ZATCA record '.$record->uuid.' created'.($entry ? ' and journal entry '.$entry->journal_number.' posted.' : '.'));
    }

    public function receiptForm(CustomerInvoice $accounts_receivable): View
    {
        $accounts_receivable->load(['customer', 'receipts']);

        return view('admin.accounting.accounts-receivable.receipt', [
            'invoice' => $accounts_receivable,
        ] + $this->formOptions());
    }

    /**
     * Recording a receipt debits cash/bank and credits accounts receivable.
     */
    public function storeReceipt(Request $request, CustomerInvoice $accounts_receivable): RedirectResponse
    {
        $data = $request->validate([
            'receipt_date' => ['required', 'date'],
            'receipt_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->where('status', 'active'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ], [
            'amount.max' => 'The receipt cannot be more than the outstanding balance.',
        ]);

        $receipt = DB::transaction(function () use ($accounts_receivable, $data, $request) {
            $accounts_receivable = CustomerInvoice::whereKey($accounts_receivable->id)->lockForUpdate()->firstOrFail();
            if (in_array($accounts_receivable->payment_status, ['draft', 'cancelled', 'paid'], true)) {
                throw ValidationException::withMessages(['receipt' => 'This invoice is not open for receipt.']);
            }
            if ((float) $data['amount'] > (float) $accounts_receivable->balance_amount + 0.001) {
                throw ValidationException::withMessages(['amount' => 'The receipt cannot be more than the outstanding balance.']);
            }

            $receipt = CustomerReceipt::create($data + [
                'customer_id' => $accounts_receivable->customer_id,
                'customer_invoice_id' => $accounts_receivable->id,
            ]);

            $entry = $this->posting->postCustomerReceipt($receipt, $request->user()->id);
            $receipt->update(['journal_entry_id' => $entry?->id]);

            $accounts_receivable->refreshPaymentStatus();

            return $receipt;
        });

        ActivityLog::record($request, 'Accounting', 'Recorded customer receipt', $accounts_receivable->invoice_number.' - SAR '.number_format((float) $receipt->amount, 2));

        return redirect()->route('admin.accounting.accounts-receivable.show', $accounts_receivable)
            ->with('status', 'Receipt recorded successfully.');
    }

    /**
     * @return array{0: array, 1: array} Invoice attributes and normalized line rows.
     */
    private function validated(Request $request, ?CustomerInvoice $invoice = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:customer_invoices,invoice_number'.($invoice ? ','.$invoice->id : '')],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.revenue_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
        ], [
            'lines.required' => 'Add at least one invoice line.',
        ]);

        $vatRate = (float) $data['vat_rate'];

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['description'] ?? null) && (float) ($line['unit_price'] ?? 0) > 0)
            ->map(function ($line) use ($vatRate) {
                $taxable = round((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2);
                $vat = round($taxable * $vatRate / 100, 2);

                return [
                    'description' => $line['description'],
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_price' => $line['unit_price'] ?? 0,
                    'taxable_amount' => $taxable,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vat,
                    'total_amount' => round($taxable + $vat, 2),
                    'revenue_account_id' => $line['revenue_account_id'] ?? null,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                ];
            })
            ->values()
            ->all();

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one invoice line with a description and unit price.',
            ]);
        }

        $taxable = round(array_sum(array_column($lines, 'taxable_amount')), 2);
        $vat = round(array_sum(array_column($lines, 'vat_amount')), 2);

        unset($data['lines']);

        $data['invoice_number'] = $data['invoice_number']
            ?? $invoice?->invoice_number
            ?? CustomerInvoice::nextNumber((int) date('Y', strtotime($data['invoice_date'])));

        $data += [
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
            'balance_amount' => round($taxable + $vat, 2),
            'payment_status' => $invoice?->payment_status ?? 'draft',
        ];

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'costCenters' => CostCenter::where('status', 'active')->orderBy('code')->get(),
            'revenueAccounts' => ChartOfAccount::where('account_type', 'revenue')
                ->where('status', 'active')
                ->orderBy('account_code')
                ->get(),
            'receiptAccounts' => ChartOfAccount::where('account_type', 'asset')
                ->where('status', 'active')
                ->whereIn('account_code', [PostingService::CASH, PostingService::BANK])
                ->orderBy('account_code')
                ->get(),
            'statuses' => CustomerInvoice::PAYMENT_STATUSES,
            'zatcaStatuses' => CustomerInvoice::ZATCA_STATUSES,
        ];
    }
}
