<?php

namespace App\Services\Accounting;

use App\Models\ActivityLog;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierAdvanceMovement;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\UserAccessScopeService;
use App\Support\SettlementReplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Explicit recoverable payments, not VAT documents and not automatic offsets. */
final class SupplierRecoverables
{
    public function __construct(private readonly PostingService $posting, private readonly UserAccessScopeService $scope) {}

    public function authorize(User $actor): void
    {
        abort_unless($actor->status === 'active' && $actor->hasPermission('Accounts Payable', 'view')
            && $actor->hasPermission('Accounts Payable', 'approve') && $actor->hasPermission('Accounts Payable', 'process'), 403);
    }

    public function scoped(User $actor)
    {
        $query = SupplierAdvance::withoutGlobalScopes();
        $this->scope->apply($query, new SupplierAdvance, $actor);

        return $query;
    }

    /** Company Finance must explicitly map a dedicated advance asset; never create accounts silently. */
    public function assetAccount(): ChartOfAccount
    {
        $rules = AutomaticPostingRule::where('source_module', 'Supplier Advance')->where('trigger_event', 'Payment Recorded')->where('status', 'active')->get();
        $account = $rules->count() === 1 ? ChartOfAccount::find($rules->first()->debit_account_id) : null;
        $excluded = ChartOfAccount::whereIn('account_code', [PostingService::CASH, PostingService::BANK, PostingService::RECEIVABLE, PostingService::INPUT_VAT, PostingService::INVENTORY_ASSET])->get();
        $excludedIds = $excluded->flatMap(fn ($a) => [$a->id, ...$a->descendantIds()])->all();
        if (! $account || $account->status !== 'active' || $account->account_type !== 'asset' || $account->vat_applicable
            || $account->cost_center_required || in_array($account->id, $excludedIds, true)
            || ! $rules->first()->auto_post || $rules->first()->approval_required) {
            $this->refuse('mapping', __('supplier_advances.mapping_required'));
        }

        return $account;
    }

    public function record(Supplier $supplier, array $input, User $actor): SupplierAdvance
    {
        $this->authorize($actor);
        $data = Validator::make($input, $this->commonRules('payment_date') + [
            'purpose' => ['required', Rule::in(SupplierAdvance::PURPOSES)],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'recoverable_confirmed' => ['accepted'],
            'project_id' => ['nullable', 'integer'], 'site_id' => ['nullable', 'integer'],
            'payment_account_id' => ['required', 'integer'],
        ])->validate();

        return DB::transaction(function () use ($supplier, $data, $actor) {
            $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            // Serializing on the supplier also makes same-supplier submission retries safe.
            $projectId = ! empty($data['project_id']) ? (int) $data['project_id'] : null;
            $siteId = ! empty($data['site_id']) ? (int) $data['site_id'] : null;
            $this->dimensions($projectId, $siteId, $actor);
            $expected = ['supplier_id' => $supplier->id, 'project_id' => $projectId, 'site_id' => $siteId,
                'purpose' => $data['purpose'], 'amount' => $data['amount'], 'payment_date' => $data['payment_date'],
                'payment_account_id' => (int) $data['payment_account_id'], 'reference' => $data['reference'], 'reason' => $data['reason']];
            if ($existing = SupplierAdvance::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->first()) {
                $this->assertVisible($existing, $actor);
                SettlementReplay::assertMatches($existing, $expected, 'payment_date');

                return $existing;
            }
            if ($supplier->status !== 'active') {
                $this->refuse('supplier', __('supplier_advances.inactive_supplier'));
            }
            $asset = $this->assetAccount();
            $payment = $this->paymentAccount((int) $data['payment_account_id'], $supplier);
            $advance = SupplierAdvance::create($expected + ['asset_account_id' => $asset->id, 'idempotency_key' => $data['idempotency_key'], 'created_by' => $actor->id]);
            $entry = $this->posting->postSupplierRecoverable($advance, null, $asset->id, $payment->id, $actor->id);
            $advance->update(['journal_entry_id' => $entry->id]);
            $this->log($actor, 'Supplier advance posted', $advance, $advance->amount);

            return $advance->fresh();
        });
    }

