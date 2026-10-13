<?php

namespace App\Services\Equipment;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\EquipmentAsset;
use App\Models\EquipmentFuelLog;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\DocumentNumberService;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fuel logs and maintenance records: operational entries first, project cost
 * only once Finance posts them. Cash / Bank → one journal (Dr the expense
 * category's account with the assignment's project / site, Dr input VAT,
 * Cr the chosen payment account). Supplier Credit → one draft Supplier Bill
 * that carries the liability through the existing AP runtime; the record
 * reads the bill's accounting state and never posts a second journal.
 * Accounts come from the Expense Category and the Finance payment catalogue;
 * nothing is hard-coded.
 */
class EquipmentCostService
{
    public function __construct(private readonly PostingService $posting, private readonly EquipmentAssignmentService $assignments, private readonly DocumentNumberService $numbers) {}

    /** @param array<string, mixed> $data */
    public function recordFuel(EquipmentAsset $asset, array $data, User $actor): EquipmentFuelLog
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $locked = EquipmentAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $date = $this->date($data['fuel_date'] ?? null, 'fuel_date');
            $quantity = $this->number($data['quantity'] ?? null);
            $rate = $this->number($data['rate'] ?? null);
            if ($quantity === null || $quantity <= 0) {
                $this->invalid('quantity', 'Enter the fuel quantity.');
            }
            if ($rate === null || $rate < 0) {
                $this->invalid('rate', 'Enter the rate per unit.');
            }
            $money = $this->money(round($quantity * $rate, 2), $data);
            $financial = $this->financialFields($data);
            $this->assignments->applyMeter($locked, $this->number($data['meter_reading'] ?? null), (bool) ($data['meter_correction'] ?? false), $actor, 'fuel');
            $active = $locked->activeAssignment()->first();
            $unit = (string) ($data['unit'] ?? 'litre');
            $log = EquipmentFuelLog::create([
                'fuel_number' => $this->numbers->next('equipment-fuel-'.$date->year, 'FUEL-'.$date->year.'-', 'equipment_fuel_logs', 'fuel_number'),
                'equipment_asset_id' => $locked->id, 'equipment_assignment_id' => $active?->id, 'project_id' => $active?->project_id, 'site_id' => $active?->site_id,
                'fuel_date' => $date->toDateString(), 'meter_reading' => $this->number($data['meter_reading'] ?? null),
                'quantity' => round($quantity, 3), 'unit' => in_array($unit, ['litre', 'gallon'], true) ? $unit : 'litre', 'rate' => round($rate, 4),
                'reference_number' => $this->text($data['reference_number'] ?? null, 100), 'notes' => $this->text($data['notes'] ?? null, 1000), 'created_by' => $actor->id,
            ] + $money + $financial);
            $this->assignments->log($locked, $actor, 'Recorded fuel', $log->fuel_number.' · '.number_format($quantity, 3).' '.$log->unit.' · SAR '.number_format((float) $log->total_amount, 2).' · '.$log->payment_type);

