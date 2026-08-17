<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = ChartOfAccount::with('parent')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('account_name', 'like', "%{$search}%")
                    ->orWhere('account_code', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('account_type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('account_code')
            ->get();

        return view('admin.accounting.chart-of-accounts.index', [
            'accounts' => $accounts,
            'rootAccounts' => ChartOfAccount::with('children.children')->whereNull('parent_id')->orderBy('account_code')->get(),
            'totalAccounts' => ChartOfAccount::count(),
            'activeAccounts' => ChartOfAccount::where('status', 'active')->count(),
            'vatAccounts' => ChartOfAccount::where('vat_applicable', true)->count(),
            'costCenterAccounts' => ChartOfAccount::where('cost_center_required', true)->count(),
            'types' => ChartOfAccount::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.accounting.chart-of-accounts.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $account = ChartOfAccount::create($this->validated($request));

        ActivityLog::record($request, 'Accounting', 'Created account', $account->label());

        return redirect()->route('admin.accounting.chart-of-accounts.index')
            ->with('status', 'Account "'.$account->label().'" created successfully.');
    }

    public function show(ChartOfAccount $chart_of_account): View
    {
        $chart_of_account->load(['parent', 'children']);

        return view('admin.accounting.chart-of-accounts.show', [
            'account' => $chart_of_account,
            'balance' => $chart_of_account->postedBalance(),
            'lines' => $chart_of_account->journalLines()
                ->with('journalEntry')
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(ChartOfAccount $chart_of_account): View
    {
        return view('admin.accounting.chart-of-accounts.edit', ['account' => $chart_of_account] + $this->formOptions($chart_of_account));
    }

    public function update(Request $request, ChartOfAccount $chart_of_account): RedirectResponse
    {
        $chart_of_account->update($this->validated($request, $chart_of_account));

        ActivityLog::record($request, 'Accounting', 'Updated account', $chart_of_account->label());

        return redirect()->route('admin.accounting.chart-of-accounts.index')
            ->with('status', 'Account "'.$chart_of_account->label().'" updated successfully.');
    }

    /**
     * Accounts carrying transactions or children are deactivated, never deleted.
     */
    public function destroy(Request $request, ChartOfAccount $chart_of_account): RedirectResponse
    {
        $label = $chart_of_account->label();

        if ($chart_of_account->journalLines()->exists() || $chart_of_account->children()->exists()) {
            $chart_of_account->update(['status' => 'inactive']);

            ActivityLog::record($request, 'Accounting', 'Deactivated account', $label);

            return redirect()->route('admin.accounting.chart-of-accounts.index')
                ->with('status', 'Account "'.$label.'" has transactions or sub-accounts, so it was deactivated instead of deleted.');
        }

        $chart_of_account->delete();

        ActivityLog::record($request, 'Accounting', 'Deleted account', $label);

        return redirect()->route('admin.accounting.chart-of-accounts.index')
            ->with('status', 'Account "'.$label.'" deleted successfully.');
    }

    private function validated(Request $request, ?ChartOfAccount $account = null): array
    {
        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'unique:chart_of_accounts,account_code'.($account ? ','.$account->id : '')],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => array_filter(['nullable', 'exists:chart_of_accounts,id', $account ? 'not_in:'.$account->id : null]),
            'opening_balance' => ['required', 'numeric'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'vat_applicable' => ['nullable', 'boolean'],
            'cost_center_required' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ], [], ['parent_id' => 'parent account']);

        $data['vat_applicable'] = $request->boolean('vat_applicable');
        $data['cost_center_required'] = $request->boolean('cost_center_required');

        return $data;
    }

    private function formOptions(?ChartOfAccount $account = null): array
    {
        return [
            'parents' => ChartOfAccount::when($account, fn ($q) => $q->whereKeyNot($account->id))
                ->orderBy('account_code')
                ->get(),
            'types' => ChartOfAccount::TYPES,
        ];
    }
}
