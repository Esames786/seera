<?php

namespace App\Services\Zatca;

use App\Models\ZatcaEgsUnit;
use Illuminate\Support\Facades\DB;

/**
 * Generates one stamped document for an EGS unit under the unit's row lock:
 * assigns the next invoice counter value (KSA-16) and the previous document
 * hash (KSA-13) from the unit's chain state, builds the XML, validates it
 * locally, signs it and advances the chain. Two documents generated at the
 * same time serialise here, so the sequence and the chain can never fork.
 * A validation failure rolls back and consumes nothing.
 *
 * @see Detailed Technical Guidelines v2 §4.3: "Taxpayers must generate documents in a single sequence per EGS... The PDH must be maintained even in the case of documents which were rejected"
 */
final class ZatcaStampService
{
    public function __construct(
        private readonly ZatcaInvoiceBuilder $builder,
        private readonly ZatcaXmlValidator $validator,
        private readonly ZatcaSignatureService $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $doc  document without chain values
     * @return array{xml: string, unsigned_xml: string, invoice_hash: string, qr: string, icv: int, previous_hash: string, signed_at: \Illuminate\Support\Carbon, warnings: array<int, mixed>}
     */
    public function stamp(ZatcaEgsUnit $unit, array $doc, string $credentialKind = 'production'): array
    {
        return DB::transaction(function () use ($unit, $doc, $credentialKind) {
            $locked = ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($credentialKind === 'production' && ! $locked->hasProductionCredential()) {
                throw ZatcaException::configuration('no_production_csid', __('zatca.ready_unit_no_production_csid'));
            }
            if ($credentialKind === 'compliance' && ! $locked->hasComplianceCredential()) {
                throw ZatcaException::state('compliance_csid_missing', __('zatca.err_compliance_csid_missing'));
            }
            $doc['icv'] = (int) $locked->icv_counter + 1;
            $doc['previous_hash'] = $locked->last_invoice_hash ?: ZatcaEgsUnit::FIRST_PREVIOUS_HASH;
            $unsigned = $this->builder->build($doc);
            $validation = $this->validator->validate($doc, $unsigned);
            if ($validation['errors'] !== []) {
                throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'validation_failed', $validation['errors'][0]['message'], ['errors' => $validation['errors'], 'warnings' => $validation['warnings']]);
            }
            $signingUnit = $credentialKind === 'compliance' ? $this->complianceView($locked) : $locked;
            $signed = $this->signer->sign($unsigned, $signingUnit, [
                'seller_name' => $doc['seller']['name'], 'vat_number' => $doc['seller']['vat_number'],
                'timestamp' => $doc['issue_date'].'T'.$doc['issue_time'].'Z', 'total_with_vat' => $doc['totals']['tax_inclusive'], 'vat_total' => $doc['totals']['vat'],
            ], $doc['type'] === 'simplified');
            $locked->forceFill(['icv_counter' => $doc['icv'], 'last_invoice_hash' => $signed['invoice_hash']])->save();
            $unit->icv_counter = $locked->icv_counter;
            $unit->last_invoice_hash = $locked->last_invoice_hash;

            return ['xml' => $signed['xml'], 'unsigned_xml' => $unsigned, 'invoice_hash' => $signed['invoice_hash'], 'qr' => $signed['qr'], 'icv' => $doc['icv'],
                'previous_hash' => $doc['previous_hash'], 'signed_at' => $signed['signed_at'], 'warnings' => $validation['warnings']];
        }, 1);
    }

    /** A unit view that signs with the compliance certificate (for the compliance-check documents). */
    private function complianceView(ZatcaEgsUnit $unit): ZatcaEgsUnit
    {
        $view = $unit->replicate(['production_token_encrypted', 'production_secret_encrypted']);
        $view->production_token_encrypted = null;
        $view->production_secret_encrypted = null;
        $view->exists = true;
        $view->id = $unit->id;

        return $view;
    }
}
