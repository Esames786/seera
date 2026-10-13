<?php

namespace App\Http\Controllers\Admin\Equipment;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EquipmentAsset;
use App\Models\EquipmentDocument;
use App\Models\EquipmentFuelLog;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Services\Equipment\EquipmentAssignmentService;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Support\SaveAction;
use App\Support\Workspace\DocumentActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Equipment & Vehicles register, profile and connected workspace (Phase 1).
 * Reads never write; assignment, fuel, maintenance, documents and accounting
 * are explicit actions on the operations controller.
 */
class EquipmentAssetController extends Controller
{
    public function __construct(private readonly EquipmentAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'project' => ['nullable', 'integer'], 'type' => ['nullable', Rule::in(array_keys(EquipmentAsset::TYPES))], 'status' => ['nullable', Rule::in(array_keys(EquipmentAsset::STATUSES))], 'search' => ['nullable', 'string', 'max:80']]);
        $assets = $this->filtered($request)->with(['project', 'site', 'operator'])->orderBy('asset_code')->paginate(15)->withQueryString();
        $counts = EquipmentAsset::query()->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned, SUM(CASE WHEN status = 'under_maintenance' THEN 1 ELSE 0 END) as maintenance, SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")->first();

        return view('admin.equipment.index', [
            'assets' => $assets,
            'counts' => $counts,
            'projects' => Project::orderBy('name')->get(['id', 'code', 'name']),
            'expiringSoon' => EquipmentAsset::query()->where(fn ($q) => $q->whereDate('registration_expiry_date', '<=', today()->addDays(60))->orWhereDate('insurance_expiry_date', '<=', today()->addDays(60)))->count(),
            'dueService' => EquipmentMaintenanceRecord::query()->where('status', 'completed')->whereNotNull('next_service_date')->whereDate('next_service_date', '<=', today()->addDays(30))->distinct('equipment_asset_id')->count('equipment_asset_id'),
        ]);
    }

    /** Equipment Register as CSV (same scope and filters as the list). */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with(['project', 'site', 'operator'])->orderBy('asset_code')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Asset Code', 'Name', 'Type', 'Category', 'Make', 'Model', 'Year', 'Registration', 'Serial / Chassis', 'Ownership', 'Status', 'Project', 'Site', 'Operator', 'Meter', 'Registration expiry', 'Insurance expiry', 'Tracker']);
            foreach ($rows as $asset) {
                fputcsv($out, [$asset->asset_code, $asset->name, $asset->typeLabel(), $asset->category, $asset->make, $asset->model, $asset->year, $asset->registration_number,
                    $asset->serial_number ?: $asset->chassis_number, EquipmentAsset::OWNERSHIP[$asset->ownership_type] ?? $asset->ownership_type, $asset->statusLabel(),
                    $asset->project?->code, $asset->site?->name, $asset->operator?->name, $asset->meterLabel(), $asset->registration_expiry_date?->toDateString(), $asset->insurance_expiry_date?->toDateString(),
                    $asset->tracker_device_id ? ($asset->tracker_provider ?: 'tracker').' '.$asset->tracker_device_id.' (not connected)' : 'Not connected']);
            }
            fclose($out);
        }, 'equipment-register-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function create(): View
    {
        return view('admin.equipment.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = 'available';
        $data['created_by'] = $request->user()->id;
        $asset = EquipmentAsset::create($data);
        ActivityLog::record($request, 'Equipment', 'Created equipment', '[Equipment #'.$asset->id.'] '.$asset->asset_code.' · '.$asset->name.' ('.$asset->typeLabel().')');

        return $this->saved($request, $asset, 'Equipment '.$asset->asset_code.' created.');
    }

    /** Connected workspace: identity header, assignment, history, fuel, maintenance, costs, documents, tracking readiness, activity. */
    public function show(Request $request, EquipmentAsset $equipment): View
    {
        $request->validate(['page_assign' => ['nullable', 'integer', 'min:1'], 'page_fuel' => ['nullable', 'integer', 'min:1'], 'page_maint' => ['nullable', 'integer', 'min:1'], 'record' => ['prohibited'], 'project_id' => ['prohibited']]);
        $user = $request->user();
        $equipment->load(['project', 'site', 'operator', 'activeAssignment.project', 'activeAssignment.site', 'activeAssignment.operator', 'creator']);
        $canFinance = $user->hasPermission('Financial Reports', 'view');
        $fuel = $equipment->fuelLogs()->with(['project', 'site', 'supplier', 'category', 'journalEntry', 'supplierBill', 'document'])->paginate(10, ['*'], 'page_fuel')->withQueryString()->fragment('fuel');
        $maintenance = $equipment->maintenanceRecords()->with(['project', 'site', 'supplier', 'category', 'journalEntry', 'supplierBill', 'document'])->paginate(10, ['*'], 'page_maint')->withQueryString()->fragment('maintenance');
        $costs = null;
        if ($canFinance) {
            $sum = fn ($query) => $query->selectRaw("COALESCE(SUM(CASE WHEN accounting_status = 'posted' THEN total_amount ELSE 0 END), 0) as posted, COALESCE(SUM(CASE WHEN accounting_status <> 'posted' THEN total_amount ELSE 0 END), 0) as unposted, COUNT(*) as entries")->first();
            $costs = ['fuel' => $sum(EquipmentFuelLog::where('equipment_asset_id', $equipment->id)), 'maintenance' => $sum(EquipmentMaintenanceRecord::where('equipment_asset_id', $equipment->id)->where('status', '!=', 'cancelled'))];
        }
        $nextService = EquipmentMaintenanceRecord::where('equipment_asset_id', $equipment->id)->where('status', 'completed')->where(fn ($q) => $q->whereNotNull('next_service_date')->orWhereNotNull('next_service_meter'))->orderByDesc('service_date')->orderByDesc('id')->first();

        return view('admin.equipment.show', $this->formOptions() + [
            'asset' => $equipment,
            'assignmentHistory' => $equipment->assignments()->with(['project', 'site', 'operator', 'assigner'])->paginate(10, ['*'], 'page_assign')->withQueryString()->fragment('history'),
            'fuelLogs' => $fuel,
            'maintenance' => $maintenance,
            'documents' => $equipment->documents()->with('uploader')->get(),
            'costs' => $costs,
            'nextService' => $nextService,
            'openMaintenance' => EquipmentMaintenanceRecord::where('equipment_asset_id', $equipment->id)->where('status', 'open')->count(),
            'canFinance' => $canFinance,
            'canEdit' => $user->hasPermission('Equipment', 'edit'),
            'canAssign' => $user->hasPermission('Equipment', 'process'),
            'canRecord' => $user->hasPermission('Equipment', 'create'),
            'canPost' => $user->hasPermission('Equipment', 'post'),
            'canViewJournal' => $user->hasPermission('Journal Entries', 'view'),
            'canViewBill' => $user->hasPermission('Accounts Payable', 'view'),
            'activity' => DocumentActivity::latest($user, ['[Equipment #'.$equipment->id.']'], ['Equipment'], 15),
        ]);
    }

    public function edit(EquipmentAsset $equipment): View
    {
        return view('admin.equipment.edit', ['asset' => $equipment] + $this->formOptions());
    }

    public function update(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $this->validated($request, $equipment);
        $equipment = DB::transaction(function () use ($request, $equipment, $data) {
            // Re-read assignment and meter state under the same mutex as operational actions.
            $equipment = EquipmentAsset::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('status', $data)) {
                if ($data['status'] === 'assigned' && ! $equipment->current_assignment_id) {
                    throw ValidationException::withMessages(['status' => 'Use Assign on the equipment page to put an asset on a project; the Assigned status follows the assignment.']);
                }
                if ($data['status'] !== 'assigned' && $data['status'] !== 'under_maintenance' && $equipment->current_assignment_id && $data['status'] !== $equipment->status) {
                    throw ValidationException::withMessages(['status' => 'Release the active assignment before changing the status of an assigned asset.']);
                }
            }
            $meter = $data['current_meter'] ?? null;
            unset($data['current_meter']);
            $equipment->update($data);
            if ($meter !== null) {
                $this->assignments->applyMeter($equipment, (float) $meter, $request->boolean('meter_correction'), $request->user(), 'profile edit');
            }
            ActivityLog::record($request, 'Equipment', 'Updated equipment', '[Equipment #'.$equipment->id.'] '.$equipment->asset_code.' · '.$equipment->name);

            return $equipment;
        }, 3);

        return $this->saved($request, $equipment, 'Equipment '.$equipment->asset_code.' updated.');
    }

    // ------------------------------------------------------------------ helpers

    private function filtered(Request $request)
    {
        return EquipmentAsset::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('asset_code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('registration_number', 'like', "%{$s}%")->orWhere('serial_number', 'like', "%{$s}%"));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('asset_type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('project'), fn ($q) => $q->where('current_project_id', $request->integer('project')));
    }

    private function validated(Request $request, ?EquipmentAsset $asset = null): array
    {
        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\.\/]*$/', Rule::unique('equipment_assets', 'asset_code')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:150'],
            'asset_type' => ['required', Rule::in(array_keys(EquipmentAsset::TYPES))],
            'category' => ['nullable', 'string', 'max:80'],
            'make' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'chassis_number' => ['nullable', 'string', 'max:60'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'ownership_type' => ['required', Rule::in(array_keys(EquipmentAsset::OWNERSHIP))],
            'acquisition_date' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => [$asset ? 'required' : 'nullable', Rule::in(array_keys(EquipmentAsset::STATUSES))],
            'meter_type' => ['required', Rule::in(array_keys(EquipmentAsset::METER_TYPES))],
            'current_meter' => ['nullable', 'numeric', 'min:0'],
            'meter_correction' => ['nullable', 'boolean'],
            'registration_expiry_date' => ['nullable', 'date'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'tracker_provider' => ['nullable', 'string', 'max:80'],
            'tracker_device_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'current_project_id' => ['prohibited'], 'current_site_id' => ['prohibited'], 'project_id' => ['prohibited'],
        ], ['asset_code.regex' => 'Asset code: letters, digits, "-", "." or "/" only.', 'asset_code.unique' => 'This asset code is already used by another asset.']);
        $data['asset_code'] = strtoupper($data['asset_code']);
        if ($data['asset_type'] !== 'vehicle') {
            $data['registration_number'] = null;
            $data['chassis_number'] = null;
        }
        unset($data['meter_correction']);
        if (! $asset) {
            unset($data['status']);
        }

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'types' => EquipmentAsset::TYPES, 'ownership' => EquipmentAsset::OWNERSHIP, 'statuses' => EquipmentAsset::STATUSES, 'meterTypes' => EquipmentAsset::METER_TYPES,
            'projects' => Project::where('status', '!=', 'inactive')->orderBy('name')->get(['id', 'code', 'name']),
            'sites' => Site::where('status', 'active')->orderBy('name')->get(['id', 'name', 'project_id']),
            'employees' => Employee::where('status', 'active')->orderBy('first_name')->get(['id', 'employee_code', 'first_name', 'last_name']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'chart_of_account_id']),
            'cashAccounts' => SiteExpenseAccountingService::paymentAccounts('Cash')->get(),
            'bankAccounts' => SiteExpenseAccountingService::paymentAccounts('Bank')->get(),
            'documentTypes' => EquipmentDocument::TYPES,
            'maintenanceTypes' => EquipmentMaintenanceRecord::TYPES,
        ];
    }

    private function saved(Request $request, EquipmentAsset $asset, string $message): RedirectResponse
    {
        $origin = SaveAction::returnTo($request);

        return SaveAction::redirect($request, [
            'stay' => route('admin.equipment.edit', [$asset, 'return_to' => $origin]),
            'close' => $origin ?? route('admin.equipment.show', $asset),
            'new' => route('admin.equipment.create'),
        ])->with('status', $message);
    }
}
