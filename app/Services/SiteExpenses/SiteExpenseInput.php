<?php

namespace App\Services\SiteExpenses;

use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one set of Site Expense business rules, shared by the online form and
 * the offline sync handler so a queued expense is validated exactly like a
 * typed one. Lookups run through the acting user's scope: an out-of-scope or
 * inactive reference is simply not found (ModelNotFoundException), which the
 * form answers with 404 and the sync handler turns into a structured
 * "stale reference" result.
 */
final class SiteExpenseInput
{
    public const RECEIPT_RULES = ['file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(bool $withReceipt = true): array
    {
        $rules = [
            'expense_date' => ['required', 'date'], 'project_id' => ['required', 'integer'], 'site_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'integer'], 'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment_type' => ['required', Rule::in(SiteExpense::PAYMENT_TYPES)], 'payment_account_id' => ['nullable', 'integer'],
            'taxable_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'vat_applicable' => ['required', 'boolean'], 'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['required', 'string', 'max:4000'], 'reference_number' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
        if ($withReceipt) {
            $rules['receipt'] = array_merge(['nullable'], self::RECEIPT_RULES);
        }

        return $rules;
    }

    /**
     * Apply the business rules to already shape-validated input and return
     * the attributes to store plus the resolved category.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, category: ExpenseCategory}
     *
     * @throws ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function normalize(User $user, array $data, bool $vatApplicable, ?string $vatRateOverride, ?SiteExpense $expense = null): array
    {
        Project::whereKey($data['project_id'])->firstOrFail();
        Site::whereKey($data['site_id'])->where('project_id', $data['project_id'])->firstOrFail();
        $category = ExpenseCategory::whereKey($data['expense_category_id'])->where('status', 'active')->firstOrFail();
        if ($data['supplier_id'] ?? null) {
            Supplier::whereKey($data['supplier_id'])->where('status', 'active')->firstOrFail();
        }
        if ($data['payment_type'] === 'Supplier Credit' && empty($data['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => 'Choose a supplier for Supplier Credit.']);
        }
        if ($data['payment_type'] === 'Employee Reimbursement') {
            $employeeId = $expense?->employee_id ?? Employee::where('user_id', $user->id)->value('id');
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
        $rate = $vatApplicable ? $defaultRate : 0;
        if ($user->hasPermission('Site Expenses', 'post') && $vatApplicable && $vatRateOverride !== null && $vatRateOverride !== '') {
            $rate = (float) $vatRateOverride;
        }
        $data['taxable_amount'] = round((float) $data['taxable_amount'], 2);
        $data['vat_rate'] = round($rate, 2);
        $data['vat_amount'] = round($data['taxable_amount'] * $data['vat_rate'] / 100, 2);
        $data['total_amount'] = round($data['taxable_amount'] + $data['vat_amount'], 2);
        unset($data['receipt'], $data['vat_applicable']);

        return ['data' => $data, 'category' => $category];
    }

    /** Mobile users see only mobile-visible categories unless they hold Finance posting rights. */
    public function categoryHiddenFromMobile(User $user, ExpenseCategory $category): bool
    {
        return (bool) $user->mobile_access && ! $category->mobile_visible && ! $user->hasPermission('Site Expenses', 'post');
    }
}
