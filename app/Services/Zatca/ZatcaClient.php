<?php

namespace App\Services\Zatca;

use App\Models\ZatcaSubmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The one HTTP client for ZATCA's e-invoicing APIs (Developer Portal Manual
 * v3 §2.3.10-2.3.11 and §3; FATOORA Portal User Manual v3 §3 endpoints):
 *  POST {base}/compliance                  OTP header, body {csr}           -> compliance CSID
 *  POST {base}/compliance/invoices         basic(compliance CSID:secret)    -> compliance checks
 *  POST {base}/production/csids            basic(compliance CSID:secret)    -> production CSID
 *  POST {base}/invoices/clearance/single   basic(production CSID:secret)    -> clearance (standard)
 *  POST {base}/invoices/reporting/single   basic(production CSID:secret)    -> reporting (simplified)
 * Headers: Accept-Version V2, Accept-Language, Clearance-Status 1 on clearance.
 * TLS verification on, bounded timeouts, no automatic retry (the caller
 * decides, because a retry must reuse the same document), structured
 * classification of every outcome, no secret ever logged.
 */
final class ZatcaClient
{
    public function __construct(private readonly ZatcaEnvironment $environment) {}

    /** @return array{request_id: string, token: string, secret: string, disposition: ?string, http_status: int} */
    public function complianceCsid(string $csrPem, string $otp, ?string $environment = null): array
    {
        $response = $this->send(fn () => $this->request($environment)->withHeaders(['OTP' => $otp])
            ->post($this->environment->baseUrl($environment).'/compliance', ['csr' => base64_encode($csrPem)]));
        $this->assertCredentialResponse($response, 'compliance_csid');
        $json = $response->json();

        return ['request_id' => (string) $json['requestID'], 'token' => (string) $json['binarySecurityToken'], 'secret' => (string) $json['secret'],
            'disposition' => $json['dispositionMessage'] ?? null, 'http_status' => $response->status()];
    }

    /** @return array{request_id: string, token: string, secret: string, disposition: ?string, http_status: int} */
    public function productionCsid(string $complianceToken, string $complianceSecret, string $complianceRequestId, ?string $environment = null): array
    {
        $response = $this->send(fn () => $this->request($environment)->withBasicAuth($complianceToken, $complianceSecret)
            ->post($this->environment->baseUrl($environment).'/production/csids', ['compliance_request_id' => $complianceRequestId]));
        $this->assertCredentialResponse($response, 'production_csid');
        $json = $response->json();

        return ['request_id' => (string) ($json['requestID'] ?? $complianceRequestId), 'token' => (string) $json['binarySecurityToken'], 'secret' => (string) $json['secret'],
            'disposition' => $json['dispositionMessage'] ?? null, 'http_status' => $response->status()];
    }

    public function complianceInvoice(string $token, string $secret, string $invoiceHash, string $uuid, string $signedXml, ?string $environment = null): ZatcaApiResult
    {
        return $this->invoiceCall('/compliance/invoices', $token, $secret, $invoiceHash, $uuid, $signedXml, false, $environment);
    }

    public function clearance(string $token, string $secret, string $invoiceHash, string $uuid, string $signedXml, ?string $environment = null): ZatcaApiResult
    {
        return $this->invoiceCall('/invoices/clearance/single', $token, $secret, $invoiceHash, $uuid, $signedXml, true, $environment);
    }

    public function reporting(string $token, string $secret, string $invoiceHash, string $uuid, string $signedXml, ?string $environment = null): ZatcaApiResult
    {
        return $this->invoiceCall('/invoices/reporting/single', $token, $secret, $invoiceHash, $uuid, $signedXml, false, $environment);
    }

    private function invoiceCall(string $path, string $token, string $secret, string $invoiceHash, string $uuid, string $signedXml, bool $clearance, ?string $environment): ZatcaApiResult
    {
        $started = microtime(true);
        $request = $this->request($environment)->withBasicAuth($token, $secret);
        if ($clearance) {
            $request = $request->withHeaders(['Clearance-Status' => '1']);
        }
        try {
            $response = $request->post($this->environment->baseUrl($environment).$path, ['invoiceHash' => $invoiceHash, 'uuid' => $uuid, 'invoice' => base64_encode($signedXml)]);
        } catch (ConnectionException $exception) {
            $duration = (int) ((microtime(true) - $started) * 1000);
            $timedOut = Str::contains(strtolower($exception->getMessage()), ['timed out', 'curl error 28', 'timeout']);

            return new ZatcaApiResult($timedOut ? ZatcaSubmission::OUTCOME_TIMEOUT : ZatcaSubmission::OUTCOME_NETWORK_ERROR, null, null, [], [], [], null,
                ['exception' => class_basename($exception)], $duration, $timedOut ? 'timeout' : 'network', $timedOut ? __('zatca.err_timeout') : __('zatca.err_network'));
        }

        return $this->classify($response, (int) ((microtime(true) - $started) * 1000));
    }

