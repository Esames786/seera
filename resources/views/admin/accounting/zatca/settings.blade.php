@extends('layouts.admin')

@section('title', __('zatca.settings_title'))
@section('breadcrumb', 'Accounting / '.__('zatca.title').' / '.__('zatca.settings_title'))

@section('content')
    <x-admin.page-header :title="__('zatca.settings_title')" :description="__('zatca.env_'.$environment).' · '.$baseUrl">
        <a class="btn outline" href="{{ route('admin.accounting.zatca.index') }}">Back to register</a>
    </x-admin.page-header>

    @if ($environment === 'production' && ! $productionEnabled)
        <div class="alert flash">{{ __('zatca.production_disabled_note') }}</div>
    @endif
    <div class="note">{{ __('zatca.onboarding_steps') }} {{ __('zatca.secrets_hidden') }}</div>
    <br/>

    <div class="split even">
        <x-admin.data-table :title="__('zatca.seller_readiness')">
            <tbody>
                <tr><th>Name</th><td>{{ $seller['name'] ?: '-' }} @if ($seller['name_ar'])<span class="small" dir="rtl">({{ $seller['name_ar'] }})</span>@endif</td></tr>
                <tr><th>VAT</th><td>{{ $seller['vat_number'] ?: '-' }}</td></tr>
                <tr><th>{{ $seller['id_scheme'] }}</th><td>{{ $seller['id_value'] ?: '-' }}</td></tr>
                <tr><th>Address</th><td>{{ implode(', ', array_filter([$seller['building_number'], $seller['street'], $seller['district'], $seller['city'], $seller['postal_code'], $seller['country_code']])) ?: '-' }}</td></tr>
                @forelse ($sellerReadiness['errors'] as $item)
                    <tr><td colspan="2"><span class="badge red">{{ $item['rule'] }}</span> {{ $item['message'] }}</td></tr>
                @empty
                    <tr><td colspan="2"><span class="badge green">{{ __('zatca.ready') }}</span></td></tr>
                @endforelse
                @foreach ($sellerReadiness['warnings'] as $item)
                    <tr><td colspan="2"><span class="badge yellow">{{ $item['rule'] }}</span> {{ $item['message'] }}</td></tr>
                @endforeach
            </tbody>
            <x-slot:footer><a class="btn sm outline" href="{{ route('admin.master.company-profile') }}">Company Profile</a></x-slot:footer>
        </x-admin.data-table>

        <x-admin.data-table :title="__('zatca.unit')">
            <tbody>
                <tr><td>
                    <form method="POST" action="{{ route('admin.accounting.zatca.settings.store') }}" class="form-grid" data-no-dirty-guard>
                        @csrf
                        @php $editing = $units->first(fn ($u) => $u->status !== 'production' && $u->status !== 'revoked'); @endphp
                        @if ($editing)<input type="hidden" name="unit_id" value="{{ $editing->id }}"/>@endif
                        <div><label for="unit_name">{{ __('zatca.unit_name') }} *</label><input id="unit_name" name="name" class="input" required maxlength="150" value="{{ old('name', $editing?->name ?? 'Seera ERP main unit') }}"/></div>
                        <div><label for="invoice_types">{{ __('zatca.unit_invoice_types') }} *</label><input id="invoice_types" name="invoice_types" class="input" required pattern="[01]{4}" value="{{ old('invoice_types', $editing?->invoice_types ?? '1100') }}"/></div>
                        <div><label for="location">{{ __('zatca.unit_location') }}</label><input id="location" name="location" class="input" maxlength="191" value="{{ old('location', $editing?->location) }}"/></div>
                        <div><label for="industry">{{ __('zatca.unit_industry') }}</label><input id="industry" name="industry" class="input" maxlength="100" value="{{ old('industry', $editing?->industry ?? 'Construction') }}"/></div>
                        <div><label for="organization_unit">{{ __('zatca.unit_org_unit') }}</label><input id="organization_unit" name="organization_unit" class="input" maxlength="191" value="{{ old('organization_unit', $editing?->organization_unit) }}"/></div>
                        <div class="full form-actions"><button class="btn primary">{{ __('ui.save') }}</button></div>
                    </form>
                </td></tr>
            </tbody>
        </x-admin.data-table>
    </div>

    @forelse ($units as $unit)
        <x-admin.data-table :title="$unit->name.' — '.$unit->statusLabel()" class="detail-table">
            <tbody>
                <tr><th>{{ __('zatca.environment') }}</th><td>{{ __('zatca.env_'.$unit->environment) }}</td></tr>
                <tr><th>{{ __('zatca.unit_invoice_types') }}</th><td>{{ $unit->invoice_types }} ({{ $unit->supportsStandard() ? 'T' : '' }}{{ $unit->supportsSimplified() ? 'S' : '' }})</td></tr>
                <tr><th>{{ __('zatca.unit_vat') }} / {{ __('zatca.unit_serial') }}</th><td>{{ $unit->vat_number ?? '-' }} / <span class="small">{{ $unit->egs_serial ?? '-' }}</span></td></tr>
                <tr><th>{{ __('zatca.csr') }}</th><td>{{ $unit->csr_generated_at ? $unit->csr_generated_at->format('Y-m-d H:i') : '-' }}@if ($unit->csr) <details><summary class="small">CSR (PEM)</summary><pre class="small" style="white-space:pre-wrap;word-break:break-all">{{ $unit->csr }}</pre></details>@endif</td></tr>
                <tr><th>Compliance CSID</th><td>{{ $unit->compliance_issued_at ? $unit->compliance_issued_at->format('Y-m-d H:i').' · request '.$unit->compliance_request_id : '-' }}</td></tr>
                <tr><th>{{ __('zatca.compliance_checks') }}</th><td>
                    @forelse ($unit->compliance_checks ?? [] as $label => $check)
                        <div><x-admin.status-badge :status="$check['outcome']"/> {{ $label }} — {{ $check['message'] ?? '' }} <span class="small">({{ $check['warnings'] }} warnings, {{ $check['errors'] }} errors)</span></div>
                    @empty
                        -
                    @endforelse
                </td></tr>
                <tr><th>Production CSID</th><td>{{ $unit->production_issued_at ? $unit->production_issued_at->format('Y-m-d H:i') : '-' }} @if ($unit->certificate_expires_at)· {{ __('zatca.certificate_expires') }} {{ $unit->certificate_expires_at->format('Y-m-d') }}@endif</td></tr>
                <tr><th>{{ __('zatca.chain') }}</th><td>{{ __('zatca.icv') }} {{ $unit->icv_counter }} · <span class="small" style="word-break:break-all">{{ $unit->last_invoice_hash ?? \App\Models\ZatcaEgsUnit::FIRST_PREVIOUS_HASH }}</span></td></tr>
                @if ($unit->last_error)<tr><th>{{ __('zatca.last_error') }}</th><td>{{ $unit->last_error }}</td></tr>@endif
            </tbody>
            <x-slot:footer>
                @if ($unit->status !== 'revoked')
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                        @if ($unit->status !== 'production')
                            <form method="POST" action="{{ route('admin.accounting.zatca.settings.csr', $unit) }}">@csrf<button class="btn sm outline">{{ __('zatca.generate_csr') }}</button></form>
                        @endif
                        @if ($unit->csr && $unit->status !== 'production')
                            <form method="POST" action="{{ route('admin.accounting.zatca.settings.compliance', $unit) }}" style="display:flex;gap:6px;align-items:flex-end" data-no-dirty-guard>@csrf
                                <div><label for="otp-{{ $unit->id }}" class="small">{{ __('zatca.otp') }}</label><input id="otp-{{ $unit->id }}" name="otp" class="input" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required style="width:120px"/></div>
                                <button class="btn sm primary">{{ __('zatca.request_compliance') }}</button>
                            </form>
                        @endif
                        @if ($unit->hasComplianceCredential() && $unit->status !== 'production')
                            <form method="POST" action="{{ route('admin.accounting.zatca.settings.checks', $unit) }}">@csrf<button class="btn sm outline">{{ __('zatca.run_checks') }}</button></form>
                            <form method="POST" action="{{ route('admin.accounting.zatca.settings.production', $unit) }}">@csrf<button class="btn sm primary">{{ __('zatca.request_production') }}</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.accounting.zatca.settings.revoke', $unit) }}">@csrf<button class="btn sm danger">{{ __('zatca.revoke') }}</button></form>
                    </div>
                @endif
            </x-slot:footer>
        </x-admin.data-table>
    @empty
        <div class="note">{{ __('zatca.no_unit') }}</div>
    @endforelse
@endsection
