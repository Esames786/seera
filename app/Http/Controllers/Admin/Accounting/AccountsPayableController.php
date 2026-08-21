<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Services\Accounting\PostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsPayableController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(Request $request): View
    {
        $bills = SupplierBill::with(['supplier', 'project'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%")));
            })
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $open = SupplierBill::whereIn('status', ['unpaid', 'partially_paid', 'approved']);

        return view('admin.accounting.accounts-payable.index', [
            'bills' => $bills,
            'totalPayable' => round((float) (clone $open)->sum('balance_amount'), 2),
            'overdueCount' => (clone $open)->whereDate('due_date', '<', now())->count(),
            'draftCount' => SupplierBill::where('status', 'draft')->count(),
            'paidThisMonth' => round((float) SupplierPayment::whereDate('payment_date', '>=', now()->startOfMonth())->sum('amount'), 2),
            'recentPayments' => SupplierPayment::with(['supplier', 'bill'])->latest('id')->limit(8)->get(),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.accounting.accounts-payable.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $bill = DB::transaction(function () use ($data, $lines) {
            $bill = SupplierBill::create($data);
            $bill->lines()->createMany($lines);

            return $bill;
        });

        ActivityLog::record($request, 'Accounting', 'Created supplier bill', $bill->bill_number);

        return redirect()->route('admin.accounting.accounts-payable.show', $bill)
            ->with('status', 'Supplier bill "'.$bill->bill_number.'" saved. Approve it to post the accounting entry.');
    }

    public function show(SupplierBill $accounts_payable): View
    {
        $accounts_payable->load([
            'supplier', 'project', 'site', 'costCenter',
            'lines.expenseCategory', 'lines.account',
            'payments.paymentAccount', 'journalEntry.lines.account',
        ]);

        return view('admin.accounting.accounts-payable.show', ['bill' => $accounts_payable]);
    }

    public function edit(SupplierBill $accounts_payable): View
    {
        if (! $accounts_payable->isEditable()) {
            abort(403, 'An approved supplier bill can no longer be edited.');
        }

        $accounts_payable->load('lines');

        return view('admin.accounting.accounts-payable.edit', ['bill' => $accounts_payable] + $this->formOptions());
    }

    public function update(Request $request, SupplierBill $accounts_payable): RedirectResponse
    {
        if (! $accounts_payable->isEditable()) {
            return back()->withErrors(['bill' => 'An approved supplier bill can no longer be edited.']);
        }

        [$data, $lines] = $this->validated($request, $accounts_payable);

        DB::transaction(function () use ($accounts_payable, $data, $lines) {
            $accounts_payable->update($data);
            $accounts_payable->lines()->delete();
            $accounts_payable->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Accounting', 'Updated supplier bill', $accounts_payable->bill_number);

        return redirect()->route('admin.accounting.accounts-payable.show', $accounts_payable)
            ->with('status', 'Supplier bill "'.$accounts_payable->bill_number.'" updated successfully.');
    }

    public function destroy(Request $request, SupplierBill $accounts_payable): RedirectResponse
    {
        if (! $accounts_payable->isEditable()) {
            return back()->withErrors(['bill' => 'An approved supplier bill cannot be deleted.']);
        }

        $number = $accounts_payable->bill_number;
        $accounts_payable->delete();

        ActivityLog::record($request, 'Accounting', 'Deleted supplier bill', $number);

        return redirect()->route('admin.accounting.accounts-payable.index')
            ->with('status', 'Supplier bill "'.$number.'" deleted successfully.');
    }

    /**
     * Approving a bill posts expense + input VAT against accounts payable.
     */
    public function approve(Request $request, SupplierBill $accounts_payable): RedirectResponse
    {
        $entry = DB::transaction(function () use ($accounts_payable, $request) {
            $accounts_payable = SupplierBill::whereKey($accounts_payable->id)->lockForUpdate()->firstOrFail();
            if ($accounts_payable->status !== 'draft') {
                throw ValidationException::withMessages(['bill' => 'Only a draft supplier bill can be approved.']);
            }

            $entry = $this->posting->postSupplierBill($accounts_payable, $request->user()->id);
            $accounts_payable->update([
                'status' => 'unpaid',
                'paid_amount' => 0,
                'balance_amount' => $accounts_payable->total_amount,
                'journal_entry_id' => $entry?->id,
            ]);

            return $entry;
        });

        ActivityLog::record($request, 'Accounting', 'Approved supplier bill', $accounts_payable->bill_number);

        return redirect()->route('admin.accounting.accounts-payable.show', $accounts_payable)
            ->with('status', 'Supplier bill approved'.($entry ? ' and journal entry '.$entry->journal_number.' created.' : '.'));
    }

    public function paymentForm(SupplierBill $accounts_payable): View
    {
        $accounts_payable->load(['supplier', 'payments']);

        return view('admin.accounting.accounts-payable.payment', [
            'bill' => $accounts_payable,
        ] + $this->formOptions());
    }

    /**
     * Recording a payment debits accounts payable and credits cash/bank.
     */
    public function storePayment(Request $request, SupplierBill $accounts_payable): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->where('status', 'active'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ], [
            'amount.max' => 'The payment cannot be more than the outstanding balance.',
        ]);

        $payment = DB::transaction(function () use ($accounts_payable, $data, $request) {
            $accounts_payable = SupplierBill::whereKey($accounts_payable->id)->lockForUpdate()->firstOrFail();
            if (in_array($accounts_payable->status, ['draft', 'cancelled', 'paid'], true)) {
                throw ValidationException::withMessages(['payment' => 'This bill is not open for payment.']);
            }
            if ((float) $data['amount'] > (float) $accounts_payable->balance_amount + 0.001) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot be more than the outstanding balance.']);
            }

            $payment = SupplierPayment::create($data + [
                'supplier_id' => $accounts_payable->supplier_id,
                'supplier_bill_id' => $accounts_payable->id,
            ]);

            $entry = $this->posting->postSupplierPayment($payment, $request->user()->id);
            $payment->update(['journal_entry_id' => $entry?->id]);

            $accounts_payable->refreshPaymentStatus();

            return $payment;
        });

        ActivityLog::record($request, 'Accounting', 'Recorded supplier payment', $accounts_payable->bill_number.' - SAR '.number_format((float) $payment->amount, 2));

        return redirect()->route('admin.accounting.accounts-payable.show', $accounts_payable)
            ->with('status', 'Payment recorded successfully.');
    }

    /**
     * @return array{0: array, 1: array} Bill attributes and normalized line rows.
     */
    private function validated(Request $request, ?SupplierBill $bill = null): array
    {
        $supplierId = $request->input('supplier_id');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'bill_number' => [
                'required', 'string', 'max:100',
                'unique:supplier_bills,bill_number,'.($bill?->id ?? 'NULL').',id,supplier_id,'.$supplierId,
            ],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'lines.*.chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
        ], [
            'bill_number.unique' => 'This supplier already has a bill with that number.',
            'lines.required' => 'Add at least one bill line.',
        ]);

        $vatRate = (float) $data['vat_rate'];

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['description'] ?? null) && (float) ($line['unit_price'] ?? 0) > 0)
            ->map(function ($line) use ($vatRate) {
                $taxable = round((float) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0), 2);
                $vat = round($taxable * $vatRate / 100, 2);

                return [
                    'description' => $line['description'],
                    'expense_category_id' => $line['expense_category_id'] ?? null,
                    'chart_of_account_id' => $line['chart_of_account_id'] ?? null,
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_price' => $line['unit_price'] ?? 0,
                    'taxable_amount' => $taxable,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vat,
                    'total_amount' => round($taxable + $vat, 2),
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                ];
            })
            ->values()
            ->all();

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one bill line with a description and unit price.',
            ]);
        }

        $taxable = round(array_sum(array_column($lines, 'taxable_amount')), 2);
        $vat = round(array_sum(array_column($lines, 'vat_amount')), 2);

        unset($data['lines']);

        $data += [
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => round($taxable + $vat, 2),
            'balance_amount' => round($taxable + $vat, 2),
            'status' => $bill?->status ?? 'draft',
        ];

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'costCenters' => CostCenter::where('status', 'active')->orderBy('code')->get(),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
            'expenseAccounts' => ChartOfAccount::whereIn('account_type', ['expense', 'asset'])
                ->where('status', 'active')
                ->orderBy('account_code')
                ->get(),
            'paymentAccounts' => ChartOfAccount::where('account_type', 'asset')
                ->where('status', 'active')
                ->whereIn('account_code', [PostingService::CASH, PostingService::BANK])
                ->orderBy('account_code')
                ->get(),
            'statuses' => SupplierBill::STATUSES,
        ];
    }
}
