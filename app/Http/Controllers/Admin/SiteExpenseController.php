<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SiteExpenseApprovalSubject;
use App\Services\DocumentNumberService;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Support\SaveAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteExpenseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $query = SiteExpense::with(['project', 'site', 'category', 'supplier', 'submitter']);
        foreach (['project_id', 'site_id', 'expense_category_id', 'submitted_by_user_id', 'payment_type', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('accounting_posted')) {
            $query->where('accounting_posted', $request->input('accounting_posted') === '1');
        }
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
            ->where('expense_number', 'like', '%'.$request->input('search').'%')->orWhere('description', 'like', '%'.$request->input('search').'%')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->input('to')));

        return view('admin.site-expenses.index', ['expenses' => $query->latest('id')->paginate(15)->withQueryString(),
            'projects' => Project::orderBy('name')->get(), 'sites' => Site::orderBy('name')->get(),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'submitters' => User::whereIn('id', SiteExpense::select('submitted_by_user_id'))->orderBy('name')->get(['id', 'name'])]);
    }

    public function create(Request $request)
    {
        return view('admin.site-expenses.form', $this->options($request) + ['expense' => new SiteExpense(['expense_date' => today()])]);
    }

    public function mobile(Request $request)
    {
        abort_unless($request->user()->mobile_access && $request->user()->hasPermission('Site Expenses', 'create'), 403);

        return $this->create($request);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $path = $this->upload($request);
        try {
            $expense = DB::transaction(function () use ($request, $data, $path) {
                $year = now()->year;
                $expense = SiteExpense::create($data + [
                    'expense_number' => app(DocumentNumberService::class)->next('site-expense-'.$year, 'SE-'.$year.'-', 'site_expenses', 'expense_number', 6),
                    'submitted_by_user_id' => $request->user()->id,
                    'employee_id' => Employee::where('user_id', $request->user()->id)->value('id'), 'status' => 'draft',
                ]);
                $this->receipt($request, $expense, $path);
                $this->log($request, $expense, 'Created draft');

                return $expense;
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        if ($request->input('_intent') === 'submit') {
            return $this->submitSaved($request, $expense);
        }

        return $this->saved($request, $expense);
    }

    public function edit(Request $request, SiteExpense $site_expense)
    {
        abort_unless(app(SiteExpenseApprovalSubject::class)->canSubmit($site_expense, $request->user()), 403, 'Only authorized draft or rejected expenses can be edited.');

        return view('admin.site-expenses.form', $this->options($request) + ['expense' => $site_expense]);
    }

    public function update(Request $request, SiteExpense $site_expense)
    {
        abort_unless(app(SiteExpenseApprovalSubject::class)->canSubmit($site_expense, $request->user()), 403);
        $data = $this->validated($request, $site_expense);
        $path = $this->upload($request);
        try {
            DB::transaction(function () use ($request, $site_expense, $data, $path) {
                $expense = SiteExpense::whereKey($site_expense->id)->lockForUpdate()->firstOrFail();
                abort_unless(app(SiteExpenseApprovalSubject::class)->canSubmit($expense, $request->user()), 403, 'This expense was submitted while you were editing.');
                $expense->update($data); // Never turn rejected history back into draft.
                $this->receipt($request, $expense, $path);
                $this->log($request, $expense, 'Updated expense');
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        if ($request->input('_intent') === 'submit') {
            return $this->submitSaved($request, $site_expense);
        }

        return $this->saved($request, $site_expense);
    }

    public function show(Request $request, SiteExpense $site_expense, ApprovalRuntimeService $runtime)
    {
        $expense = $site_expense->load(['project', 'site', 'category', 'supplier', 'employee', 'submitter', 'receipts']);
        $finance = $this->finance($request->user());
        if ($finance) {
            $expense->load('journalEntry', 'supplierBill.journalEntry', 'settlementJournal', 'reversalJournal');
        }

        return view('admin.site-expenses.show', ['expense' => $expense, 'finance' => $finance,
            'history' => $runtime->history(app(SiteExpenseApprovalSubject::class), $expense)->get(), 'runtime' => $runtime,
            'subject' => app(SiteExpenseApprovalSubject::class), 'workflows' => $this->workflows(),
            'settlementAccounts' => $finance ? SiteExpenseAccountingService::paymentAccounts('Cash')->get()->merge(SiteExpenseAccountingService::paymentAccounts('Bank')->get()) : collect(),
            'activity' => ActivityLog::visibleTo($request->user())->where('module', 'Site Expenses')
                ->where('description', 'like', '[SiteExpense #'.$expense->id.'] %')->latest('id')->limit(50)->get()]);
    }

    public function submit(Request $request, SiteExpense $site_expense)
    {
        $request->validate(['workflow_id' => ['nullable', 'integer'], 'previous_instance_id' => ['nullable', 'integer']]);
        $this->start($request, $site_expense);

        return redirect()->route('admin.site-expenses.show', $site_expense)->with('status', 'Expense '.$site_expense->expense_number.' submitted for approval. All required reviewers must approve before accounting is attempted.');
    }

    private function submitSaved(Request $request, SiteExpense $expense)
    {
        try {
            return $this->submit($request, $expense);
        } catch (ValidationException $error) {
            return redirect()->route('admin.site-expenses.show', $expense)->withErrors($error->errors())
                ->with('status', 'Expense saved safely. Resolve the approval configuration before submitting.');
        }
    }

    private function start(Request $request, SiteExpense $expense): void
    {
        $choices = $this->workflows();
        if ($choices->isEmpty()) {
            throw ValidationException::withMessages(['approval' => 'No approval workflow is configured for Site Expenses. Please contact an administrator.']);
        }
        $id = $request->integer('workflow_id') ?: ($choices->count() === 1 ? $choices->first()->id : 0);
        if (! $id || ! $choices->contains('id', $id)) {
            throw ValidationException::withMessages(['workflow_id' => 'Choose a configured Site Expense approval workflow.']);
        }
        app(ApprovalRuntimeService::class)->start(app(SiteExpenseApprovalSubject::class), $expense->id, $id,
            $request->user(), $request->filled('previous_instance_id') ? $request->integer('previous_instance_id') : null);
    }

    public function approve(Request $request, SiteExpense $site_expense)
    {
        return $this->decision($request, $site_expense, 'approve');
    }

    public function reject(Request $request, SiteExpense $site_expense)
    {
        return $this->decision($request, $site_expense, 'reject');
    }

    private function decision(Request $request, SiteExpense $expense, string $action)
    {
        $data = $request->validate(['instance_id' => ['required', 'integer'], 'step_id' => ['required', 'integer'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $instance = $expense->approvals()->findOrFail($data['instance_id']);
        $step = $instance->steps()->findOrFail($data['step_id']);
        app(ApprovalRuntimeService::class)->decide(app(SiteExpenseApprovalSubject::class), $expense->id, $instance->id, $step->id, $request->user(), $action, $data['comment'] ?? null);

        return redirect()->route('admin.site-expenses.show', $expense)->with('status', 'Approval decision saved. Review the current accounting status below.');
    }

    public function retry(Request $request, SiteExpense $site_expense)
    {
        abort_unless($request->user()->hasPermission('Site Expenses', 'post'), 403);
        app(SiteExpenseAccountingService::class)->attempt($site_expense->id, $request->user()->id);

        return redirect()->route('admin.site-expenses.show', $site_expense)->with('status', 'Accounting retry checked. Existing journals and bills are never duplicated.');
    }

    public function settle(Request $request, SiteExpense $site_expense)
    {
        abort_unless($request->user()->hasPermission('Site Expenses', 'post') && $request->user()->hasPermission('Site Expenses', 'process'), 403);
        $data = $request->validate(['payment_account_id' => ['required', 'integer']]);
        app(SiteExpenseAccountingService::class)->settle($site_expense->id, (int) $data['payment_account_id'], $request->user()->id);

        return redirect()->route('admin.site-expenses.show', $site_expense)->with('status', 'Employee reimbursement settled.');
    }

    public function reverse(Request $request, SiteExpense $site_expense)
    {
        abort_unless($request->user()->hasPermission('Site Expenses', 'post') && $request->user()->hasPermission('Site Expenses', 'process'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        if (trim($data['reason']) === '') {
            throw ValidationException::withMessages(['reason' => 'Give a correction reason.']);
        }
        app(SiteExpenseAccountingService::class)->reverse($site_expense->id, trim($data['reason']), $request->user()->id);

        return redirect()->route('admin.site-expenses.show', $site_expense)->with('status', 'Opposite journal posted; original history retained.');
    }

    public function destroy(Request $request, SiteExpense $site_expense)
    {
        DB::transaction(function () use ($request, $site_expense) {
            $expense = SiteExpense::whereKey($site_expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->status === 'draft', 403, 'Only a draft may be cancelled. Financial and approval history cannot be deleted.');
            $expense->update(['status' => 'cancelled']);
            $this->log($request, $expense, 'Cancelled draft');
        });

        return redirect()->route('admin.site-expenses.show', $site_expense)->with('status', 'Draft cancelled; retained in history.');
    }

    public function receiptFile(Request $request, SiteExpense $site_expense, int $receipt)
    {
        $file = $site_expense->receipts()->findOrFail($receipt);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($file->original_filename));
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Type' => $file->mime_type,
            'Content-Security-Policy' => "default-src 'none'; sandbox"];

        return $request->boolean('download') ? Storage::disk('local')->download($file->path, $name, $headers)
            : Storage::disk('local')->response($file->path, $name, $headers, 'inline');
    }

    private function validated(Request $request, ?SiteExpense $expense = null): array
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'], 'project_id' => ['required', 'integer'], 'site_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'integer'], 'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment_type' => ['required', Rule::in(SiteExpense::PAYMENT_TYPES)], 'payment_account_id' => ['nullable', 'integer'],
            'taxable_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'vat_applicable' => ['required', 'boolean'], 'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['required', 'string', 'max:4000'], 'reference_number' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        Project::whereKey($data['project_id'])->firstOrFail();
        Site::whereKey($data['site_id'])->where('project_id', $data['project_id'])->firstOrFail();
        $category = ExpenseCategory::whereKey($data['expense_category_id'])->where('status', 'active')->firstOrFail();
        if ($request->user()->mobile_access && ! $category->mobile_visible && ! $request->user()->hasPermission('Site Expenses', 'post')) {
            abort(403);
        }
        if ($data['supplier_id'] ?? null) {
            Supplier::whereKey($data['supplier_id'])->where('status', 'active')->firstOrFail();
        }
        if ($data['payment_type'] === 'Supplier Credit' && empty($data['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => 'Choose a supplier for Supplier Credit.']);
        }
        if ($data['payment_type'] === 'Employee Reimbursement') {
            $employeeId = $expense?->employee_id ?? Employee::where('user_id', $request->user()->id)->value('id');
            if (! $employeeId) {
                throw ValidationException::withMessages(['payment_type' => 'Link the submitting user to an employee before recording employee-paid expenses.']);
            }
        }
        if (in_array($data['payment_type'], ['Cash', 'Bank'], true)) {
            if (in_array($category->payment_type, ['Cash', 'Bank'], true) && $category->payment_type !== $data['payment_type']) {
                throw ValidationException::withMessages(['payment_type' => 'This category does not permit this payment channel.']);
            }
            if (! SiteExpenseAccountingService::paymentAccounts($data['payment_type'])->whereKey($data['payment_account_id'] ?? 0)->exists()) {
                throw ValidationException::withMessages(['payment_account_id' => 'Choose an active permitted payment account. Contact Finance if none is available.']);
            }
        } else {
            $data['payment_account_id'] = null;
        }
        $defaultRate = $category->vat_treatment === 'VAT 15%' ? 15 : 0;
        $rate = $request->boolean('vat_applicable') ? $defaultRate : 0;
        if ($request->user()->hasPermission('Site Expenses', 'post') && $request->boolean('vat_applicable') && $request->filled('vat_rate')) {
            $rate = (float) $data['vat_rate'];
        }
        $data['taxable_amount'] = round((float) $data['taxable_amount'], 2);
        $data['vat_rate'] = round($rate, 2);
        $data['vat_amount'] = round($data['taxable_amount'] * $data['vat_rate'] / 100, 2);
        $data['total_amount'] = round($data['taxable_amount'] + $data['vat_amount'], 2);
        unset($data['receipt'], $data['vat_applicable']);

        return $data;
    }

    private function options(Request $request): array
    {
        return ['projects' => Project::orderBy('name')->get(), 'sites' => Site::orderBy('name')->get(),
            'categories' => ExpenseCategory::where('status', 'active')
                ->when($request->user()->mobile_access && ! $request->user()->hasPermission('Site Expenses', 'post'), fn ($q) => $q->where('mobile_visible', true))->orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'accounts' => SiteExpenseAccountingService::paymentAccounts('Cash')->get()->merge(SiteExpenseAccountingService::paymentAccounts('Bank')->get()),
            'workflows' => $this->workflows(), 'finance' => $this->finance($request->user())];
    }

    private function workflows()
    {
        return ApprovalWorkflow::where('module', 'Site Expenses')->where('trigger_action', 'Expense Submitted')->where('status', 'active')->orderBy('name')->get();
    }

    private function finance(User $user): bool
    {
        return $user->hasPermission('Site Expenses', 'post') || $user->hasPermission('Journal Entries', 'view') || $user->hasPermission('Accounts Payable', 'view');
    }

    private function upload(Request $request): ?string
    {
        return $request->hasFile('receipt') ? $request->file('receipt')->store('site-expense-receipts', 'local') : null;
    }

    private function receipt(Request $request, SiteExpense $expense, ?string $path): void
    {
        if (! $path) {
            return;
        }
        $file = $request->file('receipt');
        $expense->receipts()->create(['path' => $path, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 191),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id]);
    }

    private function log(Request $request, SiteExpense $expense, string $action): void
    {
        ActivityLog::record($request, 'Site Expenses', $action, '[SiteExpense #'.$expense->id.'] '.$expense->expense_number);
    }

    private function saved(Request $request, SiteExpense $expense)
    {
        return SaveAction::redirect($request, ['stay' => route('admin.site-expenses.edit', $expense),
            'close' => route('admin.site-expenses.index'), 'new' => route('admin.site-expenses.create')])->with('status', 'Expense '.$expense->expense_number.' saved.');
    }
}