    private function classify(Response $response, int $durationMs): ZatcaApiResult
    {
        $status = $response->status();
        $json = $response->json() ?? [];
        $results = is_array($json['validationResults'] ?? null) ? $json['validationResults'] : [];
        $warnings = $this->messages($results['warningMessages'] ?? $json['warnings'] ?? []);
        $errors = $this->messages($results['errorMessages'] ?? $json['errors'] ?? []);
        $info = $this->messages($results['infoMessages'] ?? []);
        $zatcaStatus = $json['clearanceStatus'] ?? $json['reportingStatus'] ?? $json['status'] ?? $results['status'] ?? null;
        $cleared = isset($json['clearedInvoice']) && is_string($json['clearedInvoice']) ? (base64_decode($json['clearedInvoice'], true) ?: null) : null;
        $excerpt = ['http_status' => $status, 'zatca_status' => $zatcaStatus, 'validation_status' => $results['status'] ?? null, 'warning_count' => count($warnings), 'error_count' => count($errors),
            'cleared_invoice' => $cleared !== null, 'message' => is_string($json['message'] ?? null) ? Str::limit($json['message'], 300) : null];

        $outcome = match (true) {
            $status === 200 => $warnings === [] ? ZatcaSubmission::OUTCOME_ACCEPTED : ZatcaSubmission::OUTCOME_ACCEPTED_WITH_WARNINGS,
            $status === 202 => ZatcaSubmission::OUTCOME_ACCEPTED_WITH_WARNINGS,
            $status === 400 || $status === 303 || $status === 303 => ZatcaSubmission::OUTCOME_REJECTED,
            $status === 401 || $status === 403 => ZatcaSubmission::OUTCOME_AUTH_ERROR,
            $status === 429 => ZatcaSubmission::OUTCOME_RATE_LIMITED,
            $status >= 500 => ZatcaSubmission::OUTCOME_SERVER_ERROR,
            default => ZatcaSubmission::OUTCOME_REJECTED,
        };
        // A reported / cleared status that is negative overrides a 2xx body (defensive).
        if (is_string($zatcaStatus) && Str::startsWith(strtoupper($zatcaStatus), 'NOT_') && $outcome !== ZatcaSubmission::OUTCOME_REJECTED && $status < 500) {
            $outcome = ZatcaSubmission::OUTCOME_REJECTED;
        }
        $errorCode = match ($outcome) {
            ZatcaSubmission::OUTCOME_REJECTED => $status === 303 ? 'wrong_endpoint' : ($errors[0]['code'] ?? 'rejected'),
            ZatcaSubmission::OUTCOME_AUTH_ERROR => 'auth_'.$status,
            ZatcaSubmission::OUTCOME_RATE_LIMITED => 'rate_limited',
            ZatcaSubmission::OUTCOME_SERVER_ERROR => 'http_'.$status,
            default => null,
        };
        $errorMessage = match ($outcome) {
            ZatcaSubmission::OUTCOME_AUTH_ERROR => __('zatca.err_auth'),
            ZatcaSubmission::OUTCOME_RATE_LIMITED => __('zatca.err_rate_limited'),
            ZatcaSubmission::OUTCOME_SERVER_ERROR => __('zatca.err_server', ['status' => $status]),
            ZatcaSubmission::OUTCOME_REJECTED => $status === 303 ? __('zatca.err_wrong_endpoint') : ($errors[0]['message'] ?? __('zatca.err_rejected')),
            default => null,
        };

        return new ZatcaApiResult($outcome, $status, is_string($zatcaStatus) ? $zatcaStatus : null, $warnings, $errors, $info, $cleared, $excerpt, $durationMs, $errorCode, $errorMessage);
    }

    /** @return array<int, array{type: ?string, code: ?string, category: ?string, message: ?string, status: ?string}> */
    private function messages(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $out[] = ['type' => null, 'code' => null, 'category' => null, 'message' => Str::limit($item, 500), 'status' => null];
            } elseif (is_array($item)) {
                $out[] = ['type' => $item['type'] ?? null, 'code' => $item['code'] ?? null, 'category' => $item['category'] ?? null,
                    'message' => isset($item['message']) ? Str::limit((string) $item['message'], 500) : null, 'status' => $item['status'] ?? null];
            }
        }

        return $out;
    }

    private function request(?string $environment): PendingRequest
    {
        return Http::acceptJson()->asJson()
            ->withHeaders(['Accept-Version' => 'V2', 'Accept-Language' => (string) config('zatca.http.accept_language', 'en')])
            ->withOptions(['verify' => true])
            ->timeout((int) config('zatca.http.timeout_seconds', 30))
            ->connectTimeout((int) config('zatca.http.connect_timeout_seconds', 10));
    }

    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            $timedOut = Str::contains(strtolower($exception->getMessage()), ['timed out', 'curl error 28', 'timeout']);
            throw new ZatcaException($timedOut ? ZatcaException::OUTCOME_UNKNOWN : ZatcaException::NETWORK, $timedOut ? 'timeout' : 'network', $timedOut ? __('zatca.err_timeout') : __('zatca.err_network'), [], $exception);
        }
    }

    private function assertCredentialResponse(Response $response, string $step): void
    {
        $json = $response->json() ?? [];
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ZatcaException(ZatcaException::AUTH, $step.'_auth', __('zatca.err_auth'), ['http_status' => $response->status()]);
        }
        if ($response->status() === 429) {
            throw new ZatcaException(ZatcaException::RATE_LIMIT, $step.'_rate_limited', __('zatca.err_rate_limited'));
        }
        if ($response->status() >= 500) {
            throw new ZatcaException(ZatcaException::SERVER_ERROR, $step.'_server', __('zatca.err_server', ['status' => $response->status()]));
        }
        if (! $response->successful() || empty($json['binarySecurityToken']) || empty($json['secret'])) {
            $errors = $this->messages($json['errors'] ?? $json['validationResults']['errorMessages'] ?? []);
            $message = $errors[0]['message'] ?? (is_string($json['message'] ?? null) ? $json['message'] : null) ?? (is_string($json['dispositionMessage'] ?? null) ? $json['dispositionMessage'] : __('zatca.err_rejected'));
            throw new ZatcaException(ZatcaException::BUSINESS_REJECTION, $step.'_rejected', Str::limit((string) $message, 500), ['http_status' => $response->status(), 'errors' => $errors]);
        }
    }
}
