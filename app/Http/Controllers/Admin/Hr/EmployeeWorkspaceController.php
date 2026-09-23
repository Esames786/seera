<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Support\EmployeeWorkspacePanels as Panels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeWorkspaceController extends Controller
{
    private function authorizePanel(Request $request, string $panel, string $action): void
    {
        abort_unless(isset(Panels::PANELS[$panel]), 404);
        abort_unless($request->user()->hasPermission(Panels::PANELS[$panel][1], $action), 403);
    }

    public function panel(Request $request, Employee $employee, string $panel): JsonResponse
    {
        $this->authorizePanel($request, $panel, 'view');
        $request->validate(['record' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1']]);
        $record = $request->filled('record') ? Panels::query($employee, $panel)->findOrFail($request->integer('record')) : null;
        if ($panel === 'account') {
            $record = Panels::query($employee, $panel)->first();
        }
        $module = Panels::PANELS[$panel][1];
        $canCreate = $panel !== 'payroll-history' && $request->user()->hasPermission($module, $panel === 'shifts' ? 'edit' : 'create');
        $canEdit = $record && Panels::editable($panel, $record) && $request->user()->hasPermission($module, 'edit');
        $canSave = $record ? $canEdit : $canCreate;
        $rows = Panels::query($employee, $panel)->latest('id')->paginate(10);

        return response()->json(['html' => view('admin.hr.employees._workspace-panel', [
            'employee' => $employee, 'panel' => $panel, 'record' => $record,
            'title' => Panels::PANELS[$panel][0], 'module' => $module,
            'fields' => Panels::fields($employee, $panel, $record), 'rows' => $rows,
            'columns' => Panels::columns($panel), 'canCreate' => $canCreate,
            'canSave' => $canSave,
        ])->render()]);
    }

    public function save(Request $request, Employee $employee, string $panel): JsonResponse
    {
        return DB::transaction(function () use ($request, $employee, $panel) {
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            return $this->saveLocked($request, $employee, $panel);
        });
    }

    private function saveLocked(Request $request, Employee $employee, string $panel): JsonResponse
    {
        $request->validate(['record_id' => ['nullable', 'integer']]);
        $this->authorizePanel($request, $panel, $panel === 'shifts' || $request->filled('record_id') ? 'edit' : 'create');
        abort_if($panel === 'payroll-history', 403);
        abort_if($request->filled('employee_id') && $request->integer('employee_id') !== $employee->id, 403);
        $record = $request->filled('record_id') ? Panels::query($employee, $panel)->lockForUpdate()->findOrFail($request->integer('record_id')) : null;
        abort_if($record && ! Panels::editable($panel, $record), 403, 'This historical or approved record is read-only.');
        $request->merge(['employee_id' => $employee->id]);

        if ($panel === 'shifts') {
            $this->saveShift($request, $employee, $record);
        } else {
            $controller = app(Panels::PANELS[$panel][3]);
            if (in_array($panel, ['leaves', 'overtime'], true)) {
                abort_unless($request->input('status', 'pending') === 'pending', 403, 'Approval is a separate action.');
                $request->merge(['status' => 'pending']);
            }
            if ($panel === 'attendance') {
                $request->merge(['project_id' => $employee->project_id, 'site_id' => $employee->site_id, 'source' => $record?->source ?? 'manual']);
            }
            if ($panel === 'overtime' && $request->filled('attendance_record_id')) {
                abort_unless(AttendanceRecord::whereKey($request->integer('attendance_record_id'))->where('employee_id', $employee->id)->exists(), 403);
            }
            if ($panel === 'account') {
                abort_unless($request->user()->hasPermission('HR', 'view'), 403);
                abort_if(! $record && $employee->user_id, 409, 'This employee already has an account.');
                $request->validate(['password' => ['nullable', 'string', 'min:8', 'max:72']]);
                $defaults = $employee->only(['department_id', 'designation_id', 'branch_id', 'project_id', 'site_id', 'contract_type', 'employee_classification', 'iqama_number']);
                $request->merge($defaults + [
                    'joining_date' => $employee->joining_date?->toDateString(), 'iqama_expiry_date' => $employee->iqama_expiry_date?->toDateString(),
                    'employee_id' => $employee->employee_code, 'source_employee_id' => $employee->id,
                ]);
            }
            // Controllers retain the accepted validation, calculation, audit and
            // file cleanup logic. Module permissions and ownership are checked above.
            $record ? $controller->update($request, $record) : $controller->store($request);
        }

        return response()->json(['message' => __('Saved successfully. This section is up to date.')]);
    }

    public function action(Request $request, Employee $employee, string $panel, int $record, string $action): JsonResponse
    {
        return DB::transaction(function () use ($request, $employee, $panel, $record, $action) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            return $this->actionLocked($request, $employee, $panel, $record, $action);
        });
    }

    private function actionLocked(Request $request, Employee $employee, string $panel, int $record, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['approve', 'reject', 'delete'], true), 404);
        $this->authorizePanel($request, $panel, $action === 'delete' ? 'delete' : $action);
        $model = Panels::query($employee, $panel)->lockForUpdate()->findOrFail($record);
        abort_unless(Panels::editable($panel, $model), 403);
        if ($action === 'delete') {
            abort_unless(in_array($panel, ['attendance', 'leaves', 'overtime', 'eosb', 'shifts'], true), 403);
        } else {
            abort_unless(in_array($panel, ['leaves', 'overtime', 'eosb'], true) && ($action !== 'reject' || $panel === 'leaves'), 403);
        }
        if ($panel === 'shifts') {
            $model->delete();
            ActivityLog::record($request, 'HR', 'Removed employee shift assignment', $employee->name);
        } else {
            $method = $action === 'delete' ? 'destroy' : $action;
            app(Panels::PANELS[$panel][3])->{$method}($request, $model);
        }

        return response()->json(['message' => __('Action completed.')]);
    }

    private function saveShift(Request $request, Employee $employee, ?EmployeeShiftAssignment $record): void
    {
        $data = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'status' => ['required', 'in:active,inactive'],
        ]);
        DB::transaction(function () use ($request, $employee, $record, $data) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $overlap = $employee->shiftAssignments()->where('status', 'active')
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->exists();
            if ($data['status'] === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This overlaps an active shift assignment. Close the previous assignment first.']);
            }
            $record ? $record->update($data) : $employee->shiftAssignments()->create($data);
            ActivityLog::record($request, 'HR', 'Saved employee shift assignment', $employee->name);
        });
    }
}
