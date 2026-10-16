<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.master.company.profile', [
            'company' => CompanyProfile::firstOrNew(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'zatca_registration_number' => ['nullable', 'string', 'max:100'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'invoice_mode' => ['nullable', 'string', 'max:100'],
            'certificate_status' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'fiscal_year_start' => ['nullable', 'string', 'max:30'],
            'fiscal_year_end' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            // ZATCA seller address (BR-KSA-09, 4-digit building, 5-digit postal code) and identification scheme (BR-KSA-08).
            'street' => ['nullable', 'string', 'max:191'], 'building_number' => ['nullable', 'regex:/^\d{4}$/'], 'additional_number' => ['nullable', 'regex:/^\d{4}$/'],
            'district' => ['nullable', 'string', 'max:191'], 'postal_code' => ['nullable', 'regex:/^\d{5}$/'], 'country_code' => ['nullable', 'regex:/^[A-Za-z]{2}$/'],
            'seller_id_scheme' => ['nullable', 'in:'.implode(',', CompanyProfile::SELLER_ID_SCHEMES)],
        ]);
        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        $company = CompanyProfile::firstOrNew();
        $company->fill($data)->save();

        ActivityLog::record($request, 'Company', 'Updated company profile', $company->name);

        return back()->with('status', 'Company profile saved successfully.');
    }
}
