@extends('layouts.admin')

@section('title', __('Equipment View'))
@section('breadcrumb', __('Operations / Equipment & Vehicles / View'))

@section('content')
    @php
        /** @var \App\Models\EquipmentAsset $asset */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $active = $asset->activeAssignment;
        $reg = \App\Models\EquipmentAsset::expiryState($asset->registration_expiry_date);
        $ins = \App\Models\EquipmentAsset::expiryState($asset->insurance_expiry_date);
        $expiryClass = fn ($s) => $s === 'expired' ? 'red' : ($s === 'expiring' ? 'yellow' : 'gray');
        $serviceDue = $nextService && (($nextService->next_service_date && $nextService->next_service_date->lte(today()->addDays(30))) || ($nextService->next_service_meter !== null && (float) $asset->current_meter >= (float) $nextService->next_service_meter - 50));
        $sections = ['overview' => __('Overview'), 'assignment' => __('Current Assignment'), 'history' => __('Assignment History'), 'fuel' => __('Fuel'), 'maintenance' => __('Maintenance'), 'costs' => __('Costs'), 'documents' => __('Documents'), 'tracking' => __('Tracking / GPS')];
        if ($activity !== null) $sections['activity'] = __('Activity');
        $accClass = fn ($s) => $s === 'posted' ? 'green' : ($s === 'failed' ? 'red' : ($s === 'bill_pending' ? 'yellow' : 'gray'));
    @endphp

    <x-admin.page-header :title="$asset->asset_code.' — '.$asset->name"  :description="__('Read-only equipment view. Assign / Release, Fuel, Maintenance, Documents and Finance posting are explicit actions in their sections.')">
        @if ($canEdit)<a class="btn primary" href="{{ route('admin.equipment.edit', [$asset, 'return_to' => route('admin.equipment.show', $asset, false)]) }}">{{ __('Edit Profile') }}</a>@endif
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.equipment.index')) }}">{{ __('Back to Equipment') }}</a>
    </x-admin.page-header>

    {{-- Persistent identity header --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $asset->asset_code }} — {{ $asset->name }}</h2>
            <div>
                <x-admin.status-badge :status="$asset->status"/>
                <span class="badge blue">{{ __($asset->typeLabel()) }}{{ $asset->category ? ' · '.$asset->category : '' }}</span>
                @if ($serviceDue)<span class="badge yellow">{{ __('Service due') }}</span>@endif
                @if ($reg === 'expired' || $ins === 'expired')<span class="badge red">{{ __('Document expired') }}</span>@elseif ($reg === 'expiring' || $ins === 'expiring')<span class="badge yellow">{{ __('Document expiring') }}</span>@endif
            </div>
        </div>
        <dl>
            <dt>{{ __('Make / Model') }}</dt><dd>{{ trim(($asset->make ?? '').' '.($asset->model ?? '')) ?: '-' }}{{ $asset->year ? ' · '.$asset->year : '' }}</dd>
            <dt>{{ __($asset->isVehicle() ? 'Registration / Chassis' : 'Serial number') }}</dt><dd>{{ $asset->isVehicle() ? (($asset->registration_number ?? '-').' / '.($asset->chassis_number ?? '-')) : ($asset->serial_number ?? '-') }}</dd>
            <dt>{{ __('Ownership') }}</dt><dd>{{ __(\App\Models\EquipmentAsset::OWNERSHIP[$asset->ownership_type] ?? $asset->ownership_type) }}</dd>
            <dt>{{ __('Meter') }}</dt><dd>{{ $asset->meterLabel() }}</dd>
        </dl>
        <dl>
            <dt>{{ __('Current project') }}</dt><dd>{{ $asset->project ? $asset->project->code.' · '.$asset->project->name : __('Not assigned') }}</dd>
            <dt>{{ __('Current site') }}</dt><dd>{{ $asset->site?->name ?? '-' }}</dd>
            <dt>{{ __('Operator / driver') }}</dt><dd>{{ $asset->operator?->name ?? '-' }}</dd>
            <dt>{{ __('Next service') }}</dt><dd>{{ $nextService ? trim(($nextService->next_service_date?->toDateString() ?? '').($nextService->next_service_meter !== null ? ' · at '.number_format((float) $nextService->next_service_meter, 1).' '.$asset->meterUnit() : '')) : __('No service plan recorded') }}</dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Equipment sections">
        @foreach ($sections as $key => $label)<a class="tab" href="#{{ $key }}">{{ $label }}</a>@endforeach
    </nav>

    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    {{-- Overview --}}
    <x-admin.data-table :title="__('Overview')" class="detail-table" id="overview">
        <tbody>
            <tr><th>{{ __('Asset code / name') }}</th><td>{{ $asset->asset_code }} · {{ $asset->name }}</td></tr>
            <tr><th>{{ __('Type / category') }}</th><td>{{ __($asset->typeLabel()) }} / {{ $asset->category ?? '-' }}</td></tr>
            <tr><th>{{ __('Make / model / year') }}</th><td>{{ $asset->make ?? '-' }} / {{ $asset->model ?? '-' }} / {{ $asset->year ?? '-' }}</td></tr>
            @if ($asset->isVehicle())
                <tr><th>{{ __('Registration number') }}</th><td>{{ $asset->registration_number ?? '-' }} · expiry <span class="badge {{ $expiryClass($reg) }}">{{ $asset->registration_expiry_date?->toDateString() ?? __('unknown') }}</span></td></tr>
                <tr><th>{{ __('Chassis / VIN') }}</th><td>{{ $asset->chassis_number ?? '-' }}</td></tr>
            @else
                <tr><th>{{ __('Serial number') }}</th><td>{{ $asset->serial_number ?? '-' }}</td></tr>
            @endif
            <tr><th>{{ __('Insurance expiry') }}</th><td><span class="badge {{ $expiryClass($ins) }}">{{ $asset->insurance_expiry_date?->toDateString() ?? __('unknown') }}</span></td></tr>
            <tr><th>{{ __('Ownership / acquisition') }}</th><td>{{ __(\App\Models\EquipmentAsset::OWNERSHIP[$asset->ownership_type] ?? $asset->ownership_type) }} · {{ $asset->acquisition_date?->toDateString() ?? '-' }}{{ $canFinance && $asset->acquisition_cost !== null ? ' · '.$money($asset->acquisition_cost) : '' }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td><x-admin.status-badge :status="$asset->status"/>{{ $openMaintenance ? ' · '.$openMaintenance.' open maintenance record(s)' : '' }}</td></tr>
            <tr><th>{{ __('Meter') }}</th><td>{{ $asset->meterLabel() }} ({{ __(\App\Models\EquipmentAsset::METER_TYPES[$asset->meter_type] ?? $asset->meter_type) }}). {{ __('Readings from assignments, fuel and maintenance only move it forward; a lower reading needs a logged correction.') }}</td></tr>
            <tr><th>{{ __('Notes') }}</th><td>{{ $asset->notes ?? '-' }}</td></tr>
            <tr><th>{{ __('Created') }}</th><td>{{ $asset->created_at?->format('Y-m-d H:i') }} · {{ $asset->creator?->name ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Current Assignment --}}
    <x-admin.data-table :title="__('Current Assignment')" :subtitle="__('One active assignment per asset. Reassigning closes the current one and keeps it in the history.')" class="detail-table" id="assignment">
        <tbody>
            @if ($active)
                <tr><th>{{ __('Project') }}</th><td>{{ $active->project?->code }} · {{ $active->project?->name }}</td></tr>
                <tr><th>{{ __('Site') }}</th><td>{{ $active->site?->name ?? __('Whole project') }}</td></tr>
                <tr><th>{{ __('Operator / driver') }}</th><td>{{ $active->operator?->name ?? '-' }}</td></tr>
                <tr><th>{{ __('Since') }}</th><td>{{ $active->assigned_from->toDateString() }} · meter at assignment {{ $active->start_meter !== null ? number_format((float) $active->start_meter, 1).' '.$asset->meterUnit() : '-' }} · by {{ $active->assigner?->name ?? '-' }}</td></tr>
                @if ($active->notes)<tr><th>{{ __('Notes') }}</th><td>{{ $active->notes }}</td></tr>@endif
            @else
                <tr><td colspan="2" class="table-empty">This asset is not assigned to any project.{{ $canAssign ? ' Use the form below to assign it.' : '' }}</td></tr>
            @endif
        </tbody>
        @if ($canAssign && $asset->status !== 'retired')
            <x-slot:footer>
                <div style="display:flex;flex-direction:column;gap:12px;width:100%">
                    <form method="POST" action="{{ route('admin.equipment.assign', $asset) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;align-items:end">
                        @csrf
                        <div><label for="assign_project">{{ __($active ? 'Reassign to project' : 'Assign to project') }} *</label><select id="assign_project" name="project_id" class="select" required><option value="">{{ __('Select project') }}</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} {{ $project->name }}</option>@endforeach</select></div>
                        <div><label for="assign_site">{{ __('Site') }}</label><select id="assign_site" name="site_id" class="select"><option value="">{{ __('Whole project') }}</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $projects->firstWhere('id', $site->project_id)?->code }} · {{ $site->name }}</option>@endforeach</select></div>
                        <div><label for="assign_operator">{{ __('Operator / driver') }}</label><select id="assign_operator" name="operator_employee_id" class="select"><option value="">{{ __('None') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('operator_employee_id') === (string) $employee->id)>{{ $employee->employee_code }} {{ $employee->name }}</option>@endforeach</select></div>
                        <div><label for="assign_from">{{ __('From') }}</label><input id="assign_from" name="assigned_from" type="date" class="input" value="{{ old('assigned_from', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                        <div><label for="assign_meter">{{ __('Meter reading') }}</label><input id="assign_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                        <div><label for="assign_corr">{{ __('Meter correction') }}</label><select id="assign_corr" name="meter_correction" class="select"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes (logged)') }}</option></select></div>
                        <div style="grid-column:span 2"><label for="assign_notes">{{ __('Notes') }}</label><input id="assign_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                        <div><button type="submit" class="btn sm primary">{{ __($active ? 'Reassign' : 'Assign') }}</button></div>
                    </form>
                    @if ($active)
                        <form method="POST" action="{{ route('admin.equipment.release', $asset) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end" onsubmit="return confirm('Release {{ $asset->asset_code }} from {{ $active->project?->code }}? The assignment is closed and kept in the history.');">
                            @csrf
                            <div><label for="release_until">{{ __('Release on') }}</label><input id="release_until" name="assigned_until" type="date" class="input" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}"/></div>
                            <div><label for="release_meter">{{ __('Meter reading') }}</label><input id="release_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                            <div><label for="release_corr">{{ __('Meter correction') }}</label><select id="release_corr" name="meter_correction" class="select"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes (logged)') }}</option></select></div>
                            <button type="submit" class="btn sm danger">{{ __('Release from project') }}</button>
                        </form>
                    @endif
                </div>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Assignment History --}}
    <x-admin.data-table :title="__('Assignment History')" :subtitle="__('Every assignment ever recorded; closed rows are never deleted')" id="history">
        <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Site') }}</th><th>{{ __('Operator') }}</th><th>{{ __('From') }}</th><th>{{ __('Until') }}</th><th>{{ __('Meter start → end') }}</th><th>{{ __('Assigned by') }}</th><th>{{ __('Notes') }}</th><th>{{ __('State') }}</th></tr></thead>
        <tbody>
            @forelse ($assignmentHistory as $row)
                <tr>
                    <td>{{ $row->project?->code }} · {{ $row->project?->name }}</td><td>{{ $row->site?->name ?? __('Whole project') }}</td><td>{{ $row->operator?->name ?? '-' }}</td>
                    <td>{{ $row->assigned_from->toDateString() }}</td><td>{{ $row->assigned_until?->toDateString() ?? '—' }}</td>
                    <td class="small">{{ $row->start_meter !== null ? number_format((float) $row->start_meter, 1) : '-' }} → {{ $row->end_meter !== null ? number_format((float) $row->end_meter, 1) : '-' }}</td>
                    <td class="small">{{ $row->assigner?->name ?? '-' }}</td><td class="small">{{ $row->notes ?? '-' }}</td>
                    <td><span class="badge {{ $row->is_active ? 'green' : 'gray' }}">{{ __($row->is_active ? 'Active' : 'Closed') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No assignment history yet.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer><span class="small">{{ __('Showing :from-:to of :total', ['from' => $assignmentHistory->firstItem() ?? 0, 'to' => $assignmentHistory->lastItem() ?? 0, 'total' => $assignmentHistory->total()]) }}</span>@if ($assignmentHistory->hasPages()){{ $assignmentHistory->links() }}@endif</x-slot:footer>
    </x-admin.data-table>

    {{-- Fuel --}}
    <x-admin.data-table :title="__('Fuel')" :subtitle="__('Total = quantity × rate (server-calculated). A fuel entry becomes project cost only when Finance posts it (Cash / Bank journal) or the supplier bill it creates is posted.')" id="fuel">
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Project / Site') }}</th><th>{{ __('Meter') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Category') }}</th><th>{{ __('Accounting') }}</th><th>{{ __('Receipt') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($fuelLogs as $log)
                <tr>
                    <td>{{ $log->fuel_number }}</td><td>{{ $log->fuel_date->toDateString() }}</td>
                    <td class="small">{{ $log->project?->code ?? __('No project') }}{{ $log->site ? ' / '.$log->site->name : '' }}</td>
                    <td>{{ $log->meter_reading !== null ? number_format((float) $log->meter_reading, 1) : '-' }}</td>
                    <td>{{ number_format((float) $log->quantity, 3) }} {{ $log->unit }}</td><td>{{ number_format((float) $log->rate, 4) }}</td>
                    <td><strong>{{ $canFinance || $canPost ? number_format((float) $log->total_amount, 2) : __('Restricted') }}</strong>{{ (float) $log->vat_amount > 0 ? ' '.__('(incl. VAT)') : '' }}</td>
                    <td class="small">{{ __($log->payment_type) }}{{ $log->supplier ? ' · '.$log->supplier->name : '' }}{{ $log->paymentAccount ? ' · '.$log->paymentAccount->account_name : '' }}</td>
                    <td class="small">{{ $log->category?->name ?? '-' }}</td>
                    <td><span class="badge {{ $accClass($log->accounting_status) }}">{{ __($log->accountingLabel()) }}</span>
                        @if ($log->journalEntry && $canViewJournal)<div class="small"><a href="{{ route('admin.accounting.journal-entries.show', $log->journalEntry) }}">{{ $log->journalEntry->journal_number }}</a></div>@endif
                        @if ($log->supplierBill && $canViewBill)<div class="small"><a href="{{ route('admin.accounting.accounts-payable.show', $log->supplierBill) }}">Bill {{ $log->supplierBill->bill_number }}</a></div>@endif
                        @if ($log->posting_error)<div class="small" style="color:var(--red)">{{ $log->posting_error }}</div>@endif</td>
                    <td>@if ($log->document)<a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $log->document->id]) }}">Receipt</a>@else -@endif</td>
                    <td>@if ($canPost && ! $log->isFinanciallyLinked())<form method="POST" action="{{ route('admin.equipment.fuel.post', [$asset, $log->id]) }}">@csrf<button type="submit" class="btn sm primary">{{ __($log->accounting_status === 'failed' ? 'Retry posting' : ($log->payment_type === 'Supplier Credit' ? 'Create supplier bill' : 'Post to accounting')) }}</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No fuel entries have been recorded.{{ $canRecord ? ' Use the form below.' : '' }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">{{ __('Showing :from-:to of :total', ['from' => $fuelLogs->firstItem() ?? 0, 'to' => $fuelLogs->lastItem() ?? 0, 'total' => $fuelLogs->total()]) }}</span>@if ($fuelLogs->hasPages()){{ $fuelLogs->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>
    @if ($canRecord)
        <div class="card" style="padding:16px;margin-bottom:16px" id="add-fuel">
            <h3>{{ __('Record Fuel') }}</h3>
            <form method="POST" action="{{ route('admin.equipment.fuel.store', $asset) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end">
                @csrf
                <div><label for="fuel_date">{{ __('Date *') }}</label><input id="fuel_date" name="fuel_date" type="date" class="input" required value="{{ old('fuel_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                <div><label for="fuel_qty">{{ __('Quantity *') }}</label><input id="fuel_qty" name="quantity" type="number" step="0.001" min="0.001" class="input" required value="{{ old('quantity') }}"/></div>
                <div><label for="fuel_unit">{{ __('Unit') }}</label><select id="fuel_unit" name="unit" class="select"><option value="litre">{{ __('Litre') }}</option><option value="gallon" @selected(old('unit') === 'gallon')>{{ __('Gallon') }}</option></select></div>
                <div><label for="fuel_rate">{{ __('Rate (SAR / unit) *') }}</label><input id="fuel_rate" name="rate" type="number" step="0.0001" min="0" class="input" required value="{{ old('rate') }}"/></div>
                <div><label for="fuel_vat">{{ __('VAT rate') }}</label><select id="fuel_vat" name="vat_rate" class="select"><option value="0">0%</option><option value="15" @selected(old('vat_rate') === '15')>15%</option></select></div>
                <div><label for="fuel_meter">{{ __('Meter reading') }}</label><input id="fuel_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                <div><label for="fuel_corr">{{ __('Meter correction') }}</label><select id="fuel_corr" name="meter_correction" class="select"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes (logged)') }}</option></select></div>
                <div><label for="fuel_pay">{{ __('Payment *') }}</label><select id="fuel_pay" name="payment_type" class="select" required>@foreach (['Cash', 'Bank', 'Supplier Credit'] as $type)<option value="{{ $type }}" @selected(old('payment_type', 'Cash') === $type)>{{ __($type) }}</option>@endforeach</select></div>
                <div><label for="fuel_acc">{{ __('Paid from (Cash / Bank)') }}</label><select id="fuel_acc" name="payment_account_id" class="select"><option value="">{{ __('Select account') }}</option>@foreach ($cashAccounts->merge($bankAccounts) as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->label() }}</option>@endforeach</select></div>
                <div><label for="fuel_sup">{{ __('Supplier (Supplier Credit)') }}</label><select id="fuel_sup" name="supplier_id" class="select"><option value="">{{ __('None') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label for="fuel_cat">{{ __('Expense category *') }}</label><select id="fuel_cat" name="expense_category_id" class="select" required><option value="">{{ __('Select category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="fuel_ref">{{ __('Invoice / reference') }}</label><input id="fuel_ref" name="reference_number" class="input" maxlength="100" value="{{ old('reference_number') }}"/></div>
                <div><label for="fuel_receipt">{{ __('Receipt (jpg / png / pdf)') }}</label><input id="fuel_receipt" name="receipt" type="file" class="input" accept=".jpg,.jpeg,.png,.pdf"/></div>
                <div style="grid-column:span 2"><label for="fuel_notes">{{ __('Notes') }}</label><input id="fuel_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                <div><button type="submit" class="btn sm primary">{{ __('Record Fuel') }}</button></div>
            </form>
            <p class="small">{{ __('Project and site come from the active assignment at the time of the entry. The expense category\'s account decides the posting; Cash / Bank needs the account paid from, Supplier Credit needs the supplier and creates a draft supplier bill when Finance posts.') }}</p>
        </div>
    @endif

    {{-- Maintenance --}}
    <x-admin.data-table :title="__('Maintenance')" :subtitle="__('Open → Completed (or Cancelled). Completed records stay in the history; cost is posted by Finance after completion.')" id="maintenance">
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Workshop / supplier') }}</th><th>{{ __('Meter') }}</th><th>{{ __('Cost') }}</th><th>{{ __('Next service') }}</th><th>{{ __('Status') }}</th><th>{{ __('Accounting') }}</th><th>{{ __('Invoice') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($maintenance as $row)
                <tr>
                    <td>{{ $row->maintenance_number }}</td><td>{{ $row->service_date->toDateString() }}</td><td>{{ __($row->typeLabel()) }}</td>
                    <td class="small">{{ $row->description }}</td><td class="small">{{ $row->supplier?->name ?? '-' }}{{ $row->payment_type ? ' · '.$row->payment_type : '' }}</td>
                    <td>{{ $row->meter_reading !== null ? number_format((float) $row->meter_reading, 1) : '-' }}</td>
                    <td><strong>{{ $canFinance || $canPost ? number_format((float) $row->total_amount, 2) : __('Restricted') }}</strong></td>
                    <td class="small">{{ $row->next_service_date?->toDateString() ?? '' }}{{ $row->next_service_meter !== null ? ' · '.number_format((float) $row->next_service_meter, 1).' '.$asset->meterUnit() : '' }}{{ ! $row->next_service_date && $row->next_service_meter === null ? '-' : '' }}</td>
                    <td><x-admin.status-badge :status="$row->status"/></td>
                    <td>@if ($row->status !== 'cancelled')<span class="badge {{ $accClass($row->accounting_status) }}">{{ __($row->accountingLabel()) }}</span>
                        @if ($row->journalEntry && $canViewJournal)<div class="small"><a href="{{ route('admin.accounting.journal-entries.show', $row->journalEntry) }}">{{ $row->journalEntry->journal_number }}</a></div>@endif
                        @if ($row->supplierBill && $canViewBill)<div class="small"><a href="{{ route('admin.accounting.accounts-payable.show', $row->supplierBill) }}">Bill {{ $row->supplierBill->bill_number }}</a></div>@endif
                        @if ($row->posting_error)<div class="small" style="color:var(--red)">{{ $row->posting_error }}</div>@endif
                        @else -@endif</td>
                    <td>@if ($row->document)<a class="btn sm outline" href="{{ route('admin.equipment.documents.file', [$asset, $row->document->id]) }}">Invoice</a>@else -@endif</td>
                    <td>
                        <div class="actions">
                            @if ($row->status === 'open' && $canEdit)
                                <form method="POST" action="{{ route('admin.equipment.maintenance.complete', [$asset, $row->id]) }}" style="display:flex;gap:4px;align-items:end;flex-wrap:wrap">
                                    @csrf
                                    <input aria-label="{{ __('review.final_cost') }}" name="cost" type="number" step="0.01" min="0" class="input" style="width:110px" placeholder="{{ __('review.final_cost') }}" value="{{ $row->taxable_amount > 0 ? $row->taxable_amount : '' }}"/>
                                    <input aria-label="{{ __('review.meter_reading') }}" name="meter_reading" type="number" step="0.1" min="0" class="input" style="width:100px" placeholder="{{ __('review.meter_reading') }}"/>
                                    <details><summary>{{ __('review.payment_details') }}</summary>
                                        <label>{{ __('workspace.payment_type') }}<select name="payment_type" class="select">@foreach (\App\Models\EquipmentMaintenanceRecord::PAYMENT_TYPES as $type)<option value="{{ $type }}" @selected($row->payment_type === $type)>{{ __($type) }}</option>@endforeach</select></label>
                                        <label>{{ __('review.expense_category') }}<select name="expense_category_id" class="select"><option value="">{{ __('review.select_category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($row->expense_category_id == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                                        <label>{{ __('review.paid_from') }}<select name="payment_account_id" class="select"><option value="">{{ __('review.select_account') }}</option>@foreach ($cashAccounts->merge($bankAccounts) as $account)<option value="{{ $account->id }}" @selected($row->payment_account_id == $account->id)>{{ $account->label() }}</option>@endforeach</select></label>
                                        <label>{{ __('review.supplier') }}<select name="supplier_id" class="select"><option value="">{{ __('review.select_supplier') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($row->supplier_id == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
                                    </details>
                                    <button type="submit" class="btn sm primary">{{ __('review.complete') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.equipment.maintenance.cancel', [$asset, $row->id]) }}" onsubmit="return confirm('Cancel {{ $row->maintenance_number }}? It stays in the history and is never posted.');">@csrf<button type="submit" class="btn sm outline">{{ __('Cancel') }}</button></form>
                            @endif
                            @if ($row->status === 'completed' && $canPost && ! $row->isFinanciallyLinked() && (float) $row->taxable_amount > 0)
                                <form method="POST" action="{{ route('admin.equipment.maintenance.post', [$asset, $row->id]) }}">@csrf<button type="submit" class="btn sm primary">{{ __($row->accounting_status === 'failed' ? 'Retry posting' : ($row->payment_type === 'Supplier Credit' ? 'Create supplier bill' : 'Post to accounting')) }}</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="table-empty">No maintenance history exists yet.{{ $canRecord ? ' Use the form below to record a service or repair.' : '' }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">{{ __('Showing :from-:to of :total', ['from' => $maintenance->firstItem() ?? 0, 'to' => $maintenance->lastItem() ?? 0, 'total' => $maintenance->total()]) }}</span>@if ($maintenance->hasPages()){{ $maintenance->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>
    @if ($canRecord)
        <div class="card" style="padding:16px;margin-bottom:16px" id="add-maintenance">
            <h3>{{ __('Record Maintenance / Service') }}</h3>
            <form method="POST" action="{{ route('admin.equipment.maintenance.store', $asset) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end">
                @csrf
                <div><label for="mnt_date">{{ __('Service date *') }}</label><input id="mnt_date" name="service_date" type="date" class="input" required value="{{ old('service_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
                <div><label for="mnt_type">{{ __('Type *') }}</label><select id="mnt_type" name="maintenance_type" class="select" required>@foreach ($maintenanceTypes as $key => $label)<option value="{{ $key }}" @selected(old('maintenance_type', 'repair') === $key)>{{ __($label) }}</option>@endforeach</select></div>
                <div style="grid-column:span 2"><label for="mnt_desc">{{ __('Description *') }}</label><input id="mnt_desc" name="description" class="input" maxlength="2000" required value="{{ old('description') }}"/></div>
                <div><label for="mnt_cost">{{ __('Cost before VAT (SAR)') }}</label><input id="mnt_cost" name="cost" type="number" step="0.01" min="0" class="input" value="{{ old('cost') }}"/></div>
                <div><label for="mnt_vat">{{ __('VAT rate') }}</label><select id="mnt_vat" name="vat_rate" class="select"><option value="0">0%</option><option value="15" @selected(old('vat_rate') === '15')>15%</option></select></div>
                <div><label for="mnt_meter">{{ __('Meter reading') }}</label><input id="mnt_meter" name="meter_reading" type="number" step="0.1" min="0" class="input" value="{{ old('meter_reading') }}" placeholder="{{ number_format((float) $asset->current_meter, 1) }}"/></div>
                <div><label for="mnt_corr">{{ __('Meter correction') }}</label><select id="mnt_corr" name="meter_correction" class="select"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes (logged)') }}</option></select></div>
                <div><label for="mnt_next_date">{{ __('Next service date') }}</label><input id="mnt_next_date" name="next_service_date" type="date" class="input" value="{{ old('next_service_date') }}"/></div>
                <div><label for="mnt_next_meter">{{ __('Next service at meter') }}</label><input id="mnt_next_meter" name="next_service_meter" type="number" step="0.1" min="0" class="input" value="{{ old('next_service_meter') }}"/></div>
                <div><label for="mnt_pay">{{ __('Payment') }}</label><select id="mnt_pay" name="payment_type" class="select">@foreach (['Cash', 'Bank', 'Supplier Credit'] as $type)<option value="{{ $type }}" @selected(old('payment_type', 'Cash') === $type)>{{ __($type) }}</option>@endforeach</select></div>
                <div><label for="mnt_acc">{{ __('Paid from (Cash / Bank)') }}</label><select id="mnt_acc" name="payment_account_id" class="select"><option value="">{{ __('Select account') }}</option>@foreach ($cashAccounts->merge($bankAccounts) as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->label() }}</option>@endforeach</select></div>
                <div><label for="mnt_sup">{{ __('Workshop / supplier') }}</label><select id="mnt_sup" name="supplier_id" class="select"><option value="">{{ __('None') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label for="mnt_cat">{{ __('Expense category') }}</label><select id="mnt_cat" name="expense_category_id" class="select"><option value="">{{ __('Select category') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="mnt_ref">{{ __('Invoice / reference') }}</label><input id="mnt_ref" name="reference_number" class="input" maxlength="100" value="{{ old('reference_number') }}"/></div>
                <div><label for="mnt_file">{{ __('Invoice file (jpg / png / pdf)') }}</label><input id="mnt_file" name="invoice" type="file" class="input" accept=".jpg,.jpeg,.png,.pdf"/></div>
                <div><label for="mnt_out">{{ __('Take out of service') }}</label><select id="mnt_out" name="take_out_of_service" class="select"><option value="0">{{ __('No') }}</option><option value="1" @selected(old('take_out_of_service') === '1')>{{ __('Yes — status Under Maintenance until completed') }}</option></select></div>
                <div style="grid-column:span 2"><label for="mnt_notes">{{ __('Notes') }}</label><input id="mnt_notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                <div><button type="submit" class="btn sm primary">{{ __('Record Maintenance') }}</button></div>
            </form>
        </div>
    @endif

    {{-- Costs --}}
    <x-admin.data-table :title="__('Costs')" :subtitle="__('Equipment cost reaches the Project Cost Report and the project budget only through posted accounting (Cash / Bank journal or the posted supplier bill), never from the operational totals below.')" class="detail-table" id="costs">
        <tbody>
            @if ($canFinance && $costs)
                <tr><th>{{ __('Fuel — posted') }}</th><td>{{ $money($costs['fuel']->posted) }}</td></tr>
                <tr><th>{{ __('Fuel — recorded, not yet posted') }}</th><td>{{ $money($costs['fuel']->unposted) }} ({{ $costs['fuel']->entries }} entries in total)</td></tr>
                <tr><th>{{ __('Maintenance — posted') }}</th><td>{{ $money($costs['maintenance']->posted) }}</td></tr>
                <tr><th>{{ __('Maintenance — recorded, not yet posted') }}</th><td>{{ $money($costs['maintenance']->unposted) }} ({{ $costs['maintenance']->entries }} records, cancelled excluded)</td></tr>
                <tr><th>{{ __('Posted equipment cost (this asset)') }}</th><td><strong>{{ $money($costs['fuel']->posted + $costs['maintenance']->posted) }}</strong></td></tr>
                <tr><th>{{ __('Where it counts') }}</th><td class="small">{{ __('Each posted journal carries the project / site of the assignment at the time of the entry, so the Project Cost Report and Budget vs Actual (Equipment category, mapped accounts) count it once. Supplier-credit records count through their supplier bill, never twice.') }}</td></tr>
            @else
                <tr><td colspan="2" class="table-empty">{{ __('Cost figures are shown to users with Financial Reports view.') }}</td></tr>
            @endif
        </tbody>
    </x-admin.data-table>

    {{-- Documents --}}
    <x-admin.data-table :title="__('Documents')" :subtitle="__('Private files (registration, insurance, inspection, permits, invoices, receipts). Served only to authorized users; no public links.')" id="documents">
        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Number') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('File') }}</th><th>{{ __('Uploaded') }}</th><th>{{ __('Notes') }}</th></tr></thead>
        <tbody>
            @forelse ($documents as $doc)
                @php $state = $doc->expiryState(); @endphp
                <tr>
                    <td>{{ __($doc->typeLabel()) }}</td><td>{{ $doc->document_number ?? '-' }}</td><td>{{ $doc->issue_date?->toDateString() ?? '-' }}</td>
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
                    <div><label for="doc_type">{{ __('Type *') }}</label><select id="doc_type" name="document_type" class="select" required>@foreach ($documentTypes as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach</select></div>
                    <div><label for="doc_number">{{ __('Number') }}</label><input id="doc_number" name="document_number" class="input" maxlength="80"/></div>
                    <div><label for="doc_issue">{{ __('Issued') }}</label><input id="doc_issue" name="issue_date" type="date" class="input"/></div>
                    <div><label for="doc_expiry">{{ __('Expiry') }}</label><input id="doc_expiry" name="expiry_date" type="date" class="input"/></div>
                    <div><label for="doc_file">{{ __('File *') }}</label><input id="doc_file" name="file" type="file" class="input" required accept=".jpg,.jpeg,.png,.pdf"/></div>
                    <div><label for="doc_notes">{{ __('Notes') }}</label><input id="doc_notes" name="notes" class="input" maxlength="500"/></div>
                    <button type="submit" class="btn sm primary">{{ __('Upload') }}</button>
                </form>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Tracking / GPS --}}
    <x-admin.data-table :title="__('Tracking / GPS')" class="detail-table" id="tracking">
        <tbody>
            <tr><th>{{ __('Live tracking') }}</th><td><span class="badge gray">{{ __('Not connected') }}</span> {{ __('Live GPS tracking is not connected to a telematics provider. No position, route, speed, idle time or geofence event is recorded or shown for this asset.') }}</td></tr>
            <tr><th>{{ __('Tracker readiness') }}</th><td>{{ $asset->tracker_device_id ? ($asset->tracker_provider ?: 'Provider not set').' · device '.$asset->tracker_device_id.' (stored for the future integration; not polled)' : 'No tracker device recorded.' }}</td></tr>
            <tr><th>{{ __('What exists today') }}</th><td class="small">{{ __('Project / site assignment, operator, meter readings from assignments, fuel and maintenance, and the site geofence settings used by mobile attendance. The provider integration contract is documented for the next phase.') }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Activity --}}
    @if ($activity !== null)
        <x-admin.data-table :title="__('Activity')" :subtitle="__('Latest entries for this asset, limited to the users your role may see')" id="activity">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Details') }}</th></tr></thead>
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
        {{ __('Equipment Phase 1: asset master, assignment history, fuel, maintenance, private documents and accounting through Expense Categories and the Finance payment catalogue. Live GPS / routes / speed / idle time, utilization analytics and depreciation are NOT YET OPERATIONAL.') }}
    </div>
@endsection
