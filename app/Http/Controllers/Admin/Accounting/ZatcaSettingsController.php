<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ZatcaEgsUnit;
use App\Services\Zatca\ZatcaEnvironment;
use App\Services\Zatca\ZatcaException;
use App\Services\Zatca\ZatcaOnboardingService;
use App\Services\Zatca\ZatcaReadiness;
use App\Services\Zatca\ZatcaSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner / Finance-admin control of the ZATCA solution unit and its
 * onboarding (ZATCA Invoicing — edit). Secrets are written, never read
 * back; the OTP is used once and not stored.
 */
class ZatcaSettingsController extends Controller
{
    public function __construct(private readonly ZatcaOnboardingService $onboarding, private readonly ZatcaEnvironment $environment, private readonly ZatcaSnapshot $snapshot, private readonly ZatcaReadiness $readiness) {}

    public function index(): View
    {
        $environment = $this->environment->current();
        $seller = $this->snapshot->seller();

        return view('admin.accounting.zatca.settings', [
            'environment' => $environment,
            'productionEnabled' => (bool) config('zatca.production_enabled', false),
            'units' => ZatcaEgsUnit::where('environment', $environment)->orderByDesc('id')->get(),
            'seller' => $seller,
            'sellerReadiness' => $this->readiness->seller($seller),
            'baseUrl' => $this->safeBaseUrl($environment),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'invoice_types' => ['required', 'regex:/^[01]{4}$/', 'not_in:0000'],
            'location' => ['nullable', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:100'],
            'organization_unit' => ['nullable', 'string', 'max:191'],
            'unit_id' => ['nullable', 'integer', Rule::exists('zatca_egs_units', 'id')],
        ]);
        $environment = $this->environment->current();
        $unit = isset($data['unit_id']) ? ZatcaEgsUnit::whereKey($data['unit_id'])->where('environment', $environment)->firstOrFail() : new ZatcaEgsUnit(['environment' => $environment, 'created_by' => $request->user()->id]);
        if ($unit->exists && $unit->status === ZatcaEgsUnit::STATUS_PRODUCTION && $unit->invoice_types !== $data['invoice_types']) {
            return back()->withErrors(['zatca' => __('zatca.err_unit_in_production')]);
        }
        $unit->fill(['name' => $data['name'], 'invoice_types' => $data['invoice_types'], 'location' => $data['location'] ?? null, 'industry' => $data['industry'] ?? null, 'organization_unit' => $data['organization_unit'] ?? null])->save();
        ActivityLog::record($request, 'ZATCA', 'Saved ZATCA unit', '[EGS #'.$unit->id.'] '.$unit->name.' ('.$environment.')');

        return redirect()->route('admin.accounting.zatca.settings.index')->with('status', __('zatca.saved'));
    }

    public function csr(Request $request, ZatcaEgsUnit $unit): RedirectResponse
    {
        return $this->act(fn () => $this->onboarding->generateCsr($unit, $request->user()), __('zatca.csr_done'));
    }

    public function compliance(Request $request, ZatcaEgsUnit $unit): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);

        return $this->act(fn () => $this->onboarding->requestComplianceCsid($unit, $data['otp'], $request->user()), __('zatca.compliance_done'));
    }

    public function checks(Request $request, ZatcaEgsUnit $unit): RedirectResponse
    {
        return $this->act(fn () => $this->onboarding->runComplianceChecks($unit, $request->user()), __('zatca.checks_done'));
    }

    public function production(Request $request, ZatcaEgsUnit $unit): RedirectResponse
    {
        return $this->act(fn () => $this->onboarding->requestProductionCsid($unit, $request->user()), __('zatca.production_done'));
    }

    public function revoke(Request $request, ZatcaEgsUnit $unit): RedirectResponse
    {
        return $this->act(fn () => $this->onboarding->revokeLocally($unit, $request->user()), __('zatca.revoked_done'));
    }

    private function act(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ZatcaException $exception) {
            return redirect()->route('admin.accounting.zatca.settings.index')->withErrors(['zatca' => $exception->getMessage()])->with('zatca_details', $exception->details);
        }

        return redirect()->route('admin.accounting.zatca.settings.index')->with('status', $message);
    }

    private function safeBaseUrl(string $environment): string
    {
        try {
            return $this->environment->baseUrl($environment);
        } catch (ZatcaException $exception) {
            return $exception->getMessage();
        }
    }
}
