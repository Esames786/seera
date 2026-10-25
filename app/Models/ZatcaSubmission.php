<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One API attempt against ZATCA (compliance check, clearance or reporting):
 * what was sent (by hash), what came back (status, warnings, errors, a
 * sanitised excerpt) and how it was classified. Never stores credentials,
 * Authorization headers or OTPs.
 */
class ZatcaSubmission extends Model
{
    public const OUTCOME_ACCEPTED = 'accepted';

    public const OUTCOME_ACCEPTED_WITH_WARNINGS = 'accepted_with_warnings';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_AUTH_ERROR = 'auth_error';

    public const OUTCOME_SERVER_ERROR = 'server_error';

    public const OUTCOME_RATE_LIMITED = 'rate_limited';

    public const OUTCOME_NETWORK_ERROR = 'network_error';

    public const OUTCOME_TIMEOUT = 'timeout';

    public const OUTCOME_UNKNOWN = 'outcome_unknown';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['warnings' => 'array', 'errors' => 'array', 'response_excerpt' => 'array'];
    }

    public function record()
    {
        return $this->belongsTo(ZatcaInvoiceRecord::class, 'zatca_invoice_record_id');
    }

    public function unit()
    {
        return $this->belongsTo(ZatcaEgsUnit::class, 'zatca_egs_unit_id');
    }
}
