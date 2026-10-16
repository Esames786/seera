<?php

namespace App\Services\Zatca;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owner-controlled onboarding of an EGS unit (Detailed Technical Guidelines
 * v2 §3.3): readiness -> key pair + CSR -> OTP from the FATOORA (or
 * simulation / developer) portal entered by a person -> compliance CSID ->
 * compliance checks with the required sample documents -> production CSID.
 * Nothing that needs a human on ZATCA's portal is automated; no secret is
 * logged or returned to the UI.
 */
final class ZatcaOnboardingService
{
    public function __construct(
        private readonly ZatcaEnvironment $environment,
        private readonly ZatcaCrypto $crypto,
        private readonly ZatcaClient $client,
        private readonly ZatcaSnapshot $snapshot,
        private readonly ZatcaReadiness $readiness,
        private readonly ZatcaComplianceSamples $samples,
        private readonly ZatcaStampService $stamp,
    ) {}

    /** Key pair and CSR for the unit; the private key is stored encrypted, the CSR is kept for the portal step. */
    public function generateCsr(ZatcaEgsUnit $unit, User $actor): ZatcaEgsUnit
    {
        $seller = $this->snapshot->seller();
        $ready = $this->readiness->seller($seller);
        if ($ready['errors'] !== []) {
            throw ZatcaException::readiness('seller_not_ready', $ready['errors'][0]['message'], ['errors' => $ready['errors']]);
        }
        if ($unit->status === ZatcaEgsUnit::STATUS_PRODUCTION) {
            throw ZatcaException::state('unit_in_production', __('zatca.err_unit_in_production'));
        }
        if (! preg_match('/^[01]{4}$/', (string) $unit->invoice_types) || $unit->invoice_types === '0000') {
            throw ZatcaException::configuration('invoice_types_invalid', __('zatca.err_invoice_types_invalid'));
        }
        $keys = $this->crypto->generateKeyPair();
        $serial = $unit->egs_serial ?: '1-'.config('zatca.solution.provider').'|2-'.config('zatca.solution.version').'|3-'.$unit->id.'-'.substr(md5((string) $unit->created_at), 0, 8);
        $csr = $this->crypto->generateCsr($keys['private_key'], [
            'common_name' => $unit->name, 'organization_unit' => $unit->organization_unit ?: $seller['name'], 'organization_name' => $unit->organization_name ?: $seller['name'],
            'country_code' => $unit->country_code ?: 'SA', 'egs_serial' => $serial, 'vat_number' => $unit->vat_number ?: $seller['vat_number'],
            'invoice_types' => $unit->invoice_types, 'location' => $unit->location ?: ($seller['street'].' '.$seller['city']), 'industry' => $unit->industry ?: 'Construction',
        ], $this->environment->certificateTemplateName($unit->environment));
        $unit->setSecret('private_key_encrypted', $keys['private_key']);
        $unit->forceFill([
            'public_key_pem' => $keys['public_key'], 'csr' => $csr, 'csr_generated_at' => now(), 'status' => ZatcaEgsUnit::STATUS_CSR,
            'egs_serial' => $serial, 'vat_number' => $unit->vat_number ?: $seller['vat_number'], 'organization_name' => $unit->organization_name ?: $seller['name'],
            'organization_unit' => $unit->organization_unit ?: $seller['name'],
            'compliance_request_id' => null, 'compliance_token_encrypted' => null, 'compliance_secret_encrypted' => null, 'compliance_issued_at' => null, 'compliance_checks' => null,
            'production_token_encrypted' => null, 'production_secret_encrypted' => null, 'production_issued_at' => null, 'certificate_pem' => null, 'certificate_expires_at' => null, 'last_error' => null,
        ])->save();
        $this->log($actor, 'Generated ZATCA CSR', $unit);

        return $unit;
    }

    /** Exchange the CSR and the one-time password for a compliance CSID. The OTP is used once and never stored. */
    public function requestComplianceCsid(ZatcaEgsUnit $unit, string $otp, User $actor): ZatcaEgsUnit
    {
        $this->assertEnvironment($unit);
        if (! $unit->csr || ! $unit->hasPrivateKey()) {
            throw ZatcaException::state('csr_missing', __('zatca.err_csr_missing'));
        }
        if (! preg_match('/^\d{6}$/', $otp)) {
            throw ZatcaException::readiness('otp_invalid', __('zatca.err_otp_invalid'));
        }
        try {
            $issued = $this->client->complianceCsid($unit->csr, $otp, $unit->environment);
        } catch (ZatcaException $e) {
            $unit->forceFill(['last_error' => $e->getMessage()])->save();
            $this->log($actor, 'ZATCA compliance CSID refused', $unit, 'failed', $e->getMessage());
            throw $e;
        }
        $unit->setSecret('compliance_token_encrypted', $issued['token']);
        $unit->setSecret('compliance_secret_encrypted', $issued['secret']);
        $unit->forceFill(['compliance_request_id' => $issued['request_id'], 'compliance_issued_at' => now(), 'status' => ZatcaEgsUnit::STATUS_COMPLIANCE, 'compliance_checks' => null, 'last_error' => null])->save();
        $this->log($actor, 'Obtained ZATCA compliance CSID', $unit);

        return $unit;
    }

