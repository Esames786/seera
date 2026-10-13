<?php

namespace App\Services\Equipment;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project / site assignment of an asset. The asset row is the mutex: two
 * users cannot give one asset two active assignments. Reassigning closes the
 * previous assignment (kept forever) and opens the new one; releasing closes
 * it and makes the asset available. The meter never moves backwards without
 * an explicit correction.
 */
class EquipmentAssignmentService
{
    /** @param array<string, mixed> $data */
    public function assign(EquipmentAsset $asset, array $data, User $actor): EquipmentAssignment
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $locked = EquipmentAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['retired', 'out_of_service'], true)) {
                $this->invalid('equipment', 'A '.strtolower($locked->statusLabel()).' asset cannot be assigned. Change its status first.');
            }
            // Relations are resolved through the acting user's scope: a forged or out-of-scope id is simply not found.
            $project = Project::whereKey((int) ($data['project_id'] ?? 0))->first();
            if (! $project) {
                $this->invalid('project_id', 'Choose a project within your scope.');
            }
            $siteId = $this->optionalId($data['site_id'] ?? null);
            if ($siteId !== null && ! Site::where('project_id', $project->id)->whereKey($siteId)->exists()) {
                $this->invalid('site_id', 'Choose a site that belongs to the selected project and your scope.');
            }
            $operatorId = $this->optionalId($data['operator_employee_id'] ?? null);
            if ($operatorId !== null && ! Employee::whereKey($operatorId)->where('status', 'active')->exists()) {
                $this->invalid('operator_employee_id', 'Choose an active employee within your scope as operator / driver.');
            }
            $from = Carbon::parse($data['assigned_from'] ?? today())->startOfDay();
            if ($from->gt(today())) {
                $this->invalid('assigned_from', 'The assignment date cannot be in the future.');
            }
            $this->applyMeter($locked, $this->optionalNumber($data['meter_reading'] ?? null), (bool) ($data['meter_correction'] ?? false), $actor, 'assignment');

            $previous = EquipmentAssignment::where('equipment_asset_id', $locked->id)->where('is_active', true)->lockForUpdate()->get();
            foreach ($previous as $old) {
                $old->update(['is_active' => false, 'assigned_until' => $from->lt($old->assigned_from) ? $old->assigned_from : $from, 'end_meter' => $locked->current_meter, 'closed_by' => $actor->id]);
            }
            $assignment = EquipmentAssignment::create([
                'equipment_asset_id' => $locked->id, 'project_id' => $project->id, 'site_id' => $siteId, 'operator_employee_id' => $operatorId,
                'assigned_from' => $from->toDateString(), 'start_meter' => $locked->current_meter, 'is_active' => true, 'assigned_by' => $actor->id,
                'notes' => $this->notes($data['notes'] ?? null),
            ]);
            $locked->update([
                'current_project_id' => $project->id, 'current_site_id' => $siteId, 'current_operator_id' => $operatorId, 'current_assignment_id' => $assignment->id,
                'status' => $locked->status === 'under_maintenance' ? 'under_maintenance' : 'assigned',
            ]);
            $this->log($locked, $actor, 'Assigned equipment', $project->code.($siteId ? ' / '.Site::withoutGlobalScopes()->find($siteId)?->name : '').($operatorId ? ' · operator '.Employee::withoutGlobalScopes()->find($operatorId)?->name : '')
                .($previous->isNotEmpty() ? ' (previous assignment closed)' : ''));

            return $assignment;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function release(EquipmentAsset $asset, array $data, User $actor): void
    {
        DB::transaction(function () use ($asset, $data, $actor) {
            $locked = EquipmentAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $active = EquipmentAssignment::where('equipment_asset_id', $locked->id)->where('is_active', true)->lockForUpdate()->get();
            if ($active->isEmpty()) {
                $this->invalid('equipment', 'This asset has no active assignment to release.');
            }
            $until = Carbon::parse($data['assigned_until'] ?? today())->startOfDay();
            if ($until->gt(today())) {
                $this->invalid('assigned_until', 'The release date cannot be in the future.');
            }
            $this->applyMeter($locked, $this->optionalNumber($data['meter_reading'] ?? null), (bool) ($data['meter_correction'] ?? false), $actor, 'release');
            foreach ($active as $assignment) {
                $assignment->update(['is_active' => false, 'assigned_until' => $until->lt($assignment->assigned_from) ? $assignment->assigned_from : $until, 'end_meter' => $locked->current_meter, 'closed_by' => $actor->id,
                    'notes' => trim(($assignment->notes ?? '').' '.($this->notes($data['notes'] ?? null) ?? '')) ?: null]);
            }
            $locked->update(['current_project_id' => null, 'current_site_id' => null, 'current_operator_id' => null, 'current_assignment_id' => null,
                'status' => $locked->status === 'assigned' ? 'available' : $locked->status]);
            $this->log($locked, $actor, 'Released equipment', 'assignment closed on '.$until->toDateString());
        }, 3);
    }

    /**
     * Record a meter reading on the locked asset. A lower reading is refused
     * unless the user explicitly marks it as a correction, which is logged.
     */
    public function applyMeter(EquipmentAsset $locked, ?float $reading, bool $correction, User $actor, string $context): void
    {
        if ($reading === null || $locked->meter_type === 'none') {
            return;
        }
        if ($reading < 0) {
            $this->invalid('meter_reading', 'The meter reading cannot be negative.');
        }
        $current = (float) $locked->current_meter;
        if ($reading < $current && ! $correction) {
            $this->invalid('meter_reading', 'Meter reading '.number_format($reading, 1).' is below the current '.number_format($current, 1).' '.$locked->meterUnit().'. Tick "Meter correction" to record a controlled correction.');
        }
        if (abs($reading - $current) < 0.05) {
            return;
        }
        $locked->update(['current_meter' => $reading]);
        if ($reading < $current) {
            $this->log($locked, $actor, 'Corrected equipment meter', number_format($current, 1).' → '.number_format($reading, 1).' '.$locked->meterUnit().' ('.$context.')');
        }
    }

    public function log(EquipmentAsset $asset, User $actor, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'Equipment', 'action' => $action,
            'description' => '[Equipment #'.$asset->id.'] '.$asset->asset_code.' · '.$detail, 'status' => 'success']);
    }

    private function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function optionalNumber(mixed $value): ?float
    {
        return $value === null || $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function notes(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 1000);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
