<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoPostingRuleController extends Controller
{
    public function index(Request $request): View
    {
        $rules = AutomaticPostingRule::with(['debitAccount', 'creditAccount'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('source_module', 'like', "%{$search}%")
                    ->orWhere('trigger_event', 'like', "%{$search}%"));
            })
            ->when($request->filled('module'), fn ($q) => $q->where('source_module', $request->string('module')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('source_module')
            ->paginate(10)
            ->withQueryString();

        return view('admin.accounting.posting-rules.index', [
            'rules' => $rules,
            'totalRules' => AutomaticPostingRule::count(),
            'activeRules' => AutomaticPostingRule::where('status', 'active')->count(),
            'autoPostRules' => AutomaticPostingRule::where('auto_post', true)->count(),
            'approvalRules' => AutomaticPostingRule::where('approval_required', true)->count(),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.accounting.posting-rules.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = AutomaticPostingRule::create($this->validated($request));

        ActivityLog::record($request, 'Accounting', 'Created posting rule', $rule->source_module.' - '.$rule->trigger_event);

        return redirect()->route('admin.accounting.posting-rules.index')
            ->with('status', 'Posting rule for "'.$rule->source_module.'" created successfully.');
    }

    public function show(AutomaticPostingRule $posting_rule): View
    {
        $posting_rule->load(['debitAccount', 'creditAccount']);

        return view('admin.accounting.posting-rules.show', ['rule' => $posting_rule]);
    }

    public function edit(AutomaticPostingRule $posting_rule): View
    {
        return view('admin.accounting.posting-rules.edit', ['rule' => $posting_rule] + $this->formOptions());
    }

    public function update(Request $request, AutomaticPostingRule $posting_rule): RedirectResponse
    {
        $posting_rule->update($this->validated($request));

        ActivityLog::record($request, 'Accounting', 'Updated posting rule', $posting_rule->source_module.' - '.$posting_rule->trigger_event);

        return redirect()->route('admin.accounting.posting-rules.index')
            ->with('status', 'Posting rule updated successfully.');
    }

    public function destroy(Request $request, AutomaticPostingRule $posting_rule): RedirectResponse
    {
        $label = $posting_rule->source_module.' - '.$posting_rule->trigger_event;
        $posting_rule->delete();

        ActivityLog::record($request, 'Accounting', 'Deleted posting rule', $label);

        return redirect()->route('admin.accounting.posting-rules.index')
            ->with('status', 'Posting rule deleted successfully.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'source_module' => ['required', 'string', 'max:100'],
            'trigger_event' => ['required', 'string', 'max:100'],
            'debit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'cost_center_rule' => ['required', 'string', 'max:100'],
            'auto_post' => ['nullable', 'boolean'],
            'approval_required' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['auto_post'] = $request->boolean('auto_post');
        $data['approval_required'] = $request->boolean('approval_required');

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'accounts' => ChartOfAccount::where('status', 'active')->orderBy('account_code')->get(),
            'sourceModules' => JournalEntry::SOURCE_MODULES,
            'triggerEvents' => [
                'Payroll Approved', 'Site Expense Approved', 'Inventory Purchase',
                'Stock Issued', 'Stock Adjusted', 'Bill Approved', 'Payment Recorded',
                'Invoice Approved', 'Receipt Recorded',
            ],
            'costCenterRules' => AutomaticPostingRule::COST_CENTER_RULES,
        ];
    }
}