            return $log;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordMaintenance(EquipmentAsset $asset, array $data, User $actor): EquipmentMaintenanceRecord
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $locked = EquipmentAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $date = $this->date($data['service_date'] ?? null, 'service_date');
            $type = (string) ($data['maintenance_type'] ?? 'repair');
            if (! array_key_exists($type, EquipmentMaintenanceRecord::TYPES)) {
                $this->invalid('maintenance_type', 'Choose the maintenance type.');
            }
            $description = trim((string) ($data['description'] ?? ''));
            if ($description === '') {
                $this->invalid('description', 'Describe the service or repair.');
            }
            $cost = $this->number($data['cost'] ?? null) ?? 0.0;
            if ($cost < 0) {
                $this->invalid('cost', 'The cost cannot be negative.');
            }
            $money = $this->money(round($cost, 2), $data);
            $financial = $this->financialFields($data, $cost > 0);
            $nextMeter = $this->number($data['next_service_meter'] ?? null);
            $nextDate = ! empty($data['next_service_date']) ? Carbon::parse($data['next_service_date'])->toDateString() : null;
            $this->assignments->applyMeter($locked, $this->number($data['meter_reading'] ?? null), (bool) ($data['meter_correction'] ?? false), $actor, 'maintenance');
            $active = $locked->activeAssignment()->first();
            $record = EquipmentMaintenanceRecord::create([
                'maintenance_number' => $this->numbers->next('equipment-maintenance-'.$date->year, 'MNT-'.$date->year.'-', 'equipment_maintenance_records', 'maintenance_number'),
                'equipment_asset_id' => $locked->id, 'equipment_assignment_id' => $active?->id, 'project_id' => $active?->project_id, 'site_id' => $active?->site_id,
                'service_date' => $date->toDateString(), 'meter_reading' => $this->number($data['meter_reading'] ?? null), 'maintenance_type' => $type, 'description' => mb_substr($description, 0, 2000),
                'next_service_date' => $nextDate, 'next_service_meter' => $nextMeter, 'status' => 'open',
                'reference_number' => $this->text($data['reference_number'] ?? null, 100), 'notes' => $this->text($data['notes'] ?? null, 1000), 'created_by' => $actor->id,
            ] + $money + $financial);
            if (! empty($data['take_out_of_service']) && in_array($locked->status, ['available', 'assigned'], true)) {
                $locked->update(['status' => 'under_maintenance']);
            }
            $this->assignments->log($locked, $actor, 'Recorded maintenance', $record->maintenance_number.' · '.$record->typeLabel().' · SAR '.number_format((float) $record->total_amount, 2));

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function completeMaintenance(EquipmentMaintenanceRecord $record, array $data, User $actor): void
    {
        DB::transaction(function () use ($record, $data, $actor) {
            $locked = EquipmentAsset::whereKey($record->equipment_asset_id)->lockForUpdate()->firstOrFail();
            $record = EquipmentMaintenanceRecord::withoutGlobalScopes()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'open') {
                $this->invalid('maintenance', 'Only an open maintenance record can be completed.');
            }
            $cost = $this->number($data['cost'] ?? null);
            $updates = ['status' => 'completed', 'completed_at' => now()];
            if ($cost !== null) {
                if ($cost < 0) {
                    $this->invalid('cost', 'The cost cannot be negative.');
                }
                $updates += $this->money(round($cost, 2), ['vat_rate' => $data['vat_rate'] ?? $record->vat_rate]);
            }
            $updates += $this->financialFields(array_replace($record->only(['payment_type', 'payment_account_id', 'supplier_id', 'expense_category_id']),
                array_intersect_key($data, array_flip(['payment_type', 'payment_account_id', 'supplier_id', 'expense_category_id']))),
                (float) ($updates['taxable_amount'] ?? $record->taxable_amount) > 0);
            $this->assignments->applyMeter($locked, $this->number($data['meter_reading'] ?? null), (bool) ($data['meter_correction'] ?? false), $actor, 'maintenance completion');
            if ($this->number($data['meter_reading'] ?? null) !== null) {
                $updates['meter_reading'] = $this->number($data['meter_reading']);
            }
            $record->update($updates);
            if ($locked->status === 'under_maintenance' && ! EquipmentMaintenanceRecord::where('equipment_asset_id', $locked->id)->where('status', 'open')->exists()) {
                $locked->update(['status' => $locked->current_assignment_id ? 'assigned' : 'available']);
            }
            $this->assignments->log($locked, $actor, 'Completed maintenance', $record->maintenance_number.' · SAR '.number_format((float) $record->fresh()->total_amount, 2));
        }, 3);
    }

    public function cancelMaintenance(EquipmentMaintenanceRecord $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor) {
            $locked = EquipmentAsset::whereKey($record->equipment_asset_id)->lockForUpdate()->firstOrFail();
            $record = EquipmentMaintenanceRecord::withoutGlobalScopes()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->isFinanciallyLinked()) {
                $this->invalid('maintenance', 'A posted or billed maintenance record cannot be cancelled; correct it through Finance.');
            }
            if ($record->status === 'cancelled') {
                return;
            }
            $record->update(['status' => 'cancelled']);
            if ($locked->status === 'under_maintenance' && ! EquipmentMaintenanceRecord::where('equipment_asset_id', $locked->id)->where('status', 'open')->exists()) {
                $locked->update(['status' => $locked->current_assignment_id ? 'assigned' : 'available']);
            }
            $this->assignments->log($locked, $actor, 'Cancelled maintenance', $record->maintenance_number);
        }, 3);
    }

    /**
     * Finance: post a fuel log or maintenance record to accounting (idempotent,
     * record row locked). Cash / Bank → posted journal; Supplier Credit → one
     * draft supplier bill. A failure keeps the record with the reason.
     */
    public function post(EquipmentFuelLog|EquipmentMaintenanceRecord $record, User $actor): void
    {
        $class = $record::class;
        $id = $record->id;
        try {
            DB::transaction(function () use ($class, $id, $actor) {
                $record = $class::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($record->journal_entry_id || $record->supplier_bill_id) {
                    $this->syncLocked($record);

                    return;
                }
                if ($record instanceof EquipmentMaintenanceRecord && $record->status !== 'completed') {
                    $this->invalid('posting', 'Complete the maintenance record before posting its cost; a cancelled record is never posted.');
                }
                if ((float) $record->taxable_amount <= 0) {
                    $this->invalid('posting', 'Nothing to post: the record has no cost.');
                }
                [$account, $costCenter] = $this->mapping($record);
                $asset = EquipmentAsset::withoutGlobalScopes()->find($record->equipment_asset_id);
                if ($record->payment_type === 'Supplier Credit') {
                    $supplier = Supplier::withoutGlobalScopes()->find($record->supplier_id);
                    if (! $supplier || $supplier->status !== 'active') {
                        $this->invalid('posting', 'Choose an active supplier before Finance can generate the bill.');
                    }
                    $bill = SupplierBill::create([
                        'approval_mode' => 'runtime', 'requested_by' => $record->created_by, 'supplier_id' => $supplier->id,
                        'bill_number' => $record->number(), 'bill_date' => $record->documentDate(), 'reference_number' => $record->reference_number,
                        'project_id' => $record->project_id, 'site_id' => $record->site_id, 'cost_center_id' => $costCenter?->id,
                        'taxable_amount' => $record->taxable_amount, 'vat_rate' => $record->vat_rate, 'vat_amount' => $record->vat_amount,
                        'total_amount' => $record->total_amount, 'paid_amount' => 0, 'balance_amount' => $record->total_amount, 'status' => 'draft',
                        'notes' => 'Equipment '.($record instanceof EquipmentFuelLog ? 'fuel' : 'maintenance').' '.$record->number().' for '.($asset?->label() ?? 'asset').'. Finance approval/posting required.',
                    ]);
                    $bill->lines()->create([
                        'description' => $record->description(), 'expense_category_id' => $record->expense_category_id, 'chart_of_account_id' => $account->id,
                        'quantity' => 1, 'unit_price' => $record->taxable_amount, 'taxable_amount' => $record->taxable_amount, 'vat_rate' => $record->vat_rate,
                        'vat_amount' => $record->vat_amount, 'total_amount' => $record->total_amount, 'cost_center_id' => $costCenter?->id,
                    ]);
                    $record->update(['supplier_bill_id' => $bill->id, 'accounting_status' => $record::ACC_BILL_PENDING, 'posting_error' => null]);
                    if ($asset) {
                        $this->assignments->log($asset, $actor, 'Supplier bill created', $record->number().' → draft bill '.$bill->bill_number.' (Finance approval / posting required)');
                    }

                    return;
                }
                $credit = SiteExpenseAccountingService::paymentAccounts($record->payment_type)->find($record->payment_account_id);
                if (! $credit) {
                    $this->invalid('posting', 'The selected payment account is not an active permitted '.$record->payment_type.' account.');
                }
                $entry = $this->posting->postEquipmentCost($record, $account, $credit, $costCenter?->id, $actor->id);
                $record->update(['journal_entry_id' => $entry->id, 'accounting_status' => $record::ACC_POSTED, 'posted_at' => now(), 'posting_error' => null]);
                if ($asset) {
                    $this->assignments->log($asset, $actor, 'Posted equipment cost', $record->number().' → '.$entry->journal_number.' · SAR '.number_format((float) $record->total_amount, 2));
                }
            }, 3);
        } catch (Throwable $error) {
            $message = $error instanceof ValidationException
                ? implode(' ', array_merge(...array_values($error->errors())))
                : 'Accounting could not complete. Finance can review the expense category and payment account and retry; the record is retained.';
            Log::warning('Equipment cost posting failed', ['record' => $class, 'id' => $id, 'exception_class' => $error::class]);
            DB::transaction(function () use ($class, $id, $message) {
                $record = $class::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $record->journal_entry_id && ! $record->supplier_bill_id) {
                    $record->update(['accounting_status' => $record::ACC_FAILED, 'posting_error' => mb_substr($message, 0, 2000)]);
                }
            }, 3);
            if ($error instanceof ValidationException) {
                throw $error;
            }
        }
    }

    /** Called after a journal or linked bill changes state (never on a GET). */
    public function sync(string $class, int $id): void
    {
        DB::transaction(function () use ($class, $id) {
            $record = $class::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
            if ($record) {
                $this->syncLocked($record);
            }
        }, 3);
    }

    /** Records linked to a supplier bill (for the bill-status hook). @return array<int, array{0: string, 1: int}> */
    public static function recordsForBill(int $billId): array
    {
        $rows = [];
        foreach ([EquipmentFuelLog::class, EquipmentMaintenanceRecord::class] as $class) {
            foreach ($class::withoutGlobalScopes()->where('supplier_bill_id', $billId)->pluck('id') as $id) {
                $rows[] = [$class, (int) $id];
            }
        }

        return $rows;
    }

    /** @return array{0: ChartOfAccount, 1: ?CostCenter} */
    public function mapping(EquipmentFuelLog|EquipmentMaintenanceRecord $record): array
    {
        $category = ExpenseCategory::find($record->expense_category_id);
        $account = $category ? ChartOfAccount::find($category->chart_of_account_id) : null;
        if (! $category || $category->status !== 'active' || ! $account || $account->status !== 'active' || $account->account_type !== 'expense') {
            $this->invalid('posting', "Expense category '".($category?->name ?? 'not set')."' has no active expense account configured. Set the account on the Expense Category (Master Setup) before posting.");
        }
        if (in_array($account->id, $this->posting->vatAccountIds(), true)) {
            $this->invalid('posting', 'Equipment cost must not post to a VAT control account.');
        }
        $centers = $record->project_id ? CostCenter::where('type', 'project')->where('linked_id', $record->project_id)->where('status', 'active')->get() : collect();
        if ($centers->count() > 1 || ($account->cost_center_required && $centers->count() !== 1)) {
            $this->invalid('posting', 'Account '.$account->label().' requires one active project cost center; '.($record->project_id ? 'configure it for the assigned project' : 'the asset had no project assignment when this cost was recorded').'.');
        }

        return [$account, $centers->first()];
    }

    private function syncLocked(EquipmentFuelLog|EquipmentMaintenanceRecord $record): void
    {
        if ($record->journal_entry_id) {
            $entry = JournalEntry::withoutGlobalScopes()->find($record->journal_entry_id);
            $record->update(['accounting_status' => $entry?->status === 'posted' ? $record::ACC_POSTED : ($entry && $entry->status !== 'cancelled' ? $record::ACC_NOT_POSTED : $record::ACC_FAILED),
                'posted_at' => $entry?->status === 'posted' ? ($record->posted_at ?? $entry->posted_at) : null,
                'posting_error' => $entry && $entry->status === 'cancelled' ? 'Journal '.$entry->journal_number.' was cancelled in Accounting; Finance must review.' : null]);

            return;
        }
        if ($record->supplier_bill_id) {
            $bill = SupplierBill::withoutGlobalScopes()->with('journalEntry')->find($record->supplier_bill_id);
            $posted = $bill?->journalEntry?->status === 'posted';
            $record->update(['accounting_status' => $posted ? $record::ACC_POSTED : ($bill && $bill->status === 'cancelled' ? $record::ACC_FAILED : $record::ACC_BILL_PENDING),
                'posted_at' => $posted ? ($record->posted_at ?? $bill->journalEntry->posted_at) : null,
                'posting_error' => $bill && $bill->status === 'cancelled' ? 'Supplier bill '.$bill->bill_number.' was cancelled; record a new cost if still owed.' : null]);
        }
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{taxable_amount: float, vat_rate: float, vat_amount: float, total_amount: float} */
    private function money(float $taxable, array $data): array
    {
        $vatRate = (float) ($this->number($data['vat_rate'] ?? null) ?? 0);
        if (! in_array($vatRate, [0.0, 15.0], true)) {
            $this->invalid('vat_rate', 'VAT rate must be 0 or 15.');
        }
        $vat = round($taxable * $vatRate / 100, 2);

        return ['taxable_amount' => $taxable, 'vat_rate' => $vatRate, 'vat_amount' => $vat, 'total_amount' => round($taxable + $vat, 2)];
    }

    /** @return array{payment_type: string, payment_account_id: ?int, supplier_id: ?int, expense_category_id: ?int} */
    private function financialFields(array $data, bool $required = true): array
    {
        $type = (string) ($data['payment_type'] ?? 'Cash');
        if (! in_array($type, EquipmentFuelLog::PAYMENT_TYPES, true)) {
            $this->invalid('payment_type', 'Choose Cash, Bank or Supplier Credit.');
        }
        $categoryId = $this->optionalId($data['expense_category_id'] ?? null);
        if (($required || $categoryId) && (! $categoryId || ! ExpenseCategory::whereKey($categoryId)->where('status', 'active')->exists())) {
            $this->invalid('expense_category_id', 'Choose an active expense category (its account decides the posting).');
        }
        $supplierId = $this->optionalId($data['supplier_id'] ?? null);
        $accountId = $this->optionalId($data['payment_account_id'] ?? null);
        if ($type === 'Supplier Credit') {
            if (($required || $supplierId) && (! $supplierId || ! Supplier::whereKey($supplierId)->where('status', 'active')->exists())) {
                $this->invalid('supplier_id', 'Choose the active supplier that will bill this cost.');
            }
            $accountId = null;
        } else {
            if (($required || $accountId) && (! $accountId || ! SiteExpenseAccountingService::paymentAccounts($type)->whereKey($accountId)->exists())) {
                $this->invalid('payment_account_id', 'Choose the active '.$type.' account the cost was paid from.');
            }
        }

        return ['payment_type' => $type, 'payment_account_id' => $accountId, 'supplier_id' => $supplierId, 'expense_category_id' => $categoryId];
    }

    private function date(mixed $value, string $field): Carbon
    {
        try {
            $date = Carbon::parse($value ?: today())->startOfDay();
        } catch (Throwable) {
            $this->invalid($field, 'Enter a valid date.');
        }
        if ($date->gt(today())) {
            $this->invalid($field, 'The date cannot be in the future.');
        }

        return $date;
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