    public function settle(SupplierAdvance $advance, string $kind, array $input, User $actor): SupplierAdvanceMovement
    {
        $this->authorize($actor);
        abort_unless(in_array($kind, ['apply', 'refund'], true), 404);
        $data = Validator::make($input, $this->commonRules('movement_date') + ($kind === 'apply'
            ? ['supplier_bill_id' => ['required', 'integer']]
            : ['payment_account_id' => ['required', 'integer']]))->validate();

        return DB::transaction(function () use ($advance, $kind, $data, $actor) {
            // Always advance, then bill. The row lock protects shared available funds.
            $source = $this->scoped($actor)->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $expected = ['supplier_advance_id' => $source->id, 'kind' => $kind, 'amount' => $data['amount'],
                'movement_date' => $data['movement_date'], 'reference' => $data['reference'],
                'supplier_bill_id' => $kind === 'apply' ? (int) $data['supplier_bill_id'] : null,
                'payment_account_id' => $kind === 'refund' ? (int) $data['payment_account_id'] : null];
            if ($existing = SupplierAdvanceMovement::withoutGlobalScopes()->where('idempotency_key', $data['idempotency_key'])->first()) {
                SettlementReplay::assertMatches($existing, $expected, 'movement_date');

                return $existing;
            }
            $this->assertPosted($source);
            if ($data['movement_date'] < $source->payment_date->toDateString()) {
                $this->refuse('movement_date', __('supplier_advances.invalid_date'));
            }
            if ((float) $data['amount'] > $source->available() + 0.004) {
                $this->refuse('amount', __('supplier_advances.exceeds_available'));
            }
            $bill = null;
            if ($kind === 'apply') {
                $query = SupplierBill::withoutGlobalScopes();
                $this->scope->apply($query, new SupplierBill, $actor);
                $bill = $query->whereKey($data['supplier_bill_id'])->lockForUpdate()->firstOrFail();
                if ($bill->supplier_id !== $source->supplier_id || ! $bill->isPayable()
                    || (string) $bill->project_id !== (string) $source->project_id || (string) $bill->site_id !== (string) $source->site_id) {
                    $this->refuse('supplier_bill_id', __('supplier_advances.invalid_bill'));
                }
                if ($data['movement_date'] < $bill->bill_date->toDateString()) {
                    $this->refuse('movement_date', __('supplier_advances.invalid_date'));
                }
                if ((float) $data['amount'] > (float) $bill->balance_amount + 0.004) {
                    $this->refuse('amount', __('supplier_advances.exceeds_bill'));
                }
                // Use the ORIGINAL posted bill account, even if the supplier mapping has changed.
                $credits = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $bill->journal_entry_id)
                    ->where('credit', $bill->total_amount)->whereHas('account', fn ($q) => $q->where('account_type', 'liability'))->get();
                if ($credits->count() !== 1 || abs((float) $credits->first()->credit - (float) $bill->total_amount) > 0.004) {
                    $this->refuse('supplier_bill_id', __('supplier_advances.invalid_bill'));
                }
                $debitId = $credits->first()->chart_of_account_id;
            } else {
                $debitId = $this->paymentAccount((int) $data['payment_account_id'], $source->supplier)->id;
            }
            $movement = SupplierAdvanceMovement::create($expected + ['idempotency_key' => $data['idempotency_key'], 'created_by' => $actor->id]);
            $entry = $this->posting->postSupplierRecoverable($source, $movement, $debitId, $source->asset_account_id, $actor->id);
            $movement->update(['journal_entry_id' => $entry->id]);
            $bill?->refreshPaymentStatus();
            $this->log($actor, 'Supplier advance '.$kind, $source, $movement->amount);

            return $movement->fresh();
        });
    }

    public function reverse(SupplierAdvance $advance, ?SupplierAdvanceMovement $movement, string $reason, User $actor): void
    {
        $this->authorize($actor);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:500']])->validate();
        DB::transaction(function () use ($advance, $movement, $reason, $actor) {
            $source = $this->scoped($actor)->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $bill = null;
            if ($movement) {
                abort_unless($movement->supplier_advance_id === $source->id, 404);
                if ($movement->supplier_bill_id) {
                    $query = SupplierBill::withoutGlobalScopes();
                    $this->scope->apply($query, new SupplierBill, $actor);
                    $bill = $query->whereKey($movement->supplier_bill_id)->lockForUpdate()->firstOrFail();
                }
                $row = SupplierAdvanceMovement::withoutGlobalScopes()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            } else {
                $row = $source;
                if ($source->movements()->whereNull('reversed_at')->exists()) {
                    $this->refuse('advance', __('supplier_advances.reverse_movements_first'));
                }
            }
            if ($row->reversed_at) {
                return;
            }
            $entry = $row->journalEntry()->withoutGlobalScopes()->firstOrFail();
            if ($entry->status !== 'posted') {
                $this->refuse('advance', __('supplier_advances.not_posted'));
            }
            $reversal = $this->posting->reverseEntry($entry, 'Supplier recoverable reversal: '.$reason, $actor->id);
            // Protect the reversal from generic journal mutations as well.
            $reversal->update(['source_module' => $movement ? 'Supplier Advance Movement' : 'Supplier Advance', 'source_id' => $row->id]);
            $row->update(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason, 'reversal_journal_id' => $reversal->id]);
            $bill?->refreshPaymentStatus();
            $this->log($actor, 'Supplier advance reversal', $source, $row->amount, $reason);
        });
    }

    private function commonRules(string $date): array
    {
        return ['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            $date => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:100'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function paymentAccount(int $id, Supplier $supplier): ChartOfAccount
    {
        $account = ChartOfAccount::whereKey($id)->where('status', 'active')->where('account_type', 'asset')
            ->whereIn('account_code', [PostingService::CASH, PostingService::BANK])->first();
        if (! $account || (($supplier->allowed_payment_types ?? 'Both') !== 'Both'
            && $supplier->allowed_payment_types !== ($account->account_code === PostingService::CASH ? 'Cash' : 'Bank'))) {
            $this->refuse('payment_account_id', __('supplier_advances.invalid_payment'));
        }

        return $account;
    }

    private function dimensions(?int $projectId, ?int $siteId, User $actor): void
    {
        if ($projectId) {
            $q = Project::withoutGlobalScopes();
            $this->scope->apply($q, new Project, $actor);
            $q->whereKey($projectId)->firstOrFail();
        }
        if ($siteId) {
            $q = Site::withoutGlobalScopes();
            $this->scope->apply($q, new Site, $actor);
            $site = $q->whereKey($siteId)->firstOrFail();
            if ($site->project_id !== $projectId) {
                $this->refuse('site_id', __('supplier_advances.invalid_bill'));
            }
        }
        if ($actor->effectiveAccessScope() !== 'company') {
            if (! $projectId || ($actor->effectiveAccessScope() === 'site' && ! $siteId)
                || ! in_array($actor->effectiveAccessScope(), ['project', 'site'], true)) {
                abort(403);
            }
        }
    }

    private function assertVisible(SupplierAdvance $advance, User $actor): void
    {
        $this->scoped($actor)->whereKey($advance->id)->firstOrFail();
    }

    private function assertPosted(SupplierAdvance $source): void
    {
        if ($source->reversed_at || $source->journalEntry?->status !== 'posted') {
            $this->refuse('advance', __('supplier_advances.not_posted'));
        }
    }

    private function log(User $actor, string $action, SupplierAdvance $advance, $amount, string $reason = ''): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'module' => 'Accounting', 'action' => $action,
            'description' => $advance->supplier->name.' / advance #'.$advance->id.' / SAR '.number_format((float) $amount, 2).' / '.$reason]);
    }

    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