    /**
     * Submit the required sample documents to the compliance endpoint with the
     * compliance CSID. Each document consumes an ICV and extends the hash chain
     * like any generated document. Results are stored per document type.
     */
    public function runComplianceChecks(ZatcaEgsUnit $unit, User $actor): ZatcaEgsUnit
    {
        $this->assertEnvironment($unit);
        if (! $unit->hasComplianceCredential()) {
            throw ZatcaException::state('compliance_csid_missing', __('zatca.err_compliance_csid_missing'));
        }
        $seller = $this->snapshot->seller();
        $results = [];
        $allAccepted = true;
        foreach ($this->samples->documents($unit, $seller) as $doc) {
            $label = $doc['type'].'_'.$doc['type_code'];
            try {
                $stamped = $this->stamp->stamp($unit, $doc, 'compliance');
                $result = $this->client->complianceInvoice($unit->secret('compliance_token_encrypted'), $unit->secret('compliance_secret_encrypted'), $stamped['invoice_hash'], $doc['uuid'], $stamped['xml'], $unit->environment);
                ZatcaSubmission::create(['zatca_egs_unit_id' => $unit->id, 'attempt' => 1, 'environment' => $unit->environment, 'endpoint' => 'compliance/invoices', 'invoice_hash' => $stamped['invoice_hash'],
                    'http_status' => $result->httpStatus, 'outcome' => $result->outcome, 'zatca_status' => $result->zatcaStatus, 'warnings' => $result->warnings, 'errors' => $result->errors,
                    'response_excerpt' => $result->excerpt + ['document' => $label], 'duration_ms' => $result->durationMs, 'user_id' => $actor->id]);
                $results[$label] = ['outcome' => $result->outcome, 'status' => $result->zatcaStatus, 'warnings' => count($result->warnings), 'errors' => count($result->errors), 'message' => $result->summary(), 'at' => now()->toIso8601String()];
                $allAccepted = $allAccepted && $result->accepted();
            } catch (ZatcaException $e) {
                $results[$label] = ['outcome' => 'error', 'status' => null, 'warnings' => 0, 'errors' => 1, 'message' => $e->getMessage(), 'at' => now()->toIso8601String()];
                $allAccepted = false;
            }
        }
        $unit->forceFill(['compliance_checks' => $results, 'last_error' => $allAccepted ? null : __('zatca.err_compliance_checks_failed')])->save();
        $this->log($actor, $allAccepted ? 'ZATCA compliance checks passed' : 'ZATCA compliance checks incomplete', $unit, $allAccepted ? 'success' : 'failed');

        return $unit;
    }

    public function complianceChecksPassed(ZatcaEgsUnit $unit): bool
    {
        $checks = $unit->compliance_checks ?? [];

        return $checks !== [] && collect($checks)->every(fn ($c) => in_array($c['outcome'] ?? null, [ZatcaSubmission::OUTCOME_ACCEPTED, ZatcaSubmission::OUTCOME_ACCEPTED_WITH_WARNINGS], true));
    }

    /** Production CSID for the unit's environment (the "production" credential of the sandbox is still a test credential). */
    public function requestProductionCsid(ZatcaEgsUnit $unit, User $actor): ZatcaEgsUnit
    {
        $this->assertEnvironment($unit);
        if (! $unit->hasComplianceCredential() || ! $unit->compliance_request_id) {
            throw ZatcaException::state('compliance_csid_missing', __('zatca.err_compliance_csid_missing'));
        }
        if (! $this->complianceChecksPassed($unit)) {
            throw ZatcaException::state('compliance_checks_missing', __('zatca.err_compliance_checks_missing'));
        }
        try {
            $issued = $this->client->productionCsid($unit->secret('compliance_token_encrypted'), $unit->secret('compliance_secret_encrypted'), $unit->compliance_request_id, $unit->environment);
        } catch (ZatcaException $e) {
            $unit->forceFill(['last_error' => $e->getMessage()])->save();
            $this->log($actor, 'ZATCA production CSID refused', $unit, 'failed', $e->getMessage());
            throw $e;
        }
        $unit->setSecret('production_token_encrypted', $issued['token']);
        $unit->setSecret('production_secret_encrypted', $issued['secret']);
        $certificate = $unit->signingCertificate();
        $expires = null;
        try {
            $details = $this->crypto->certificateDetails($certificate);
            $expires = $details['not_after'] ? Carbon::createFromTimestamp($details['not_after']) : null;
        } catch (ZatcaException) {
            // The token is stored regardless; expiry stays unknown.
        }
        $unit->forceFill(['production_issued_at' => now(), 'certificate_pem' => $certificate, 'certificate_expires_at' => $expires, 'status' => ZatcaEgsUnit::STATUS_PRODUCTION, 'last_error' => null])->save();
        $this->log($actor, 'Obtained ZATCA production CSID', $unit);

        return $unit;
    }

    /** Revoke locally (the portal revocation is a manual ZATCA step): credentials are discarded, the chain state is kept. */
    public function revokeLocally(ZatcaEgsUnit $unit, User $actor): ZatcaEgsUnit
    {
        DB::transaction(function () use ($unit) {
            $locked = ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => ZatcaEgsUnit::STATUS_REVOKED, 'compliance_token_encrypted' => null, 'compliance_secret_encrypted' => null,
                'production_token_encrypted' => null, 'production_secret_encrypted' => null])->save();
        });
        $this->log($actor, 'Revoked ZATCA credentials locally', $unit);

        return $unit->fresh();
    }

    private function assertEnvironment(ZatcaEgsUnit $unit): void
    {
        if ($unit->environment !== $this->environment->current()) {
            throw ZatcaException::configuration('environment_mismatch', __('zatca.err_environment_mismatch', ['unit' => $unit->environment, 'current' => $this->environment->current()]));
        }
        if ($unit->environment === ZatcaEnvironment::PRODUCTION) {
            $this->environment->assertUsable();
        }
    }

    private function log(User $actor, string $action, ZatcaEgsUnit $unit, string $status = 'success', ?string $detail = null): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'ZATCA', 'action' => $action, 'status' => $status,
            'description' => '[EGS #'.$unit->id.'] '.$unit->name.' ('.$unit->environment.')'.($detail ? ' · '.mb_substr($detail, 0, 300) : '')]);
    }
}
