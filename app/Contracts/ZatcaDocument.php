<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A Seera business document that becomes one ZATCA e-document: a customer
 * invoice (388) or a customer credit / debit note (381 / 383). Both models
 * carry the same mirror columns (invoice_type, issue_time, supply_date,
 * seller_snapshot, buyer_snapshot, zatca_status, zatca_locked_at) so the
 * e-invoice services treat them alike; what differs is answered here.
 *
 * @property int $id
 * @property string $invoice_type
 * @property ?string $issue_time
 * @property ?Carbon $supply_date
 * @property ?array $seller_snapshot
 * @property ?array $buyer_snapshot
 * @property ?string $zatca_status
 * @property ?Carbon $zatca_locked_at
 * @property ?\App\Models\Customer $customer
 */
interface ZatcaDocument
{
    public const TYPE_CODE_INVOICE = '388';

    public const TYPE_CODE_CREDIT_NOTE = '381';

    public const TYPE_CODE_DEBIT_NOTE = '383';

    /** The document number written as cbc:ID and used in file names and logs. */
    public function zatcaNumber(): string;

    /** Issue date (BT-2). */
    public function zatcaIssueDate(): Carbon;

    /** 388 for an invoice, 381 for a credit note, 383 for a debit note (BT-3). */
    public function zatcaTypeCode(): string;

    /** The original invoice number for a note (BT-25, mandatory for 381 / 383); null for an invoice. */
    public function zatcaBillingReference(): ?string;

    /** The reason for issuing a note (KSA-10, mandatory for 381 / 383); null for an invoice. */
    public function zatcaNoteReason(): ?string;

    /** Free note text (cbc:Note), already limited by the caller. */
    public function zatcaFreeNote(): ?string;

    /** The amount columns the document array is built from (taxable_amount, vat_rate, vat_amount, total_amount). */
    public function zatcaAmounts(): array;

    /** The lines: objects with description, quantity, unit_price, taxable_amount, vat_rate, vat_amount, total_amount. */
    public function lines(): HasMany;

    public function zatcaRecord(): HasOne;

    /** The where-clause that identifies this document's compliance record. */
    public function zatcaRecordKey(): array;

    /** Extra attributes the compliance record stores for this document (foreign keys, document type). */
    public function zatcaRecordAttributes(): array;

    /** True once the business side has posted the document (an unposted document never reaches ZATCA). */
    public function isPostedForZatca(): bool;

    /** The activity-log module names that mention this document. */
    public function zatcaActivityModules(): array;
}
