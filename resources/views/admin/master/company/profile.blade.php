@extends('layouts.admin')

@section('title', 'Company Profile')
@section('breadcrumb', 'Master Setup / Company Profile')

@section('content')
    <x-admin.page-header title="Company Profile" description="Central company information, Saudi VAT, ZATCA, and fiscal settings"/>

    <form method="POST" action="{{ route('admin.master.company-profile.update') }}">
        @csrf
        @method('PUT')

        <x-admin.form-section title="Basic Company Information" columns="3">
            <div><label for="name">Company Name *</label><input id="name" name="name" class="input" value="{{ old('name', $company->name) }}" required/></div>
            <div><label for="name_ar">Arabic Company Name</label><input id="name_ar" name="name_ar" class="input" dir="rtl" value="{{ old('name_ar', $company->name_ar) }}"/></div>
            <div><label>Company Logo</label><input type="file" class="input" disabled title="Logo upload is enabled in a later phase"/></div>
            <div><label for="email">Email *</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $company->email) }}"/></div>
            <div><label for="phone">Phone *</label><input id="phone" name="phone" class="input" value="{{ old('phone', $company->phone) }}" placeholder="+966 5X XXX XXXX"/></div>
            <div><label for="website">Website</label><input id="website" name="website" class="input" value="{{ old('website', $company->website) }}"/></div>
        </x-admin.form-section>

        <x-admin.form-section title="Saudi Compliance & ZATCA" columns="3">
            <div><label for="cr_number">CR Number *</label><input id="cr_number" name="cr_number" class="input" value="{{ old('cr_number', $company->cr_number) }}" placeholder="1010XXXXXX"/></div>
            <div><label for="vat_number">VAT Number *</label><input id="vat_number" name="vat_number" class="input" value="{{ old('vat_number', $company->vat_number) }}" placeholder="300XXXXXXXXXXXX"/></div>
            <div><label for="zatca_registration_number">ZATCA Registration Number</label><input id="zatca_registration_number" name="zatca_registration_number" class="input" value="{{ old('zatca_registration_number', $company->zatca_registration_number) }}"/></div>
            <div><label for="default_vat_rate">Default VAT Rate (%)</label><input id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" class="input" value="{{ old('default_vat_rate', $company->default_vat_rate) }}"/></div>
            <div>
                <label for="invoice_mode">Invoice Mode</label>
                <select id="invoice_mode" name="invoice_mode" class="select">
                    @foreach (['ZATCA Phase 2 - Clearance', 'ZATCA Phase 1 - Generation', 'Standard Invoicing'] as $mode)
                        <option @selected(old('invoice_mode', $company->invoice_mode) === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="certificate_status">Digital Certificate Status</label>
                <select id="certificate_status" name="certificate_status" class="select">
                    @foreach (['Active', 'Pending', 'Expired'] as $certStatus)
                        <option @selected(old('certificate_status', $company->certificate_status) === $certStatus)>{{ $certStatus }}</option>
                    @endforeach
                </select>
            </div>
        </x-admin.form-section>

        <x-admin.form-section title="Address & Fiscal Settings" columns="3">
            <div><label for="country">Country</label><input id="country" name="country" class="input" value="{{ old('country', $company->country) }}"/></div>
            <div><label for="city">City</label><input id="city" name="city" class="input" value="{{ old('city', $company->city) }}"/></div>
            <div><label for="currency">Default Currency</label><input id="currency" name="currency" class="input" value="{{ old('currency', $company->currency) }}"/></div>
            <div><label for="fiscal_year_start">Fiscal Year Start</label><input id="fiscal_year_start" name="fiscal_year_start" class="input" value="{{ old('fiscal_year_start', $company->fiscal_year_start) }}" placeholder="01 January"/></div>
            <div><label for="fiscal_year_end">Fiscal Year End</label><input id="fiscal_year_end" name="fiscal_year_end" class="input" value="{{ old('fiscal_year_end', $company->fiscal_year_end) }}" placeholder="31 December"/></div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="active" @selected(old('status', $company->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $company->status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="full"><label for="address">Full Address</label><textarea id="address" name="address" class="textarea">{{ old('address', $company->address) }}</textarea></div>
        </x-admin.form-section>

        <div class="form-actions">
            <a class="btn outline" href="{{ route('admin.dashboard') }}">Cancel</a>
            <button type="submit" class="btn primary">Save Company Profile</button>
        </div>
    </form>
@endsection
