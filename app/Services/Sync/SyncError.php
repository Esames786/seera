<?php

namespace App\Services\Sync;

/**
 * Structured error categories shared by the server and the client queue. The
 * client decides retry behaviour from the code, never from the English text.
 */
final class SyncError
{
    public const AUTH_REQUIRED = 'AUTH_REQUIRED';

    public const PERMISSION_DENIED = 'PERMISSION_DENIED';

    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const BUSINESS_CONFLICT = 'BUSINESS_CONFLICT';

    public const STALE_REFERENCE = 'STALE_REFERENCE';

    public const DUPLICATE = 'DUPLICATE';

    public const TEMPORARY_SERVER_ERROR = 'TEMPORARY_SERVER_ERROR';

    /** Client-side only: the request never reached the server. */
    public const NETWORK_ERROR = 'NETWORK_ERROR';

    public const ALL = [
        self::AUTH_REQUIRED, self::PERMISSION_DENIED, self::VALIDATION_ERROR, self::BUSINESS_CONFLICT,
        self::STALE_REFERENCE, self::DUPLICATE, self::TEMPORARY_SERVER_ERROR, self::NETWORK_ERROR,
    ];
}
