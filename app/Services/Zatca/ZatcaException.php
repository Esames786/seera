<?php

namespace App\Services\Zatca;

use RuntimeException;

/**
 * Structured ZATCA failure. The category drives UI wording and retry policy;
 * the code and details keep the machine reason for support without exposing
 * credentials.
 */
final class ZatcaException extends RuntimeException
{
    public const LOCAL_VALIDATION = 'local_validation';

    public const READINESS = 'readiness';

    public const CONFIGURATION = 'configuration';

    public const AUTH = 'auth';

    public const BUSINESS_REJECTION = 'business_rejection';

    public const NETWORK = 'network';

    public const RATE_LIMIT = 'rate_limit';

    public const OUTCOME_UNKNOWN = 'outcome_unknown';

    public const SERVER_ERROR = 'server_error';

    public const STATE = 'state';

    /** @param array<string, mixed> $details */
    public function __construct(public readonly string $category, public readonly string $errorCode, string $message, public readonly array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function readiness(string $code, string $message, array $details = []): self
    {
        return new self(self::READINESS, $code, $message, $details);
    }

    public static function configuration(string $code, string $message, array $details = []): self
    {
        return new self(self::CONFIGURATION, $code, $message, $details);
    }

    public static function state(string $code, string $message): self
    {
        return new self(self::STATE, $code, $message);
    }

    public function isRetryable(): bool
    {
        return in_array($this->category, [self::NETWORK, self::RATE_LIMIT, self::SERVER_ERROR, self::OUTCOME_UNKNOWN], true);
    }
}
