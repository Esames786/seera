<?php

namespace App\Services\Sync;

use App\Contracts\OfflineSyncHandler;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\OfflineSyncReceipt;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\SiteExpenses\SiteExpenseInput;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Offline Site Expense entry, synced later. The sync creates the same draft
 * document the online form creates (same rules, same private receipt path),
 * atomically with its receipt file, and stops there: approval, Supplier
 * Credit bridging and accounting continue online through the existing
 * runtime. Nothing is approved or posted by sync.
 */
final class SiteExpenseSyncHandler implements OfflineSyncHandler
{
    public const CREATE = 'create';

    public function __construct(private readonly SiteExpenseInput $input) {}

    public function operation(): string
    {
        return OfflineSyncReceipt::OPERATION_SITE_EXPENSE;
    }

    public function actions(): array
    {
        return [self::CREATE];
    }

    public function payloadRules(string $action): array
    {
        return SiteExpenseInput::rules(withReceipt: false);
    }

    public function identity(string $action, array $payload): array
    {
        return $payload;
    }

    public function order(array $items): array
    {
        return $items;
    }

    public function handle(Request $request, User $user, OfflineSyncReceipt $receipt, string $action, array $payload, Carbon $capturedAt, ?UploadedFile $file): SyncResult
    {
        $receipt->employee_id = Employee::withoutGlobalScopes()->where('user_id', $user->id)->value('id');

        $maxAge = (int) config('seera.offline_sync.site_expense_max_age_days', 60);
        if (Carbon::parse($payload['expense_date'])->lt(today()->subDays($maxAge))) {
            return SyncResult::failed(SyncError::VALIDATION_ERROR, 'expense_date_too_old', __('offline_sync.expense_date_too_old', ['days' => $maxAge]));
        }

        try {
            $normalized = $this->input->normalize($user, $payload, (bool) $payload['vat_applicable'], isset($payload['vat_rate']) ? (string) $payload['vat_rate'] : null);
        } catch (ModelNotFoundException $missing) {
            return $this->stale($missing->getModel());
        } catch (ValidationException $invalid) {
            $field = array_key_first($invalid->errors());
            $message = collect($invalid->errors())->flatten()->first();

            return $field === 'payment_account_id'
                ? SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'payment_account_unavailable', __('offline_sync.payment_account_unavailable'))
                : SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'rule_'.$field, $message);
        }
        /** @var ExpenseCategory $category */
        $category = $normalized['category'];
        if ($this->input->categoryHiddenFromMobile($user, $category)) {
            return SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'category_unavailable', __('offline_sync.category_unavailable'));
        }

        if ($file) {
            $check = Validator::make(['receipt' => $file], ['receipt' => SiteExpenseInput::RECEIPT_RULES]);
            if ($check->fails()) {
                return SyncResult::failed(SyncError::VALIDATION_ERROR, 'invalid_receipt', $check->errors()->first());
            }
        }

        $path = $file ? $file->store('site-expense-receipts', 'local') : null;
        try {
            $expense = DB::transaction(function () use ($request, $user, $normalized, $file, $path, $capturedAt, $receipt) {
                $year = now()->year;
                $expense = SiteExpense::create($normalized['data'] + [
                    'expense_number' => app(DocumentNumberService::class)->next('site-expense-'.$year, 'SE-'.$year.'-', 'site_expenses', 'expense_number', 6),
                    'submitted_by_user_id' => $user->id,
                    'employee_id' => $receipt->employee_id, 'status' => 'draft',
                    'sync_client_uuid' => $receipt->client_uuid, 'captured_at_client' => $capturedAt,
                ]);
                if ($file && $path) {
                    $expense->receipts()->create(['path' => $path, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 191),
                        'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $user->id]);
                }
                ActivityLog::record($request, 'Site Expenses', 'Created draft (offline sync)', '[SiteExpense #'.$expense->id.'] '.$expense->expense_number
                    .' · captured '.$capturedAt->format('Y-m-d H:i').', received '.now()->format('Y-m-d H:i').($path ? ', receipt attached' : ''));

                return $expense;
            }, 1);
        } catch (QueryException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if (str_contains(strtolower($exception->getMessage()), 'se_sync_uuid_unique')) {
                // Belt and braces: the receipt lock already serialises this; report the existing document rather than a duplicate.
                $existing = SiteExpense::withoutGlobalScopes()->where('sync_client_uuid', $receipt->client_uuid)->first();
                if ($existing) {
                    return SyncResult::synced($this->record($existing), __('offline_sync.expense_synced', ['number' => $existing->expense_number]));
                }
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return SyncResult::synced($this->record($expense), __('offline_sync.expense_synced', ['number' => $expense->expense_number]),
            ['receipt_required' => (bool) $category->invoice_photo_required && ! $file, 'receipt_attached' => (bool) $file]);
    }

    private function stale(string $model): SyncResult
    {
        return match ($model) {
            ExpenseCategory::class => SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'category_unavailable', __('offline_sync.category_unavailable')),
            Supplier::class => SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'supplier_unavailable', __('offline_sync.supplier_unavailable')),
            Project::class, Site::class => SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'project_unavailable', __('offline_sync.project_unavailable')),
            default => SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'reference_unavailable', __('offline_sync.project_unavailable')),
        };
    }

    private function record(SiteExpense $expense): array
    {
        return ['type' => 'site_expense', 'id' => $expense->id, 'reference' => $expense->expense_number, 'status' => $expense->status,
            'total_amount' => (string) $expense->total_amount, 'url' => route('admin.site-expenses.show', $expense)];
    }
}
