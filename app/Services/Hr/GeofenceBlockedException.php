<?php

namespace App\Services\Hr;

use RuntimeException;

/**
 * Raised inside the attendance transaction when the site policy refuses the
 * position. The transaction rolls back; the caller then writes the audit
 * entry outside it (so the refusal is kept) and reports the reason.
 */
final class GeofenceBlockedException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly string $auditDescription)
    {
        parent::__construct($reason);
    }
}
