<?php

namespace App\Services\Zatca;

use App\Models\ActivityLog;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Models\ZatcaSubmission;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The invoice-level lifecycle: record creation at approval, readiness,
 * generate (preview build + local validation), sign (chain assignment and
 * stamp, which locks the invoice), submit (clearance for standard documents,
 * reporting for simplified ones), retry with the same signed document, and
 * the "outcome unknown" path when the network fails after sending. Every
 * artifact is written to the private local disk; every attempt is logged.
 * ZATCA responses never post anything to the ledger.
 */
final class ZatcaSubmissionService
{
    public function __construct(
        private readonly ZatcaEnvironment $environment,
        private readonly ZatcaReadiness $readiness,
        private readonly ZatcaSnapshot $snapshot,
        private readonly ZatcaDocumentFactory $factory,
        private readonly ZatcaInvoiceBuilder $builder,
        private readonly ZatcaXmlValidator $validator,
        private readonly ZatcaStampService $stamp,
        private readonly ZatcaClient $client,
        private readonly ZatcaHashService $hashes,
    ) {}

    /** Called at approval: the UUID is assigned once and survives everything except a reopen before any stamp. */
    public function ensureRecord(CustomerInvoice $invoice): ZatcaInvoiceRecord
    {
        $record = $invoice->zatcaRecord;
        if ($record && $record->status !== ZatcaInvoiceRecord::STATUS_CANCELLED) {
            return $record;
        }
        $record = ZatcaInvoiceRecord::updateOrCreate(['customer_invoice_id' => $invoice->id], [
            'uuid' => $record?->uuid ?? (string) Str::uuid(), 'status' => ZatcaInvoiceRecord::STATUS_NOT_GENERATED, 'clearance_status' => 'draft',
            'invoice_type' => $invoice->invoice_type, 'environment' => $this->environment->current(), 'digital_signature_status' => 'pending',
            'retry_count' => 0, 'qr_code_data' => null, 'xml_file_path' => null, 'tamper_proof_hash' => null, 'failed_reason' => null,
            'zatca_response_code' => null, 'zatca_response_message' => null, 'cleared_at' => null,
        ]);
        $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_NOT_GENERATED]);
        $invoice->setRelation('zatcaRecord', $record);

        return $record;
    }

    /** The unit of the current environment (one issuer assumed; the most recently onboarded production unit wins). */
    public function unit(): ?ZatcaEgsUnit
    {
        $environment = $this->environment->current();

        return ZatcaEgsUnit::where('environment', $environment)->where('status', '!=', ZatcaEgsUnit::STATUS_REVOKED)
            ->orderByRaw("CASE WHEN status = 'production' THEN 0 ELSE 1 END")->orderByDesc('id')->first();
    }

    /** @return array{errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, seller: array<string, mixed>, buyer: array<string, mixed>, unit: ?ZatcaEgsUnit} */
    public function readiness(CustomerInvoice $invoice): array
    {
        $invoice->loadMissing('customer');
        $seller = $invoice->seller_snapshot ?: $this->snapshot->seller();
        $buyer = $invoice->buyer_snapshot ?: $this->snapshot->buyer($invoice->customer);
        $sellerCheck = $this->readiness->seller($seller);
        $buyerCheck = $this->readiness->buyer($buyer, (string) $invoice->invoice_type);
        $unit = $this->unit();

        return [
            'errors' => array_merge($this->readiness->invoice($invoice), $sellerCheck['errors'], $buyerCheck['errors'], $this->readiness->unit($unit, (string) $invoice->invoice_type)),
            'warnings' => array_merge($sellerCheck['warnings'], $buyerCheck['warnings']),
            'seller' => $seller, 'buyer' => $buyer, 'unit' => $unit,
        ];
    }

    /**
     * Preview: freeze the snapshots, build the document with the chain values
     * the next stamp would use, validate locally and store the unsigned XML.
     * Nothing is consumed; the status becomes validated or validation_failed.
     */
    public function generate(CustomerInvoice $invoice, User $actor): ZatcaInvoiceRecord
    {
        $record = $this->ensureRecord($invoice);
        $this->assertNotAccepted($record);
        if ($record->isLocked()) {
            throw ZatcaException::state('already_signed', __('zatca.err_already_signed'));
        }
        $readiness = $this->readiness($invoice);
        if ($readiness['errors'] !== []) {
            $this->fail($record, $invoice, ZatcaInvoiceRecord::STATUS_FAILED, ZatcaException::READINESS, $readiness['errors'][0]['code'], $readiness['errors'][0]['message'], ['readiness' => $readiness['errors']]);
            throw ZatcaException::readiness($readiness['errors'][0]['code'], $readiness['errors'][0]['message'], ['errors' => $readiness['errors'], 'warnings' => $readiness['warnings']]);
        }
        $this->snapshot->freeze($invoice);
        $unit = $readiness['unit'];
        $doc = $this->factory->fromInvoice($invoice->fresh(['lines']), $record, (int) $unit->icv_counter + 1, $unit->last_invoice_hash ?: ZatcaEgsUnit::FIRST_PREVIOUS_HASH);
        $xml = $this->builder->build($doc);
        $validation = $this->validator->validate($doc, $xml);
        $path = $this->store($record, 'unsigned', $xml);
        $passed = $validation['errors'] === [];
        $record = DB::transaction(function () use ($record, $invoice, $unit, $doc, $path, $passed, $validation, $readiness) {
            // Lock order everywhere: unit row first, then the record (the record carries a foreign key to the unit).
            ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $locked = ZatcaInvoiceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->isLocked() || $locked->isAccepted()) {
                // Another request stamped this invoice meanwhile: a preview must never overwrite a stamped record.
                throw ZatcaException::state('already_signed', __('zatca.err_already_signed'));
            }
            $locked->forceFill([
            'status' => $passed ? ZatcaInvoiceRecord::STATUS_VALIDATED : ZatcaInvoiceRecord::STATUS_VALIDATION_FAILED,
            'environment' => $unit->environment, 'zatca_egs_unit_id' => $unit->id, 'invoice_type' => $invoice->invoice_type, 'invoice_type_code' => $doc['type_code'],
            'transaction_code' => $doc['transaction_code'], 'xml_path' => $path, 'generated_at' => now(), 'validated_at' => now(),
            'validation_status' => $passed ? 'passed' : 'failed', 'validation_errors' => $validation['errors'], 'validation_warnings' => array_merge($readiness['warnings'], $validation['warnings']),
            'last_error_category' => $passed ? null : ZatcaException::LOCAL_VALIDATION, 'last_error_code' => $passed ? null : $validation['errors'][0]['code'],
            'last_error_message' => $passed ? null : $validation['errors'][0]['message'],
            ])->save();
            CustomerInvoice::whereKey($invoice->id)->update(['zatca_status' => $locked->status]);

            return $locked;
        }, 1);
        $this->log($actor, $passed ? 'ZATCA XML generated and validated locally' : 'ZATCA local validation failed', $invoice, $passed ? 'success' : 'failed', $passed ? null : $validation['errors'][0]['message']);

        return $record;
    }

    /** Stamp: consumes the ICV, extends the chain, signs, stores the signed XML and locks the invoice. */
    public function sign(CustomerInvoice $invoice, User $actor): ZatcaInvoiceRecord
    {
        $record = $this->generate($invoice, $actor);
        if ($record->status !== ZatcaInvoiceRecord::STATUS_VALIDATED) {
            throw new ZatcaException(ZatcaException::LOCAL_VALIDATION, 'validation_failed', (string) $record->last_error_message, ['errors' => $record->validation_errors]);
        }
        $unit = $record->unit;
        $this->environment->assertUsable();
        $doc = $this->factory->fromInvoice($invoice->fresh(['lines']), $record, 0, ZatcaEgsUnit::FIRST_PREVIOUS_HASH);

        return DB::transaction(function () use ($record, $invoice, $unit, $doc, $actor) {
            ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail(); // unit first, then record (same order as generate)
            $locked = ZatcaInvoiceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->isLocked()) {
                return $locked;
            }
            $stamped = $this->stamp->stamp($unit, $doc, 'production');
            $path = $this->store($locked, 'signed', $stamped['xml']);
            $locked->forceFill([
                'status' => ZatcaInvoiceRecord::STATUS_SIGNED, 'icv' => $stamped['icv'], 'previous_hash' => $stamped['previous_hash'], 'invoice_hash' => $stamped['invoice_hash'],
                'signed_xml_path' => $path, 'qr_code' => $stamped['qr'], 'signed_at' => $stamped['signed_at'], 'digital_signature_status' => 'signed',
                'validation_warnings' => $stamped['warnings'], 'last_error_category' => null, 'last_error_code' => null, 'last_error_message' => null,
            ])->save();
            CustomerInvoice::whereKey($invoice->id)->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_SIGNED, 'zatca_locked_at' => now()]);
            $this->log($actor, 'ZATCA invoice stamped', $invoice, 'success', 'ICV '.$stamped['icv']);

            return $locked;
        }, 1);
    }

    /** Submit the signed document: clearance for standard, reporting for simplified. A retry resubmits the identical XML. */
    public function submit(CustomerInvoice $invoice, User $actor): ZatcaInvoiceRecord
    {
        $record = $invoice->zatcaRecord ?? $this->ensureRecord($invoice);
        $this->assertNotAccepted($record);
        if (! in_array($record->status, [ZatcaInvoiceRecord::STATUS_SIGNED, ZatcaInvoiceRecord::STATUS_SUBMISSION_PENDING, ZatcaInvoiceRecord::STATUS_REJECTED, ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN], true)) {
            throw ZatcaException::state('not_signed', __('zatca.err_not_signed'));
        }
        $environment = $this->environment->assertUsable();
        if ($record->environment !== $environment) {
            throw ZatcaException::configuration('environment_mismatch', __('zatca.err_environment_mismatch', ['unit' => $record->environment, 'current' => $environment]));
        }
        $unit = $record->unit;
        $credential = $unit?->hasProductionCredential() ? $unit->invoiceCredential() : null;
        if (! $credential) {
            throw ZatcaException::configuration('no_production_csid', __('zatca.ready_unit_no_production_csid'));
        }
        $xml = Storage::disk('local')->get($record->signed_xml_path);
        if (! $xml || $this->hashes->hash($xml)['hash'] !== $record->invoice_hash) {
            throw ZatcaException::state('artifact_tampered', __('zatca.err_artifact_tampered'));
        }
        $attempt = $record->submission_attempts + 1;
        $wasUnknown = $record->status === ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN;
        // Claim: only one submission of a document may be in flight; a concurrent caller finds the row already claimed (or already decided) and stops.
        $claimed = ZatcaInvoiceRecord::whereKey($record->id)->where('status', $record->status)->where('submission_attempts', $record->submission_attempts)
            ->update(['status' => ZatcaInvoiceRecord::STATUS_SUBMISSION_PENDING, 'submission_attempts' => $attempt, 'submitted_at' => now(), 'updated_at' => now()]);
        if ($claimed !== 1) {
            throw ZatcaException::state('submission_in_progress', __('zatca.err_submission_in_progress'));
        }
        $record->refresh();
        $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_SUBMISSION_PENDING]);

        $simplified = $record->invoice_type === CustomerInvoice::TYPE_SIMPLIFIED;
        $result = $simplified
            ? $this->client->reporting($credential['token'], $credential['secret'], $record->invoice_hash, $record->uuid, $xml, $environment)
            : $this->client->clearance($credential['token'], $credential['secret'], $record->invoice_hash, $record->uuid, $xml, $environment);

        ZatcaSubmission::create(['zatca_invoice_record_id' => $record->id, 'zatca_egs_unit_id' => $unit->id, 'attempt' => $attempt, 'environment' => $environment,
            'endpoint' => $simplified ? 'invoices/reporting/single' : 'invoices/clearance/single', 'invoice_hash' => $record->invoice_hash, 'http_status' => $result->httpStatus,
            'outcome' => $result->outcome, 'zatca_status' => $result->zatcaStatus, 'warnings' => $result->warnings, 'errors' => $result->errors, 'response_excerpt' => $result->excerpt,
            'duration_ms' => $result->durationMs, 'user_id' => $actor->id]);

        if ($result->accepted()) {
            $changes = ['status' => $simplified ? ZatcaInvoiceRecord::STATUS_REPORTED : ZatcaInvoiceRecord::STATUS_CLEARED, 'zatca_status' => $result->zatcaStatus,
                'accepted_with_warnings' => $result->warnings !== [], 'response_warnings' => $result->warnings, 'response_errors' => [], 'outcome_unknown_at' => null,
                'last_error_category' => null, 'last_error_code' => null, 'last_error_message' => null, 'zatca_response_code' => (string) $result->httpStatus, 'zatca_response_message' => $result->summary()];
            if ($simplified) {
                $changes['reported_at'] = now();
            } else {
                $changes['cleared_at'] = now();
                if ($result->clearedInvoiceXml) {
                    $changes['cleared_xml_path'] = $this->store($record, 'cleared', $result->clearedInvoiceXml);
                    $qr = $this->qrFrom($result->clearedInvoiceXml);
                    if ($qr) {
                        $changes['qr_code'] = $qr;
                    }
                }
            }
            $record->forceFill($changes)->save();
            $invoice->update(['zatca_status' => $record->status, 'zatca_locked_at' => $invoice->zatca_locked_at ?? now()]);
            $this->log($actor, $simplified ? 'ZATCA invoice reported' : 'ZATCA invoice cleared', $invoice, 'success', ($result->warnings !== [] ? count($result->warnings).' warning(s)' : 'no warnings'));

            return $record->fresh();
        }

        if ($result->uncertain()) {
            $record->forceFill(['status' => ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN, 'outcome_unknown_at' => now(), 'last_error_category' => ZatcaException::OUTCOME_UNKNOWN,
                'last_error_code' => $result->errorCode, 'last_error_message' => $result->errorMessage])->save();
            $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN]);
            $this->log($actor, 'ZATCA submission outcome unknown', $invoice, 'failed', $result->errorMessage);

            return $record->fresh();
        }

        if ($result->rejected()) {
            $duplicate = collect($result->errors)->contains(fn ($e) => Str::contains(strtolower(($e['code'] ?? '').' '.($e['message'] ?? '')), ['duplicate', 'already']));
            if ($duplicate && $wasUnknown) {
                // ZATCA already holds a document with this identity from the uncertain attempt: do not mark rejected, keep verification required.
                $record->forceFill(['status' => ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN, 'last_error_category' => ZatcaException::OUTCOME_UNKNOWN, 'last_error_code' => 'duplicate_on_retry',
                    'last_error_message' => __('zatca.err_duplicate_on_retry'), 'response_errors' => $result->errors])->save();
                $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN]);
                $this->log($actor, 'ZATCA retry answered duplicate; verification required', $invoice, 'failed');

                return $record->fresh();
            }
            $record->forceFill(['status' => ZatcaInvoiceRecord::STATUS_REJECTED, 'zatca_status' => $result->zatcaStatus, 'response_errors' => $result->errors, 'response_warnings' => $result->warnings,
                'last_error_category' => ZatcaException::BUSINESS_REJECTION, 'last_error_code' => $result->errorCode, 'last_error_message' => $result->errorMessage,
                'zatca_response_code' => (string) $result->httpStatus, 'zatca_response_message' => $result->summary()])->save();
            $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_REJECTED]);
            $this->log($actor, 'ZATCA submission rejected', $invoice, 'failed', $result->summary());

            return $record->fresh();
        }

        // Auth, rate limit, server or network error: the document is unchanged and can be retried; auth needs a person first.
        $record->forceFill(['status' => ZatcaInvoiceRecord::STATUS_SIGNED, 'last_error_category' => match ($result->outcome) {
            ZatcaSubmission::OUTCOME_AUTH_ERROR => ZatcaException::AUTH, ZatcaSubmission::OUTCOME_RATE_LIMITED => ZatcaException::RATE_LIMIT,
            ZatcaSubmission::OUTCOME_NETWORK_ERROR => ZatcaException::NETWORK, default => ZatcaException::SERVER_ERROR,
        }, 'last_error_code' => $result->errorCode, 'last_error_message' => $result->errorMessage])->save();
        $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_SIGNED]);
        $this->log($actor, 'ZATCA submission failed ('.$result->outcome.')', $invoice, 'failed', $result->errorMessage);

        return $record->fresh();
    }

    /** Generate, stamp and submit in one go (the "Submit to ZATCA" action). */
    public function process(CustomerInvoice $invoice, User $actor): ZatcaInvoiceRecord
    {
        $record = $invoice->zatcaRecord ?? $this->ensureRecord($invoice);
        if (! $record->isLocked()) {
            $this->sign($invoice, $actor);
        }

        return $this->submit($invoice->fresh(['zatcaRecord']), $actor);
    }

    /**
     * After a rejection or a local failure (never after acceptance): refresh
     * the snapshots from the corrected masters and return the record to
     * not_generated with the same UUID. Consumed ICVs stay consumed; the
     * invoice stays locked if it was ever stamped.
     */
    public function regenerate(CustomerInvoice $invoice, User $actor): ZatcaInvoiceRecord
    {
        $record = $invoice->zatcaRecord ?? $this->ensureRecord($invoice);
        if (! in_array($record->status, [ZatcaInvoiceRecord::STATUS_REJECTED, ZatcaInvoiceRecord::STATUS_FAILED, ZatcaInvoiceRecord::STATUS_VALIDATION_FAILED, ZatcaInvoiceRecord::STATUS_VALIDATED, ZatcaInvoiceRecord::STATUS_GENERATED], true)) {
            throw ZatcaException::state('cannot_regenerate', __('zatca.err_cannot_regenerate'));
        }
        $this->snapshot->freeze($invoice, force: true);
        $record = ZatcaInvoiceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
        if ($record->isAccepted() || in_array($record->status, [ZatcaInvoiceRecord::STATUS_SUBMISSION_PENDING, ZatcaInvoiceRecord::STATUS_OUTCOME_UNKNOWN, ZatcaInvoiceRecord::STATUS_SIGNED], true)) {
            throw ZatcaException::state('cannot_regenerate', __('zatca.err_cannot_regenerate'));
        }
        $record->forceFill(['status' => ZatcaInvoiceRecord::STATUS_NOT_GENERATED, 'xml_path' => null, 'signed_xml_path' => null, 'cleared_xml_path' => null, 'qr_code' => null,
            'invoice_hash' => null, 'icv' => null, 'previous_hash' => null, 'validation_status' => null, 'validation_errors' => null, 'validation_warnings' => null,
            'generated_at' => null, 'validated_at' => null, 'signed_at' => null, 'response_errors' => null, 'response_warnings' => null, 'zatca_status' => null,
            'last_error_category' => null, 'last_error_code' => null, 'last_error_message' => null, 'digital_signature_status' => 'pending'])->save();
        $invoice->update(['zatca_status' => ZatcaInvoiceRecord::STATUS_NOT_GENERATED]);
        $this->log($actor, 'ZATCA record reset for regeneration', $invoice);

        return $record->fresh();
    }

    public function artifactPath(ZatcaInvoiceRecord $record, string $kind): ?string
    {
        return match ($kind) {
            'unsigned' => $record->xml_path,
            'signed' => $record->signed_xml_path,
            'cleared' => $record->cleared_xml_path,
            default => null,
        };
    }

    /** File name per XML Implementation Standard §14: VAT_yyyymmddThhmmss_number.xml */
    public function fileName(CustomerInvoice $invoice, ZatcaInvoiceRecord $record, string $kind): string
    {
        $seller = $invoice->seller_snapshot['vat_number'] ?? 'seller';
        $time = str_replace(':', '', (string) ($invoice->issue_time ?: '000000'));
        $number = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $invoice->invoice_number);

        return $seller.'_'.$invoice->invoice_date->format('Ymd').'T'.$time.'_'.$number.($kind === 'signed' ? '' : '-'.$kind).'.xml';
    }

    private function store(ZatcaInvoiceRecord $record, string $kind, string $xml): string
    {
        $path = 'zatca/'.($record->environment ?: $this->environment->current()).'/'.$record->uuid.'/'.$kind.'.xml';
        Storage::disk('local')->put($path, $xml);

        return $path;
    }

    private function qrFrom(string $xml): ?string
    {
        try {
            $dom = $this->hashes->load($xml);
        } catch (ZatcaException) {
            return null;
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', ZatcaHashService::CAC_NS);
        $xpath->registerNamespace('cbc', ZatcaHashService::CBC_NS);
        $node = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='QR']/cac:Attachment/cbc:EmbeddedDocumentBinaryObject")->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function assertNotAccepted(ZatcaInvoiceRecord $record): void
    {
        if ($record->isAccepted()) {
            throw ZatcaException::state('already_accepted', __('zatca.err_already_accepted'));
        }
    }

    private function fail(ZatcaInvoiceRecord $record, CustomerInvoice $invoice, string $status, string $category, string $code, string $message, array $details = []): void
    {
        $record->forceFill(['status' => $status, 'last_error_category' => $category, 'last_error_code' => $code, 'last_error_message' => $message, 'validation_errors' => $details['readiness'] ?? $record->validation_errors])->save();
        $invoice->update(['zatca_status' => $status]);
    }

    private function log(User $actor, string $action, CustomerInvoice $invoice, string $status = 'success', ?string $detail = null): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'user_name' => $actor->name, 'module' => 'ZATCA', 'action' => $action, 'status' => $status,
            'description' => $invoice->invoice_number.($detail ? ' · '.mb_substr($detail, 0, 300) : '')]);
    }
}
