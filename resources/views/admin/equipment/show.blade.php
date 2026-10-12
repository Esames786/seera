@extends('layouts.admin')

@section('title', 'Equipment View')
@section('breadcrumb', 'Operations / Equipment & Vehicles / View')

@section('content')
    @php
        /** @var \App\Models\EquipmentAsset $asset */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $active = $asset->activeAssignment;
        $reg = \App\Models\EquipmentAsset::expiryState($asset->registration_expiry_date);
        $ins = \App\Models\EquipmentAsset::expiryState($asset->insurance_expiry_date);
        $expiryClass = fn ($s) => $s === 'expired' ? 'red' : ($s === 'expiring' ? 'yellow' : 'gray');
        $serviceDue = $nextService && (($nextService->next_service_date && $nextService->next_service_date->lte(today()->addDays(30))) || ($nextService->next_service_meter !== null && (float) $asset->current_meter >= (float) $nextService->next_service_meter - 50));
        $sections = ['overview' => 'Overview', 'assignment' => 'Current Assignment', 'history' => 'Assignment History', 'fuel' => 'Fuel', 'maintenance' => 'Maintenance', 'costs' => 'Costs', 'documents' => 'Documents', 'tracking' => 'Tracking / GPS'];
        if ($activity !== null) $sections['activity'] = 'Activity';
        $accClass = fn ($s) => $s === 'posted' ? 'green' : ($s === 'failed' ? 'red' : ($s === 'bill_pending' ? 'yellow' : 'gray'));
    @endphp

    <x-admin.page-header :title="$asset->asset_code.' — '.$asset->name" description="Read-only equipment view. Assign / Release, Fuel, Maintenance, Documents and Finance posting are explicit actions in their sections.">
        @if ($canEdit)<a class="btn primary" href="{{ route('admin.equipment.edit', [$asset, 'return_to' => route('admin.equipment.show', $asset, false)]) }}">Edit Profile</a>@endif
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.equipment.index')) }}">Back to Equipment</a>
    </x-admin.page-header>

    {{-- Persistent identity header --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $asset->asset_code }} — {{ $asset->name }}</h2>
            <div>
                <x-admin.status-badge :status="$asset->status"/>
                <span class="badge blue">{{ $asset->typeLabel() }}{{ $asset->category ? ' · '.$asset->category : '' }}</span>
                @if ($serviceDue)<span class="badge yellow">Service due</span>@endif
                @if ($reg === 'expired' || $ins === 'expired')<span class="badge red">Document expired</span>@elseif ($reg === 'expiring' || $ins === 'expiring')<span class="badge yellow">Document expiring</span>@endif
            </div>
        </div>
        <dl>
            <dt>Make / Model</dt><dd>{{ trim(($asset->make ?? '').' '.($asset->model ?? '')) ?: '-' }}{{ $asset->year ? ' · '.$asset->year : '' }}</dd>
            <dt>{{ $asset->isVehicle() ? 'Registration / Chassis' : 'Serial number' }}</dt><dd>{{ $asset->isVehicle() ? (($asset->registration_number ?? '-').' / '.($asset->chassis_number ?? '-')) : ($asset->serial_number ?? '-') }}</dd>
            <dt>Ownership</dt><dd>{{ \App\Models\EquipmentAsset::OWNERSHIP[$asset->ownership_type] ?? $asset->ownership_type }}</dd>
            <dt>Meter</dt><dd>{{ $asset->meterLabel() }}</dd>
        </dl>
        <dl>
            <dt>Current project</dt><dd>{{ $asset->project ? $asset->project->code.' · '.$asset->project->name : 'Not assigned' }}</dd>
            <dt>Current site</dt><dd>{{ $asset->site?->name ?? '-' }}</dd>
            <dt>Operator / driver</dt><dd>{{ $asset->operator?->name ?? '-' }}</dd>
            <dt>Next service</dt><dd>{{ $nextService ? trim(($nextService->next_service_date?->toDateString() ?? '').($nextService->next_service_meter !== null ? ' · at '.number_format((float) $nextService->next_service_meter, 1).' '.$asset->meterUnit() : '')) : 'No service plan recorded' }}</dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Equipment sections">
        @foreach ($sections as $key => $label)<a class="tab" href="#{{ $key }}">{{ $label }}</a>@endforeach
    </nav>

    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    {{-- Overview --}}
    <x-admin.data-table title="Overview" class="detail-table" id="overview">
        <tbody>
            <tr><th>Asset code / name</th><td>{{ $asset->asset_code }} · {{ $asset->name }}</td></tr>
            <tr><th>Type / category</th><td>{{ $asset->typeLabel() }} / {{ $asset->category ?? '-' }}</td></tr>
            <tr><th>Make / model / year</th><td>{{ $asset->make ?? '-' }} / {{ $asset->model ?? '-' }} / {{ $asset->year ?? '-' }}</td></tr>
            @if ($asset->isVehicle())
                <tr><th>Registration number</th><td>{{ $asset->registration_number ?? '-' }} · expiry <span class="badge {{ $expiryClass($reg) }}">{{ $asset->registration_expiry_date?->toDateString() ?? 'unknown' }}</span></td></tr>
                <tr><th>Chassis / VIN</th><td>{{ $asset->chassis_number ?? '-' }}</td></tr>
            @else
                <tr><th>Serial number</th><td>{{ $asset->serial_number ?? '-' }}</td></tr>
            @endif
            <tr><th>Insurance expiry</th><td><span class="badge {{ $expiryClass($ins) }}">{{ $asset->insurance_expiry_date?->toDateString() ?? 'unknown' }}</span></td></tr>
            <tr><th>Ownership / acquisition</th><td>{{ \App\Models\EquipmentAsset::OWNERSHIP[$asset->ownership_type] ?? $asset->ownership_type }} · {{ $asset->acquisition_date?->toDateString() ?? '-' }}{{ $canFinance && $asset->acquisition_cost !== null ? ' · '.$money($asset->acquisition_cost) : '' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$asset->status"/>{{ $openMaintenance ? ' · '.$openMaintenance.' open maintenance record(s)' : '' }}</td></tr>
            <tr><th>Meter</th><td>{{ $asset->meterLabel() }} ({{ \App\Models\EquipmentAsset::METER_TYPES[$asset->meter_type] ?? $asset->meter_type }}). Readings from assignments, fuel and maintenance only move it forward; a lower reading needs a logged correction.</td></tr>
            <tr><th>Notes</th><td>{{ $asset->notes ?? '-' }}</td></tr>
            <tr><th>Created</th><td>{{ $asset->created_at?->format('Y-m-d H:i') }} · {{ $asset->creator?->name ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Current Assignment --}}
    <x-admin.data-table title="Current Assignment" subtitle="One active assignment per asset. Reassigning closes the current one and keeps it in the history." class="detail-table" id="assignment">
        <tbody>
            @if ($active)
                <tr><th>Project</th><td>{{ $active->project?->code }} · {{ $active->project?->name }}</td></tr>
                <tr><th>Site</th><td>{{ $active->site?->name ?? 'Whole project' }}</td></tr>
                <tr><th>Operator / driver</th><td>{{ $active->operator?->name ?? '-' }}</td></tr>
                <tr><th>Since</th><td>{{ $active->assigned_from->toDateString() }} · meter at assignment {{ $active->start_meter !== null ? number_format((float) $active->start_meter, 1).' '.$asset->meterUnit() : '-' }} · by {{ $active->assigner?->name ?? '-' }}</td></tr>
                @if ($active->notes)<tr><th>Notes</th><td>{{ $active->notes }}</td></tr>@endif
            @else
                <tr><td colspan="2" class="table-empty">This asset is not assigned to any project.{{ $canAssign ? ' Use the form below to assign it.' : '' }}</td></tr>
            @endif
        </tbody>
        @if ($canAssign && $asset->status !== 'retired')
            <x-slot:footer>
                <div style="display:flex;flex-direction:column;gap:12px;width:100%">
                    <form method="POST" action="{{ route('admin.equipment.assign', $asset) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;align-items:end">
                        @csrf
                        <div><label for="assign_project">{{ $active ? 'Reassign to project' : 'Assign to project' }} *</label><select id="assign_project" name="project_id" class="select" required><option value="">Select project</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} {{ $project->name }}</option>@endforeach</select></div>
                        <div><label for="assign_site">Site</label><select id="assign_site" name="site_id" class="select"><option value="">Whole project</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $projects->firstWhere('id', $site->project_id)?->code }} · {{ $site->name }}</option>@endforeach</select></div>
                        <div><label for="assign_operator">Operator / driver</label><select id="assign_operator" name="operator_employee_id" class="select"><option value="">None</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('operator_employee_id') === (string) $employee->id)>{{ $employee->employee_code }} {{ $employee->name }}</option>@endforeach</select></div>
                        <div><label for="assign_from">From</label><input id="assign_from" name="assigned_from" type="date" class="input" value="{{ old('assigned_from', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                        <div><label for="assign_meter">Meter reading</label><input id="assign_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                        <div><label for="assign_corr">Meter correction</label><select id="assign_corr" name="meter_correction" class="select"><option value="0">No</option><option value="1">Yes (logged)</option></select></div>
                        <div style="grid-column:span 2"><label for="assign_notes">Notes</label><input id="assign_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                        <div><button type="submit" class="btn sm primary">{{ $active ? 'Reassign' : 'Assign' }}</button></div>
                    </form>
                    @if ($active)
                        <form method="POST" action="{{ route('admin.equipment.release', $asset) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end" onsubmit="return confirm('Release {{ $asset->asset_code }} from {{ $active->project?->code }}? The assignment is closed and kept in the history.');">
                            @csrf
                            <div><label for="release_until">Release on</label><input id="release_until" name="assigned_until" type="date" class="input" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}"/></div>
                            <div><label for="release_meter">Meter reading</label><input id="release_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                            <div><label for="release_corr">Meter correction</label><select id="release_corr" name="meter_correction" class="select"><option value="0">No</option><option value="1">Yes (logged)</option></select></div>
                            <button type="submit" class="btn sm danger">Release from project</button>
                        </form>
                    @endif
                </div>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Assignment History --}}
    <x-admin.data-table title="Assignment History" subtitle="Every assignment ever recorded; closed rows are never deleted" id="history">
        <thead><tr><th>Project</th><th>Site</th><th>Operator</th><th>From</th><th>Until</th><th>Meter start → end</th><th>Assigned by</th><th>Notes</th><th>State</th></tr></thead>
        <tbody>
            @forelse ($assignmentHistory as $row)
                <tr>
                    <td>{{ $row->project?->code }} · {{ $row->project?->name }}</td><td>{{ $row->site?->name ?? 'Whole project' }}</td><td>{{ $row->operator?->name ?? '-' }}</td>
                    <td>{{ $row->assigned_from->toDateString() }}</td><td>{{ $row->assigned_until?->toDateString() ?? '—' }}</td>
                    <td class="small">{{ $row->start_meter !== null ? number_format((float) $row->start_meter, 1) : '-' }} → {{ $row->end_meter !== null ? number_format((float) $row->end_meter, 1) : '-' }}</td>
                    <td class="small">{{ $row->assigner?->name ?? '-' }}</td><td class="small">{{ $row->notes ?? '-' }}</td>
                    <td><span class="badge {{ $row->is_active ? 'green' : 'gray' }}">{{ $row->is_active ? 'Active' : 'Closed' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No assignment history yet.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer><span class="small">Showing {{ $assignmentHistory->firstItem() ?? 0 }}-{{ $assignmentHistory->lastItem() ?? 0 }} of {{ $assignmentHistory->total() }}</span>@if ($assignmentHistory->hasPages()){{ $assignmentHistory->links() }}@endif</x-slot:footer>
    </x-admin.data-table>

    {{-- Fuel --}}
    <x-admin.data-table title="Fuel" subtitle="Total = quantity × rate (server-calculated). A fuel entry becomes project cost only when Finance posts it (Cash / Bank journal) or the supplier bill it creates is posted." id="fuel">
        <thead><tr><th>Number</th><th>Date</th><th>Project / Site</th><th>Meter</th><th>Quantity</th><th>Rate</th><th>Total</th><th>Payment</th><th>Category</th><th>Accounting</th><th>Receipt</th><th></th></tr></thead>
        <tbody>
            @forelse ($fuelLogs as $log)
                <tr>
                    <td>{{ $log->fuel_number }}</td><td>{{ $log->fuel_date->toDateString() }}</td>
                    <td class="small">{{ $log->project?->code ?? 'No project' }}{{ $log->site ? ' / '.$log->site->name : '' }}</td>
                    <td>{{ $log->meter_reading !== null ? number_format((float) $log->meter_reading, 1) : '-' }}</td>
                    <td>{{ number_format((float) $log->quantity, 3) }} {{ $log->unit }}</td><td>{{ number_format((float) $log->rate, 4) }}</td>
                    <td><strong>{{ $canFinance || $canPost ? number_format((float) $log->total_amount, 2) : 'Restricted' }}</strong>{{ (float) $log->vat_amount > 0 ? ' (incl. VAT)' : '' }}</td>
                    <td class="small">{{ $log->payment_type }}{{ $log->supplier ? ' · '.$log->supplier->name : '' }}{{ $log->paymentAccount ? ' · '.$log->paymentAccount->account_name : '' }}</td>
                    <td class="small">{{ $log->category?->name ?? '-' }}</td>
                    <td><span class="badge {{ $accClass($log->accounting_status) }}">{{ $log->accountingLabel() }}</span>
                        @if ($log->journalEntry && $canViewJournal)<div class="small"><a href="{{ route('admin.accounting.journal-entries.show', $log->journalEntry) }}">{{ $log->journalEntry->journal_number }}</a></div>@endif
                        @if ($log->supplierBill && $canViewBill)<div class="small"><a href="{{ route('admin.accounting.accounts-payable.show', $log->supplierBill) }}">Bill {{ $log->supplierBill->bill_number }}</a></div>@endif
                        @if ($log->posting_error)<div class="small" style="color:var(--red)">{{ $log->posting_error }}</div>@endif</td>
                    <td>@if ($log->document)<a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $log->document->id]) }}">Receipt</a>@else -@endif</td>
                    <td>@if ($canPost && ! $log->isFinanciallyLinked())<form method="POST" action="{{ route('admin.equipment.fuel.post', [$asset, $log->id]) }}">@csrf<button type="submit" class="btn sm primary">{{ $log->accounting_status === 'failed' ? 'Retry posting' : ($log->payment_type === 'Supplier Credit' ? 'Create supplier bill' : 'Post to accounting') }}</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No fuel entries have been recorded.{{ $canRecord ? ' Use the form below.' : '' }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $fuelLogs->firstItem() ?? 0 }}-{{ $fuelLogs->lastItem() ?? 0 }} of {{ $fuelLogs->total() }}</span>@if ($fuelLogs->hasPages()){{ $fuelLogs->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>
    @if ($canRecord)
        <div class="card" style="padding:16px;margin-bottom:16px" id="add-fuel">
            <h3>Record Fuel</h3>
            <form method="POST" action="{{ route('admin.equipment.fuel.store', $asset) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end">
                @csrf
                <div><label for="fuel_date">Date *</label><input id="fuel_date" name="fuel_date" type="date" class="input" required value="{{ old('fuel_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                <div><label for="fuel_qty">Quantity *</label><input id="fuel_qty" name="quantity" type="number" step="0.001" min="0.001" class="input" required value="{{ old('quantity') }}"/></div>
                <div><label for="fuel_unit">Unit</label><select id="fuel_unit" name="unit" class="select"><option value="litre">Litre</option><option value="gallon" @selected(old('unit') === 'gallon')>Gallon</option></select></div>
                <div><label for="fuel_rate">Rate (SAR / unit) *</label><input id="fuel_rate" name="rate" type="number" step="0.0001" min="0" class="input" required value="{{ old('rate') }}"/></div>
                <div><label for="fuel_vat">VAT rate</label><select id="fuel_vat" name="vat_rate" class="select"><option value="0">0%</option><option value="15" @selected(old('vat_rate') === '15')>15%</option></select></div>
                <div><label for="fuel_meter">Meter reading</label><input id="fuel_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                <div><label for="fuel_corr">Meter correction</label><select id="fuel_corr" name="meter_correction" class="select"><option value="0">No</option><option value="1">Yes (logged)</option></select></div>
                <div><label for="fuel_pay">Payment *</label><select id="fuel_pay" name="payment_type" class="select" required>@foreach (['Cash', 'Bank', 'Supplier Credit'] as $type)<option value="{{ $type }}" @selected(old('payment_type', 'Cash') === $type)>{{ $type }}</option>@endforeach</select></div>
                <div><label for="fuel_acc">Paid from (Cash / Bank)</label><select id="fuel_acc" name="payment_account_id" class="select"><option value="">Select account</option>@foreach ($cashAccounts->merge($bankAccounts) as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->label() }}</option>@endforeach</select></div>
                <div><label for="fuel_sup">Supplier (Supplier Credit)</label><select id="fuel_sup" name="supplier_id" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label for="fuel_cat">Expense category *</label><select id="fuel_cat" name="expense_category_id" class="select" required><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="fuel_ref">Invoice / reference</label><input id="fuel_ref" name="reference_number" class="input" maxlength="100" value="{{ old('reference_number') }}"/></div>
                <div><label for="fuel_receipt">Receipt (jpg / png / pdf)</label><input id="fuel_receipt" name="receipt" type="file" class="input" accept=".jpg,.jpeg,.png,.pdf"/></div>
                <div style="grid-column:span 2"><label for="fuel_notes">Notes</label><input id="fuel_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                <div><button type="submit" class="btn sm primary">Record Fuel</button></div>
            </form>
            <p class="small">Project and site come from the active assignment at the time of the entry. The expense category's account decides the posting; Cash / Bank needs the account paid from, Supplier Credit needs the supplier and creates a draft supplier bill when Finance posts.</p>
        </div>
    @endif

    {{-- Maintenance --}}
    <x-admin.data-table title="Maintenance" subtitle="Open → Completed (or Cancelled). Completed records stay in the history; cost is posted by Finance after completion." id="maintenance">
        <thead><tr><th>Number</th><th>Date</th><th>Type</th><th>Description</th><th>Workshop / supplier</th><th>Meter</th><th>Cost</th><th>Next service</th><th>Status</th><th>Accounting</th><th>Invoice</th><th></th></tr></thead>
        <tbody>
            @forelse ($maintenance as $row)
                <tr>
                    <td>{{ $row->maintenance_number }}</td><td>{{ $row->service_date->toDateString() }}</td><td>{{ $row->typeLabel() }}</td>
                    <td class="small">{{ $row->description }}</td><td class="small">{{ $row->supplier?->name ?? '-' }}{{ $row->payment_type ? ' · '.$row->payment_type : '' }}</td>
                    <td>{{ $row->meter_reading !== null ? number_format((float) $row->meter_reading, 1) : '-' }}</td>
                    <td><strong>{{ $canFinance || $canPost ? number_format((float) $row->total_amount, 2) : 'Restricted' }}</strong></td>
                    <td class="small">{{ $row->next_service_date?->toDateString() ?? '' }}{{ $row->next_service_meter !== null ? ' · '.number_format((float) $row->next_service_meter, 1).' '.$asset->meterUnit() : '' }}{{ ! $row->next_service_date && $row->next_service_meter === null ? '-' : '' }}</td>
                    <td><x-admin.status-badge :status="$row->status"/></td>
                    <td>@if ($row->status !== 'cancelled')<span class="badge {{ $accClass($row->accounting_status) }}">{{ $row->accountingLabel() }}</span>
                        @if ($row->journalEntry && $canViewJournal)<div class="small"><a href="{{ route('admin.accounting.journal-entries.show', $row->journalEntry) }}">{{ $row->journalEntry->journal_number }}</a></div>@endif
                        @if ($row->supplierBill && $canViewBill)<div class="small"><a href="{{ route('admin.accounting.accounts-payable.show', $row->supplierBill) }}">Bill {{ $row->supplierBill->bill_number }}</a></div>@endif
                        @if ($row->posting_error)<div class="small" style="color:var(--red)">{{ $row->posting_error }}</div>@endif
                        @else -@endif</td>
                    <td>@if ($row->document)<a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $row->document->id]) }}">Invoice</a>@else -@endif</td>
                    <td>
                        <div class="actions">
                            @if ($row->status === 'open' && $canEdit)
                                <form method="POST" action="{{ route('admin.equipment.maintenance.complete', [$asset, $row->id]) }}" style="display:flex;gap:4px;align-items:end">@csrf<input name="cost" type="number" step="0.01" min="0" class="input" style="width:110px" placeholder="final cost" value="{{ $row->taxable_amount > 0 ? $row->taxable_amount : '' }}"/><input name="meter_reading" type="number" step="0.1" min="0" class="input" style="width:100px" placeholder="meter"/><button type="submit" class="btn sm primary">Complete</button></form>
                                <form method="POST" action="{{ route('admin.equipment.maintenance.cancel', [$asset, $row->id]) }}" onsubmit="return confirm('Cancel {{ $row->maintenance_number }}? It stays in the history and is never posted.');">@csrf<button type="submit" class="btn sm outline">Cancel</button></form>
                            @endif
                            @if ($row->status === 'completed' && $canPost && ! $row->isFinanciallyLinked() && (float) $row->taxable_amount > 0)
                                <form method="POST" action="{{ route('admin.equipment.maintenance.post', [$asset, $row->id]) }}">@csrf<button type="submit" class="btn sm primary">{{ $row->accounting_status === 'failed' ? 'Retry posting' : ($row->payment_type === 'Supplier Credit' ? 'Create supplier bill' : 'Post to accounting') }}</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No maintenance history exists yet.{{ $canRecord ? ' Use the form below to record a service or repair.' : '' }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $maintenance->firstItem() ?? 0 }}-{{ $maintenance->lastItem() ?? 0 }} of {{ $maintenance->total() }}</span>@if ($maintenance->hasPages()){{ $maintenance->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>
    @if ($canRecord)
        <div class="card" style="padding:16px;margin-bottom:16px" id="add-maintenance">
            <h3>Record Maintenance / Service</h3>
            <form method="POST" action="{{ route('admin.equipment.maintenance.store', $asset) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end">
                @csrf
                <div><label for="mnt_date">Service date *</label><input id="mnt_date" name="service_date" type="date" class="input" required value="{{ old('service_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                <div><label for="mnt_type">Type *</label><select id="mnt_type" name="maintenance_type" class="select" required>@foreach ($maintenanceTypes as $key => $label)<option value="{{ $key }}" @selected(old('maintenance_type', 'repair') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div style="grid-column:span 2"><label for="mnt_desc">Description *</label><input id="mnt_desc" name="description" class="input" maxlength="2000" required value="{{ old('description') }}"/></div>
                <div><label for="mnt_cost">Cost before VAT (SAR)</label><input id="mnt_cost" name="cost" type="number" step="0.01" min="0" class="input" value="{{ old('cost') }}"/></div>
                <div><label for="mnt_vat">VAT rate</label><select id="mnt_vat" name="vat_rate" class="select"><option value="0">0%</option><option value="15" @selected(old('vat_rate') === '15')>15%</option></select></div>
                <div><label for="mnt_meter">Meter reading</label><input id="mnt_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                <div><label for="mnt_corr">Meter correction</label><select id="mnt_corr" name="meter_correction" class="select"><option value="0">No</option><option value="1">Yes (logged)</option></select></div>
                <div><label for="mnt_next_date">Next service date</label><input id="mnt_next_date" name="next_service_date" type="date" class="input" value="{{ old('next_service_date') }}"/></div>
                <div><label for="mnt_next_meter">Next service at meter</label><input id="mnt_next_meter" name="next_service_meter" type="number" step="0.1" min="0" class="input" value="{{ old('next_service_meter') }}"/></div>
                <div><label for="mnt_pay">Payment</label><select id="mnt_pay" name="payment_type" class="select">@foreach (['Cash', 'Bank', 'Supplier Credit'] as $type)<option value="{{ $type }}" @selected(old('payment_type', 'Cash') === $type)>{{ $type }}</option>@endforeach</select></div>
                <div><label for="mnt_acc">Paid from (Cash / Bank)</label><select id="mnt_acc" name="payment_account_id" class="select"><option value="">Select account</option>@foreach ($cashAccounts->merge($bankAccounts) as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->label() }}</option>@endforeach</select></div>
                <div><label for="mnt_sup">Workshop / supplier</label><select id="mnt_sup" name="supplier_id" class="select"><option value="">None</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label for="mnt_cat">Expense category</label><select id="mnt_cat" name="expense_category_id" class="select"><option value="">Select category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="mnt_ref">Invoice / reference</label><input id="mnt_ref" name="reference_number" class="input" maxlength="100" value="{{ old('reference_number') }}"/></div>
                <div><label for="mnt_file">Invoice file (jpg / png / pdf)</label><input id="mnt_file" name="invoice" type="file" class="input" accept=".jpg,.jpeg,.png,.pdf"/></div>
                <div><label for="mnt_out">Take out of service</label><select id="mnt_out" name="take_out_of_service" class="select"><option value="0">No</option><option value="1" @selected(old('take_out_of_service') === '1')>Yes — status Under Maintenance until completed</option></select></div>
                <div style="grid-column:span 2"><label for="mnt_notes">Notes</label><input id="mnt_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                <div><button type="submit" class="btn sm primary">Record Maintenance</button></div>
            </form>
        </div>
    @endif

    {{-- Costs --}}
    <x-admin.data-table title="Costs" subtitle="Equipment cost reaches the Project Cost Report and the project budget only through posted accounting (Cash / Bank journal or the posted supplier bill), never from the operational totals below." class="detail-table" id="costs">
        <tbody>
            @if ($canFinance && $costs)
                <tr><th>Fuel — posted</th><td>{{ $money($costs['fuel']->posted) }}</td></tr>
                <tr><th>Fuel — recorded, not yet posted</th><td>{{ $money($costs['fuel']->unposted) }} ({{ $costs['fuel']->entries }} entries in total)</td></tr>
                <tr><th>Maintenance — posted</th><td>{{ $money($costs['maintenance']->posted) }}</td></tr>
                <tr><th>Maintenance — recorded, not yet posted</th><td>{{ $money($costs['maintenance']->unposted) }} ({{ $costs['maintenance']->entries }} records, cancelled excluded)</td></tr>
                <tr><th>Posted equipment cost (this asset)</th><td><strong>{{ $money($costs['fuel']->posted + $costs['maintenance']->posted) }}</strong></td></tr>
                <tr><th>Where it counts</th><td class="small">Each posted journal carries the project / site of the assignment at the time of the entry, so the Project Cost Report and Budget vs Actual (Equipment category, mapped accounts) count it once. Supplier-credit records count through their supplier bill, never twice.</td></tr>
            @else
                <tr><td colspan="2" class="table-empty">Cost figures are shown to users with Financial Reports view.</td></tr>
            @endif
        </tbody>
    </x-admin.data-table>

    {{-- Documents --}}
    <x-admin.data-table title="Documents" subtitle="Private files (registration, insurance, inspection, permits, invoices, receipts). Served only to authorized users; no public links." id="documents">
        <thead><tr><th>Type</th><th>Number</th><th>Issued</th><th>Expiry</th><th>File</th><th>Uploaded</th><th>Notes</th></tr></thead>
        <tbody>
            @forelse ($documents as $doc)
                @php $state = $doc->expiryState(); @endphp
                <tr>
                    <td>{{ $doc->typeLabel() }}</td><td>{{ $doc->document_number ?? '-' }}</td><td>{{ $doc->issue_date?->toDateString() ?? '-' }}</td>
                    <td>@if ($doc->expiry_date)<span class="badge {{ $expiryClass($state) }}">{{ $doc->expiry_date->toDateString() }}{{ $state === 'expired' ? ' · expired' : ($state === 'expiring' ? ' · expiring' : '') }}</span>@else -@endif</td>
                    <td><a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $doc->id]) }}">{{ $doc->original_filename }}</a> <a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $doc->id, 'download' => 1]) }}">Download</a></td>
                    <td class="small">{{ $doc->created_at?->format('Y-m-d') }} · {{ $doc->uploader?->name ?? '-' }}</td><td class="small">{{ $doc->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No documents uploaded yet.</td></tr>
            @endforelse
        </tbody>
        @if ($canRecord)
            <x-slot:footer>
                <form method="POST" action="{{ route('admin.equipment.documents.store', $asset) }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                    @csrf
                    <div><label for="doc_type">Type *</label><select id="doc_type" name="document_type" class="select" required>@foreach ($documentTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label for="doc_number">Number</label><input id="doc_number" name="document_number" class="input" maxlength="80"/></div>
                    <div><label for="doc_issue">Issued</label><input id="doc_issue" name="issue_date" type="date" class="input"/></div>
                    <div><label for="doc_expiry">Expiry</label><input id="doc_expiry" name="expiry_date" type="date" class="input"/></div>
                    <div><label for="doc_file">File *</label><input id="doc_file" name="file" type="file" class="input" required accept=".jpg,.jpeg,.png,.pdf"/></div>
                    <div><label for="doc_notes">Notes</label><input id="doc_notes" name="notes" class="input" maxlength="500"/></div>
                    <button type="submit" class="btn sm primary">Upload</button>
                </form>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Tracking / GPS --}}
    <x-admin.data-table title="Tracking / GPS" class="detail-table" id="tracking">
        <tbody>
            <tr><th>Live tracking</th><td><span class="badge gray">Not connected</span> Live GPS tracking is not connected to a telematics provider. No position, route, speed, idle time or geofence event is recorded or shown for this asset.</td></tr>
            <tr><th>Tracker readiness</th><td>{{ $asset->tracker_device_id ? ($asset->tracker_provider ?: 'Provider not set').' · device '.$asset->tracker_device_id.' (stored for the future integration; not polled)' : 'No tracker device recorded.' }}</td></tr>
            <tr><th>What exists today</th><td class="small">Project / site assignment, operator, meter readings from assignments, fuel and maintenance, and the site geofence settings used by mobile attendance. The provider integration contract is documented for the next phase.</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Activity --}}
    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries for this asset, limited to the users your role may see" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($activity as $log)
                    <tr><td>{{ $log->created_at?->format('Y-m-d H:i') }}</td><td>{{ $log->user_name }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td></tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No activity recorded for this asset yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    @endif

    <div class="note">
        Equipment Phase 1: asset master, assignment history, fuel, maintenance, private documents and accounting through Expense Categories and the Finance payment catalogue. Live GPS / routes / speed / idle time, utilization analytics and depreciation are NOT YET OPERATIONAL.
    </div>
@endsection
