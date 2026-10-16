<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * One e-invoice generation solution unit (EGS) as ZATCA defines it: the
 * identity written into the CSR, the key pair, the compliance and production
 * CSIDs with their secrets, and the invoice counter / last document hash that
 * form the hash chain. Secrets are stored encrypted with the application key
 * and never leave this model unencrypted except to sign or authenticate.
 *
 * Seera assumes one centralised unit per environment (one issuer); the table
 * still keys units by environment + name so a second branch unit can be
 * added later without a schema change.
 */
class ZatcaEgsUnit extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CSR = 'csr_generated';

    public const STATUS_COMPLIANCE = 'compliance';

    public const STATUS_PRODUCTION = 'production';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [self::STATUS_NEW, self::STATUS_CSR, self::STATUS_COMPLIANCE, self::STATUS_PRODUCTION, self::STATUS_REVOKED];

    /** Base64 of SHA-256("0"): the PIH of the first document (BR-KSA-26). */
    public const FIRST_PREVIOUS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    protected $guarded = ['id'];

    protected $hidden = ['private_key_encrypted', 'compliance_token_encrypted', 'compliance_secret_encrypted', 'production_token_encrypted', 'production_secret_encrypted'];

    protected function casts(): array
    {
        return [
            'csr_generated_at' => 'datetime', 'compliance_issued_at' => 'datetime', 'production_issued_at' => 'datetime',
            'certificate_expires_at' => 'datetime', 'compliance_checks' => 'array',
        ];
    }

    public function submissions()
    {
        return $this->hasMany(ZatcaSubmission::class);
    }

    public function records()
    {
        return $this->hasMany(ZatcaInvoiceRecord::class);
    }

    // ----------------------------------------------------------- secrets

    public function setSecret(string $column, ?string $value): void
    {
        $this->{$column} = $value === null || $value === '' ? null : Crypt::encryptString($value);
    }

    public function secret(string $column): ?string
    {
        return $this->{$column} ? Crypt::decryptString($this->{$column}) : null;
    }

    public function privateKeyPem(): ?string
    {
        return $this->secret('private_key_encrypted');
    }

    public function hasPrivateKey(): bool
    {
        return $this->private_key_encrypted !== null;
    }

    public function hasComplianceCredential(): bool
    {
        return $this->compliance_token_encrypted !== null && $this->compliance_secret_encrypted !== null;
    }

    public function hasProductionCredential(): bool
    {
        return $this->production_token_encrypted !== null && $this->production_secret_encrypted !== null;
    }

    /** The credential pair to authenticate invoice submissions: production when issued, otherwise compliance. */
    public function invoiceCredential(): ?array
    {
        if ($this->hasProductionCredential()) {
            return ['token' => $this->secret('production_token_encrypted'), 'secret' => $this->secret('production_secret_encrypted'), 'kind' => 'production'];
        }
        if ($this->hasComplianceCredential()) {
            return ['token' => $this->secret('compliance_token_encrypted'), 'secret' => $this->secret('compliance_secret_encrypted'), 'kind' => 'compliance'];
        }

        return null;
    }

    /** The signing certificate (base64 DER as issued inside the binary security token), production first. */
    public function signingCertificate(): ?string
    {
        $token = $this->hasProductionCredential() ? $this->secret('production_token_encrypted') : ($this->hasComplianceCredential() ? $this->secret('compliance_token_encrypted') : null);
        if ($token === null) {
            return null;
        }
        // A CSID binarySecurityToken is base64(certificate PEM body); decode once to the PEM body.
        $decoded = base64_decode($token, true);

        return $decoded !== false && str_contains($decoded, '-----BEGIN') ? trim(preg_replace('/-----[A-Z ]+-----|\s+/', '', $decoded)) : ($decoded !== false ? trim(preg_replace('/\s+/', '', $decoded)) : $token);
    }

    public function statusLabel(): string
    {
        return __('zatca.unit_status_'.$this->status);
    }

    public function supportsStandard(): bool
    {
        return ($this->invoice_types[0] ?? '0') === '1';
    }

    public function supportsSimplified(): bool
    {
        return ($this->invoice_types[1] ?? '0') === '1';
    }
}
