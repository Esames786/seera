<?php

namespace App\Services\Zatca;

use App\Models\ZatcaSubmission;

/**
 * Classified outcome of one ZATCA API call. Credentials and raw headers are
 * never kept; the excerpt holds only the fields support needs.
 */
final class ZatcaApiResult
{
    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $info
     * @param  array<string, mixed>  $excerpt
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ?int $httpStatus,
        public readonly ?string $zatcaStatus,
        public readonly array $warnings,
        public readonly array $errors,
        public readonly array $info,
        public readonly ?string $clearedInvoiceXml,
        public readonly array $excerpt,
        public readonly int $durationMs,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function accepted(): bool
    {
        return in_array($this->outcome, [ZatcaSubmission::OUTCOME_ACCEPTED, ZatcaSubmission::OUTCOME_ACCEPTED_WITH_WARNINGS], true);
    }

    public function rejected(): bool
    {
        return $this->outcome === ZatcaSubmission::OUTCOME_REJECTED;
    }

    public function uncertain(): bool
    {
        return $this->outcome === ZatcaSubmission::OUTCOME_TIMEOUT;
    }

    public function retryable(): bool
    {
        return in_array($this->outcome, [ZatcaSubmission::OUTCOME_NETWORK_ERROR, ZatcaSubmission::OUTCOME_SERVER_ERROR, ZatcaSubmission::OUTCOME_RATE_LIMITED, ZatcaSubmission::OUTCOME_TIMEOUT], true);
    }

    /** First human message for the UI. */
    public function summary(): string
    {
        if ($this->errors !== []) {
            return (string) ($this->errors[0]['message'] ?? $this->errors[0]['code'] ?? '');
        }
        if ($this->errorMessage) {
            return $this->errorMessage;
        }
        if ($this->warnings !== []) {
            return (string) ($this->warnings[0]['message'] ?? $this->warnings[0]['code'] ?? '');
        }

        return (string) ($this->zatcaStatus ?? $this->outcome);
    }
}
