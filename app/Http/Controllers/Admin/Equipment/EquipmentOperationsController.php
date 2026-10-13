<?php

namespace App\Http\Controllers\Admin\Equipment;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EquipmentAsset;
use App\Models\EquipmentDocument;
use App\Services\Equipment\EquipmentAssignmentService;
use App\Services\Equipment\EquipmentCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Explicit business actions on one asset: assign / release, fuel, maintenance,
 * documents and the Finance posting. Every child record is resolved through
 * the route asset, so ids from another asset are not found.
 */
class EquipmentOperationsController extends Controller
{
    public function __construct(private readonly EquipmentAssignmentService $assignments, private readonly EquipmentCostService $costs) {}

    public function assign(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $request->validate(['project_id' => ['required', 'integer'], 'site_id' => ['nullable', 'integer'], 'operator_employee_id' => ['nullable', 'integer'],
            'assigned_from' => ['nullable', 'date'], 'meter_reading' => ['nullable', 'numeric'], 'meter_correction' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        try {
            $assignment = $this->assignments->assign($equipment, $data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return $this->back($equipment, 'assignment', $equipment->asset_code.' assigned to '.$assignment->project?->code.($assignment->site ? ' / '.$assignment->site->name : '').'. Previous assignments stay in the history.');
    }

    public function release(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $request->validate(['assigned_until' => ['nullable', 'date'], 'meter_reading' => ['nullable', 'numeric'], 'meter_correction' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        try {
            $this->assignments->release($equipment, $data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return $this->back($equipment, 'assignment', $equipment->asset_code.' released; the asset is available again and the assignment is kept in the history.');
    }

    public function storeFuel(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $request->validate([
            'fuel_date' => ['required', 'date'], 'quantity' => ['required', 'numeric'], 'unit' => ['nullable', Rule::in(['litre', 'gallon'])], 'rate' => ['required', 'numeric'],
            'vat_rate' => ['nullable', 'numeric'], 'meter_reading' => ['nullable', 'numeric'], 'meter_correction' => ['nullable', 'boolean'],
            'payment_type' => ['required', 'string'], 'payment_account_id' => ['nullable', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'expense_category_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'], 'total_amount' => ['nullable'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
            'project_id' => ['prohibited'], 'site_id' => ['prohibited'],
        ]);
        try {
            $log = $this->costs->recordFuel($equipment, $data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
        if ($request->hasFile('receipt')) {
            $document = $this->storeFile($request, $equipment, 'receipt', 'fuel_receipt', $log->fuel_number);
            $log->update(['document_id' => $document->id]);
        }

        return $this->back($equipment, 'fuel', 'Fuel '.$log->fuel_number.' recorded (SAR '.number_format((float) $log->total_amount, 2).' = quantity × rate, calculated on the server). It becomes project cost only when Finance posts it.');
    }

    public function postFuel(Request $request, EquipmentAsset $equipment, int $fuel): RedirectResponse
    {
        $log = $equipment->fuelLogs()->findOrFail($fuel);
        try {
            $this->costs->post($log, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        $log->refresh();
        ActivityLog::record($request, 'Equipment', $log->supplier_bill_id ? 'Requested equipment cost bill' : 'Posted fuel cost', '[Equipment #'.$equipment->id.'] '.$equipment->asset_code.' · '.$log->fuel_number.' · '.$log->accountingLabel());

        return $this->back($equipment, 'fuel', $log->fuel_number.': '.$log->accountingLabel().'.');
    }

    public function storeMaintenance(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'], 'maintenance_type' => ['required', 'string'], 'description' => ['required', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric'], 'vat_rate' => ['nullable', 'numeric'], 'meter_reading' => ['nullable', 'numeric'], 'meter_correction' => ['nullable', 'boolean'],
            'next_service_date' => ['nullable', 'date'], 'next_service_meter' => ['nullable', 'numeric'], 'take_out_of_service' => ['nullable', 'boolean'],
            'payment_type' => ['nullable', 'string'], 'payment_account_id' => ['nullable', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'expense_category_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'], 'total_amount' => ['nullable'],
            'invoice' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
            'project_id' => ['prohibited'], 'site_id' => ['prohibited'],
        ]);
        try {
            $record = $this->costs->recordMaintenance($equipment, $data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
        if ($request->hasFile('invoice')) {
            $document = $this->storeFile($request, $equipment, 'invoice', 'service_invoice', $record->maintenance_number);
            $record->update(['document_id' => $document->id]);
        }

        return $this->back($equipment, 'maintenance', 'Maintenance '.$record->maintenance_number.' recorded as open. Complete it when the work is done; Finance posts the cost afterwards.');
    }

    public function completeMaintenance(Request $request, EquipmentAsset $equipment, int $record): RedirectResponse
    {
        $row = $equipment->maintenanceRecords()->findOrFail($record);
        $data = $request->validate(['cost' => ['nullable', 'numeric'], 'vat_rate' => ['nullable', 'numeric'], 'meter_reading' => ['nullable', 'numeric'], 'meter_correction' => ['nullable', 'boolean'],
            'payment_type' => ['sometimes', 'required', 'string'], 'payment_account_id' => ['sometimes', 'nullable', 'integer'],
            'supplier_id' => ['sometimes', 'nullable', 'integer'], 'expense_category_id' => ['sometimes', 'nullable', 'integer']]);
        try {
            $this->costs->completeMaintenance($row, $data, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return $this->back($equipment, 'maintenance', $row->maintenance_number.' completed; the record stays in the service history.');
    }

    public function cancelMaintenance(Request $request, EquipmentAsset $equipment, int $record): RedirectResponse
    {
        $row = $equipment->maintenanceRecords()->findOrFail($record);
        try {
            $this->costs->cancelMaintenance($row, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return $this->back($equipment, 'maintenance', $row->maintenance_number.' cancelled (kept in the history, never posted).');
    }

    public function postMaintenance(Request $request, EquipmentAsset $equipment, int $record): RedirectResponse
    {
        $row = $equipment->maintenanceRecords()->findOrFail($record);
        try {
            $this->costs->post($row, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
        $row->refresh();
        ActivityLog::record($request, 'Equipment', $row->supplier_bill_id ? 'Requested equipment cost bill' : 'Posted maintenance cost', '[Equipment #'.$equipment->id.'] '.$equipment->asset_code.' · '.$row->maintenance_number.' · '.$row->accountingLabel());

        return $this->back($equipment, 'maintenance', $row->maintenance_number.': '.$row->accountingLabel().'.');
    }

    public function storeDocument(Request $request, EquipmentAsset $equipment): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(EquipmentDocument::TYPES))], 'document_number' => ['nullable', 'string', 'max:80'],
            'issue_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'extensions:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        $document = $this->storeFile($request, $equipment, 'file', $data['document_type'], $data['document_number'] ?? null, $data);
        // Registration / insurance expiry on the asset follow the latest document of that type.
        if (! empty($data['expiry_date']) && in_array($data['document_type'], ['registration', 'insurance'], true)) {
            $equipment->update([$data['document_type'].'_expiry_date' => $data['expiry_date']]);
        }

        return $this->back($equipment, 'documents', $document->typeLabel().' "'.$document->original_filename.'" uploaded (private file).');
    }

    /** Private file: only through this authorized route, never a public storage URL. */
    public function documentFile(Request $request, EquipmentAsset $equipment, int $document)
    {
        $file = $equipment->documents()->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($file->original_filename));
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Type' => $file->mime_type, 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        return $request->boolean('download') ? Storage::disk('local')->download($file->path, $name, $headers)
            : Storage::disk('local')->response($file->path, $name, $headers, 'inline');
    }

    // ------------------------------------------------------------------ helpers

    private function storeFile(Request $request, EquipmentAsset $asset, string $field, string $type, ?string $number, array $extra = []): EquipmentDocument
    {
        $upload = $request->file($field);
        $path = $upload->store('equipment-documents', 'local');
        $document = $asset->documents()->create([
            'document_type' => $type, 'document_number' => $number, 'issue_date' => $extra['issue_date'] ?? null, 'expiry_date' => $extra['expiry_date'] ?? null,
            'original_filename' => mb_substr(basename($upload->getClientOriginalName()), 0, 191), 'path' => $path, 'mime_type' => (string) $upload->getClientMimeType(),
            'size' => (int) $upload->getSize(), 'uploaded_by' => $request->user()->id, 'notes' => $extra['notes'] ?? null,
        ]);
        ActivityLog::record($request, 'Equipment', 'Uploaded equipment document', '[Equipment #'.$asset->id.'] '.$asset->asset_code.' · '.$document->typeLabel().' '.$document->original_filename);

        return $document;
    }

    private function back(EquipmentAsset $asset, string $section, string $message): RedirectResponse
    {
        return redirect()->to(route('admin.equipment.show', $asset).'#'.$section)->with('status', $message);
    }
}
