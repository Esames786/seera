<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\VatTransaction;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Models\ZatcaSubmission;
use App\Services\Zatca\ZatcaCrypto;
use App\Services\Zatca\ZatcaEnvironment;
use App\Services\Zatca\ZatcaException;
use App\Services\Zatca\ZatcaHashService;
use App\Services\Zatca\ZatcaOnboardingService;
use App\Services\Zatca\ZatcaQrService;
use App\Services\Zatca\ZatcaSubmissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ZATCA e-invoicing Phase 2, sprint 1: readiness, immutable snapshots, UBL
 * XML from the posted invoice, local validation, stamp (XAdES, secp256k1),
 * Phase-2 QR, hash chain, explicit environments, the HTTP client against a
 * faked ZATCA, clearance / reporting state transitions, retry and
 * outcome-unknown handling, private artifacts, permissions, scope, and the
 * promise that ZATCA never touches the ledger. The sandbox itself is faked:
 * a live sandbox run needs Developer Portal credentials.
 */
class ZatcaPhase2Test extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';

    private Project $project;

    private Customer $customer;

    private ZatcaEgsUnit $unit;

    private string $certificateBase64;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => false, 'zatca.endpoints.sandbox' => self::SANDBOX]);
        CompanyProfile::query()->update(['name' => 'Seera Construction Co.', 'name_ar' => 'شركة سيرا للإنشاءات', 'vat_number' => '300123456700003', 'cr_number' => '1010123456',
            'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN']);
        $this->project = Project::create(['name' => 'ZATCA Tower', 'code' => 'PRJ-ZATCA', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-ZATCA', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
        $this->unit = $this->onboardedUnit();
    }

    // ---------------------------------------------------------------- helpers

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** A test key pair and a self-signed secp256k1 certificate standing in for a sandbox production CSID. */
    private function onboardedUnit(string $environment = 'sandbox', string $types = '1100'): ZatcaEgsUnit
    {
        $crypto = app(ZatcaCrypto::class);
        $keys = $crypto->generateKeyPair();
        $key = openssl_pkey_get_private($keys['private_key']);
        $config = $crypto->opensslConfig();
        $csr = openssl_csr_new(['C' => 'SA', 'O' => 'Test CA', 'CN' => 'TSZEINVOICE-SubCA-1'], $key, ['config' => $config, 'digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['config' => $config, 'digest_alg' => 'sha256']);
        openssl_x509_export($cert, $pem);
        $this->certificateBase64 = preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
        $unit = ZatcaEgsUnit::create(['name' => 'Seera test unit '.++$this->seq, 'environment' => $environment, 'status' => ZatcaEgsUnit::STATUS_PRODUCTION, 'invoice_types' => $types,
            'vat_number' => '300123456700003', 'organization_name' => 'Seera Construction Co.', 'organization_unit' => 'Riyadh', 'country_code' => 'SA', 'egs_serial' => '1-Seera ERP|2-1.0|3-TEST']);
        $unit->setSecret('private_key_encrypted', $keys['private_key']);
        $unit->setSecret('production_token_encrypted', base64_encode($this->certificateBase64));
        $unit->setSecret('production_secret_encrypted', 'test-secret-value');
        $unit->forceFill(['public_key_pem' => $keys['public_key'], 'production_issued_at' => now(), 'certificate_pem' => $this->certificateBase64])->save();

        return $unit->fresh();
    }

    private function user(array $grants, ?Project $project = null): User
    {
        $suffix = 'zatca-'.++$this->seq;
        $role = Role::create(['name' => 'Role '.$suffix, 'code' => 'ZATCA_ROLE_'.$this->seq, 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            foreach ($actions as $action) {
                $ids->push(Permission::firstOrCreate(['module' => $module, 'action' => $action])->id);
            }
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'User '.$suffix, 'email' => $suffix.'@example.test', 'username' => $suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function invoice(array $extra = [], array $lines = [['description' => 'Progress claim 1', 'quantity' => 2, 'unit_price' => 500]], bool $approve = true): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), $extra + [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => $this->project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'invoice_type' => 'standard', 'lines' => $lines,
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        if ($approve) {
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        }

        return $invoice->fresh(['zatcaRecord', 'lines', 'customer']);
    }

    private function service(): ZatcaSubmissionService
    {
        return app(ZatcaSubmissionService::class);
    }

    /** Laravel keeps earlier stubs and the first match wins; reset the client before each new fake so a test can stage several ZATCA answers. */
    private function fake(array $responses): void
    {
        app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();
        Http::fake($responses);
    }

    private function fakeZatca(array $responses = []): void
    {
        $this->fake($responses + [
            self::SANDBOX.'/invoices/clearance/single' => fn (ClientRequest $request) => Http::response(['validationResults' => ['infoMessages' => [], 'warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'],
                'clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode($this->clearedXml($request))], 200),
            self::SANDBOX.'/invoices/reporting/single' => Http::response(['validationResults' => ['infoMessages' => [], 'warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'], 'reportingStatus' => 'REPORTED'], 200),
        ]);
    }

    /** The faked platform returns the submitted document with a ZATCA QR in place of the seller's. */
    private function clearedXml(ClientRequest $request): string
    {
        $xml = base64_decode($request->data()['invoice']);

        return preg_replace('/<cbc:EmbeddedDocumentBinaryObject mimeCode="text\/plain">[^<]*<\/cbc:EmbeddedDocumentBinaryObject>(\s*<\/cac:Attachment>\s*<\/cac:AdditionalDocumentReference>\s*<cac:Signature>)/',
            '<cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">'.base64_encode('ZATCA-QR').'</cbc:EmbeddedDocumentBinaryObject>$1', $xml, 1);
    }

    private function signedXml(ZatcaInvoiceRecord $record): string
    {
        return Storage::disk('local')->get($record->signed_xml_path);
    }

    // ---------------------------------------------------------- readiness

    public function test_seller_readiness_blocks_generation_with_actionable_messages(): void
    {
        CompanyProfile::query()->update(['building_number' => '78', 'postal_code' => '1221', 'street' => null]);
        $invoice = $this->invoice();
        $readiness = $this->service()->readiness($invoice);
        $codes = collect($readiness['errors'])->pluck('code')->all();
        $this->assertContains('seller_building_invalid', $codes);
        $this->assertContains('seller_postal_invalid', $codes);
        $this->assertContains('seller_street_missing', $codes);
        $this->assertStringContainsString('Company Profile', $readiness['errors'][0]['message']);
        try {
            $this->service()->generate($invoice, $this->admin());
            $this->fail('generation should be refused');
        } catch (ZatcaException $e) {
            $this->assertSame(ZatcaException::READINESS, $e->category);
        }
        $this->assertSame('failed', $invoice->fresh()->zatcaRecord->status);
        $this->assertNull($invoice->fresh()->zatcaRecord->xml_path);
    }

    public function test_buyer_rules_depend_on_the_invoice_type(): void
    {
        $this->customer->update(['street' => null, 'city' => null, 'vat_number' => null, 'cr_number' => null, 'buyer_id_scheme' => null, 'buyer_id_value' => null]);
        $standard = $this->invoice();
        $codes = collect($this->service()->readiness($standard)['errors'])->pluck('code')->all();
        $this->assertContains('buyer_street_missing', $codes);
        $this->assertContains('buyer_city_missing', $codes);
        $this->assertContains('buyer_id_missing', $codes);

        $simplified = $this->invoice(['invoice_type' => 'simplified']);
        $this->assertSame([], $this->service()->readiness($simplified)['errors']);

        // A non-VAT buyer with a national ID satisfies BR-KSA-14 on a standard invoice.
        $this->customer->update(['street' => 'Olaya Street', 'city' => 'Riyadh', 'buyer_id_scheme' => 'NAT', 'buyer_id_value' => '1012345678']);
        $standard2 = $this->invoice();
        $this->assertSame([], $this->service()->readiness($standard2)['errors']);
        $record = $this->service()->generate($standard2, $this->admin());
        $this->assertStringContainsString('<cbc:ID schemeID="NAT">1012345678</cbc:ID>', Storage::disk('local')->get($record->xml_path));
    }

    public function test_uuid_is_assigned_at_approval_and_never_changes(): void
    {
        $invoice = $this->invoice();
        $uuid = $invoice->zatcaRecord->uuid;
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);
        $this->service()->generate($invoice, $this->admin());
        $this->service()->regenerate($invoice->fresh(['zatcaRecord']), $this->admin());
        $record = $this->service()->sign($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame($uuid, $record->uuid);
        $this->assertStringContainsString('<cbc:UUID>'.$uuid.'</cbc:UUID>', $this->signedXml($record));
    }

    // ------------------------------------------------------------- XML

    public function test_xml_is_generated_from_the_posted_invoice_and_stored_privately(): void
    {
        $invoice = $this->invoice([], [['description' => 'Steel bars', 'quantity' => 3, 'unit_price' => 250.5], ['description' => 'Cement', 'quantity' => 10, 'unit_price' => 20]]);
        $record = $this->service()->generate($invoice, $this->admin());
        $this->assertSame('validated', $record->status);
        $this->assertSame('passed', $record->validation_status);
        $xml = Storage::disk('local')->get($record->xml_path);
        $this->assertStringStartsWith('zatca/sandbox/'.$record->uuid.'/', $record->xml_path);
        $this->assertStringContainsString('<cbc:ProfileID>reporting:1.0</cbc:ProfileID>', $xml);
        $this->assertStringContainsString('<cbc:ID>'.$invoice->invoice_number.'</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:InvoiceTypeCode name="0100000">388</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:IssueTime>'.$invoice->issue_time.'</cbc:IssueTime>', $xml);
        $this->assertStringContainsString('<cbc:ActualDeliveryDate>'.$invoice->supply_date->toDateString().'</cbc:ActualDeliveryDate>', $xml);
        $this->assertStringContainsString('<cbc:ID>ICV</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:ID>PIH</cbc:ID>', $xml);
        $this->assertStringContainsString(ZatcaEgsUnit::FIRST_PREVIOUS_HASH, $xml);
        $this->assertStringContainsString('<cbc:CompanyID>300123456700003</cbc:CompanyID>', $xml);
        $this->assertStringContainsString('<cbc:RegistrationName>Riyadh Developers LLC</cbc:RegistrationName>', $xml);
        $this->assertStringContainsString('<cbc:BuildingNumber>7890</cbc:BuildingNumber>', $xml);
        $this->assertStringContainsString('<cbc:LineExtensionAmount currencyID="SAR">751.50</cbc:LineExtensionAmount>', $xml);
        $this->assertStringContainsString('<cbc:LineExtensionAmount currencyID="SAR">200.00</cbc:LineExtensionAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxExclusiveAmount currencyID="SAR">951.50</cbc:TaxExclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="SAR">1094.23</cbc:TaxInclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxAmount currencyID="SAR">142.73</cbc:TaxAmount>', $xml);
        $this->assertStringContainsString('<cbc:PriceAmount currencyID="SAR">250.50</cbc:PriceAmount>', $xml);
        $this->assertStringNotContainsString('<ext:UBLExtensions>', $xml);
        $this->assertDatabaseHas('activity_logs', ['module' => 'ZATCA', 'action' => 'ZATCA XML generated and validated locally']);
    }

    public function test_later_customer_and_company_changes_do_not_alter_the_issued_document(): void
    {
        $invoice = $this->invoice();
        $this->assertSame('Riyadh Developers LLC', $invoice->buyer_snapshot['name']);
        $this->customer->update(['name' => 'Renamed Customer', 'street' => 'New Street']);
        CompanyProfile::query()->update(['name' => 'Renamed Seller', 'building_number' => '1111']);
        $record = $this->service()->generate($invoice->fresh(['zatcaRecord']), $this->admin());
        $xml = Storage::disk('local')->get($record->xml_path);
        $this->assertStringContainsString('Riyadh Developers LLC', $xml);
        $this->assertStringContainsString('Seera Construction Co.', $xml);
        $this->assertStringNotContainsString('Renamed', $xml);
        $this->assertStringContainsString('<cbc:BuildingNumber>7890</cbc:BuildingNumber>', $xml);

        $signed = $this->service()->sign($invoice->fresh(['zatcaRecord']), $this->admin());
        CompanyProfile::query()->update(['vat_number' => '399999999900003']);
        $this->assertSame($signed->invoice_hash, app(ZatcaHashService::class)->hash($this->signedXml($signed))['hash']);
        $this->assertStringContainsString('300123456700003', $this->signedXml($signed));
    }

    public function test_vat_totals_reconcile_and_a_rounding_conflict_is_reported_not_hidden(): void
    {
        // Two lines of 1.03: per-line VAT 0.15 + 0.15 = 0.30, document-level VAT round(2.06 x 15%) = 0.31 (BR-S-09).
        $invoice = $this->invoice([], [['description' => 'A', 'quantity' => 1, 'unit_price' => 1.03], ['description' => 'B', 'quantity' => 1, 'unit_price' => 1.03]]);
        $this->assertSame('0.30', (string) $invoice->vat_amount);
        $record = $this->service()->generate($invoice, $this->admin());
        $this->assertSame('validation_failed', $record->status);
        $this->assertSame('vat_rounding_mismatch', $record->validation_errors[0]['code']);
        $this->assertStringContainsString('0.31', $record->validation_errors[0]['message']);
        $this->assertSame($invoice->vat_amount, $invoice->fresh()->vat_amount, 'finance figures are never rewritten by ZATCA');

        // A document whose line rounding matches the document rounding passes and reconciles with the posted invoice.
        $ok = $this->invoice([], [['description' => 'A', 'quantity' => 4, 'unit_price' => 100], ['description' => 'B', 'quantity' => 1, 'unit_price' => 250]]);
        $rec = $this->service()->generate($ok, $this->admin());
        $this->assertSame('validated', $rec->status);
        $xml = Storage::disk('local')->get($rec->xml_path);
        $this->assertStringContainsString('<cbc:TaxableAmount currencyID="SAR">650.00</cbc:TaxableAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxAmount currencyID="SAR">97.50</cbc:TaxAmount>', $xml);
        $this->assertSame('97.50', (string) $ok->vat_amount);
        $this->assertSame((float) $ok->vat_amount, (float) VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $ok->id)->sum('vat_amount'));
    }

    public function test_xml_escapes_markup_and_keeps_arabic_text(): void
    {
        $invoice = $this->invoice([], [['description' => '<Steel> & "Bars" حديد تسليح', 'quantity' => 1, 'unit_price' => 100]]);
        $record = $this->service()->generate($invoice, $this->admin());
        $xml = Storage::disk('local')->get($record->xml_path);
        $this->assertStringContainsString('&lt;Steel&gt; &amp; ', $xml);
        $this->assertStringContainsString('حديد تسليح', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('شركة سيرا', json_encode($invoice->seller_snapshot, JSON_UNESCAPED_UNICODE));
    }

    public function test_invoice_type_maps_to_type_code_transaction_code_and_flow(): void
    {
        $standard = $this->service()->generate($this->invoice(), $this->admin());
        $this->assertSame(['388', '0100000', 'standard'], [$standard->invoice_type_code, $standard->transaction_code, $standard->invoice_type]);
        $simplified = $this->service()->generate($this->invoice(['invoice_type' => 'simplified']), $this->admin());
        $this->assertSame(['388', '0200000', 'simplified'], [$simplified->invoice_type_code, $simplified->transaction_code, $simplified->invoice_type]);
        $this->assertStringContainsString('name="0200000"', Storage::disk('local')->get($simplified->xml_path));
        $this->assertNotNull($simplified->reportingDeadline());
        $this->assertNull($standard->reportingDeadline());
    }

    public function test_local_validation_failure_is_retained_with_rule_ids(): void
    {
        $invoice = $this->invoice();
        $invoice->lines()->first()->update(['vat_amount' => 1.00]); // corrupt a line after posting: KSA-11 / BR-CO mismatch
        $record = $this->service()->generate($invoice, $this->admin());
        $this->assertSame('validation_failed', $record->status);
        $rules = collect($record->validation_errors)->pluck('rule')->all();
        $this->assertContains('KSA-11', $rules);
        $this->assertSame('validation_failed', $invoice->fresh()->zatca_status);
        try {
            $this->service()->sign($invoice->fresh(['zatcaRecord']), $this->admin());
            $this->fail('signing must be refused');
        } catch (ZatcaException $e) {
            $this->assertSame(ZatcaException::LOCAL_VALIDATION, $e->category);
        }
        $this->assertSame(0, $this->unit->fresh()->icv_counter, 'a failed validation consumes no counter value');
    }

    // ------------------------------------------------------ stamp / QR / chain

    public function test_stamp_produces_xades_signature_phase2_qr_and_chain_values(): void
    {
        $invoice = $this->invoice();
        $record = $this->service()->sign($invoice, $this->admin());
        $xml = $this->signedXml($record);
        $this->assertSame('signed', $record->status);
        $this->assertSame(1, $record->icv);
        $this->assertSame(ZatcaEgsUnit::FIRST_PREVIOUS_HASH, $record->previous_hash);
        $this->assertStringContainsString('<ext:UBLExtensions>', $xml);
        $this->assertStringContainsString('urn:oasis:names:specification:ubl:dsig:enveloped:xades', $xml);
        $this->assertStringContainsString('<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>', $xml);
        $this->assertStringContainsString('Type="http://uri.etsi.org/01903#SignedProperties"', $xml);
        $this->assertStringContainsString('<ds:DigestValue>'.$record->invoice_hash.'</ds:DigestValue>', $xml);
        $this->assertStringContainsString('<ds:X509Certificate>'.$this->certificateBase64.'</ds:X509Certificate>', $xml);
        $this->assertStringContainsString('<cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:ID>QR</cbc:ID>', $xml);
        preg_match('/<ds:SignatureValue>([^<]+)<\/ds:SignatureValue>/', $xml, $m);
        $signature = $m[1];
        $this->assertTrue(app(ZatcaCrypto::class)->verify($this->unit->public_key_pem, base64_decode($record->invoice_hash), $signature), 'the stamp verifies with the unit public key');

        // The hash is deterministic and excludes the extension, QR and signature blocks.
        $hashes = app(ZatcaHashService::class);
        $this->assertSame($record->invoice_hash, $hashes->hash($xml)['hash']);
        $this->assertSame($record->invoice_hash, $hashes->hash(Storage::disk('local')->get($record->xml_path))['hash']);
        $this->assertSame(44, strlen($record->invoice_hash));

        // Phase-2 QR from the signed values only.
        $tags = app(ZatcaQrService::class)->decode($record->qr_code);
        $this->assertSame('Seera Construction Co.', $tags[1]);
        $this->assertSame('300123456700003', $tags[2]);
        $this->assertSame($invoice->invoice_date->toDateString().'T'.$invoice->issue_time.'Z', $tags[3]);
        $this->assertSame('1150.00', $tags[4]);
        $this->assertSame('150.00', $tags[5]);
        $this->assertSame($record->invoice_hash, $tags[6]);
        $this->assertSame($signature, $tags[7]);
        $this->assertSame(base64_decode(preg_replace('/-----[A-Z ]+-----|\s+/', '', $this->unit->public_key_pem)), $tags[8]);
        $this->assertArrayNotHasKey(9, $tags);
        $this->assertStringContainsString($record->qr_code, $xml);

        // Chain: the next document carries this hash as PIH and the next counter value.
        $next = $this->service()->sign($this->invoice(['invoice_type' => 'simplified']), $this->admin());
        $this->assertSame(2, $next->icv);
        $this->assertSame($record->invoice_hash, $next->previous_hash);
        $this->assertSame($next->invoice_hash, $this->unit->fresh()->last_invoice_hash);
        $this->assertArrayHasKey(9, app(ZatcaQrService::class)->decode($next->qr_code), 'simplified documents carry the CA signature tag');
        $this->assertNotNull($invoice->fresh()->zatca_locked_at);
    }

    public function test_signed_artifact_is_immutable_and_tampering_is_detected(): void
    {
        $invoice = $this->invoice();
        $record = $this->service()->sign($invoice, $this->admin());
        $this->fakeZatca();
        Storage::disk('local')->put($record->signed_xml_path, str_replace('1150.00', '1250.00', $this->signedXml($record)));
        try {
            $this->service()->submit($invoice->fresh(['zatcaRecord']), $this->admin());
            $this->fail('tampered artifact must not be submitted');
        } catch (ZatcaException $e) {
            $this->assertSame('artifact_tampered', $e->errorCode);
        }
        Http::assertNothingSent();
    }

    // ------------------------------------------------- secrets / environments

    public function test_credentials_are_encrypted_and_never_exposed(): void
    {
        $raw = $this->unit->getRawOriginal('private_key_encrypted');
        $this->assertStringNotContainsString('BEGIN', (string) $raw);
        $this->assertStringContainsString('BEGIN', (string) $this->unit->privateKeyPem());
        $this->assertSame('test-secret-value', $this->unit->secret('production_secret_encrypted'));
        $this->assertArrayNotHasKey('private_key_encrypted', $this->unit->toArray());
        $this->assertArrayNotHasKey('production_secret_encrypted', $this->unit->toArray());

        $finance = $this->user(['ZATCA Invoicing' => ['view', 'edit', 'post', 'retry', 'export']]);
        $page = $this->actingAs($finance)->get(route('admin.accounting.zatca.settings.index'))->assertOk();
        $page->assertDontSee('test-secret-value')->assertDontSee('BEGIN EC PRIVATE KEY')->assertDontSee('PRIVATE KEY');
        $this->assertSame(0, ActivityLog::where('description', 'like', '%test-secret-value%')->count());
    }

    public function test_environment_is_explicit_and_production_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('zatca.production_enabled'));
        config(['zatca.environment' => 'production']);
        try {
            app(ZatcaEnvironment::class)->assertUsable();
            $this->fail('production must be refused');
        } catch (ZatcaException $e) {
            $this->assertSame('production_disabled', $e->errorCode);
        }
        config(['zatca.environment' => 'staging']);
        try {
            app(ZatcaEnvironment::class)->current();
            $this->fail('unknown environment must be refused');
        } catch (ZatcaException $e) {
            $this->assertSame('invalid_environment', $e->errorCode);
        }
        config(['zatca.environment' => 'sandbox']);
        $invoice = $this->invoice();
        $this->service()->sign($invoice, $this->admin());
        config(['zatca.environment' => 'production', 'zatca.production_enabled' => true]);
        try {
            $this->service()->submit($invoice->fresh(['zatcaRecord']), $this->admin());
            $this->fail('a sandbox-stamped record must not go to production');
        } catch (ZatcaException $e) {
            $this->assertSame('environment_mismatch', $e->errorCode);
        }
        Http::fake();
        Http::assertNothingSent();
    }

    public function test_sandbox_endpoint_headers_and_body_are_what_the_api_manual_specifies(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $record = $this->service()->process($invoice, $this->admin());
        Http::assertSent(function (ClientRequest $request) use ($record) {
            return $request->url() === self::SANDBOX.'/invoices/clearance/single'
                && $request->hasHeader('Accept-Version', 'V2') && $request->hasHeader('Clearance-Status', '1') && $request->hasHeader('Accept-Language', 'en')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode(base64_encode($this->certificateBase64).':test-secret-value'))
                && $request->data()['invoiceHash'] === $record->invoice_hash && $request->data()['uuid'] === $record->uuid
                && base64_decode($request->data()['invoice']) === $this->signedXml($record);
        });
        Http::assertSentCount(1);
    }

    // ------------------------------------------------------- transitions

    public function test_standard_invoice_clearance_success_stores_the_cleared_document(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $journals = JournalEntry::count();
        $vat = VatTransaction::count();
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertSame('cleared', $record->status);
        $this->assertSame('CLEARED', $record->zatca_status);
        $this->assertNotNull($record->cleared_at);
        $this->assertFalse($record->accepted_with_warnings);
        $this->assertSame(1, $record->submission_attempts);
        $this->assertNotNull($record->cleared_xml_path);
        $this->assertStringContainsString('ZATCA-QR', base64_decode($record->qr_code), 'the QR from the cleared document replaces the local one');
        $this->assertSame('cleared', $invoice->fresh()->zatca_status);
        $this->assertSame(JournalEntry::count(), $journals, 'ZATCA transmission posts nothing');
        $this->assertSame(VatTransaction::count(), $vat, 'ZATCA transmission records no VAT');
        $this->assertDatabaseHas('zatca_submissions', ['zatca_invoice_record_id' => $record->id, 'outcome' => 'accepted', 'http_status' => 200]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'ZATCA', 'action' => 'ZATCA invoice cleared']);
        $this->assertSame(1, ZatcaInvoiceRecord::count() - 3, 'seeded demo records aside, one record per invoice');
    }

    public function test_standard_invoice_rejection_keeps_errors_and_allows_regeneration_after_correction(): void
    {
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => Http::response(['validationResults' => ['errorMessages' => [['type' => 'ERROR', 'code' => 'BR-KSA-10', 'category' => 'KSA', 'message' => 'Buyer street missing', 'status' => 'ERROR']], 'warningMessages' => [], 'status' => 'ERROR'], 'clearanceStatus' => 'NOT_CLEARED'], 400)]);
        $invoice = $this->invoice();
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertSame('rejected', $record->status);
        $this->assertSame('BR-KSA-10', $record->response_errors[0]['code']);
        $this->assertSame('BR-KSA-10', $record->last_error_code);
        $this->assertSame(ZatcaException::BUSINESS_REJECTION, $record->last_error_category);
        $this->assertSame(1, $this->unit->fresh()->icv_counter, 'a rejected document still consumed its counter value and extended the chain');
        $this->assertSame($record->invoice_hash, $this->unit->fresh()->last_invoice_hash);

        $reset = $this->service()->regenerate($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame('not_generated', $reset->status);
        $this->assertSame($record->uuid, $reset->uuid);
        $this->fakeZatca();
        $again = $this->service()->process($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame('cleared', $again->status);
        $this->assertSame(2, $again->icv);
        $this->assertSame($record->invoice_hash, $again->previous_hash);
    }

    public function test_simplified_invoice_reporting_success_and_rejection(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice(['invoice_type' => 'simplified']);
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertSame('reported', $record->status);
        $this->assertSame('REPORTED', $record->zatca_status);
        $this->assertNotNull($record->reported_at);
        $this->assertNull($record->cleared_xml_path);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === self::SANDBOX.'/invoices/reporting/single' && ! $request->hasHeader('Clearance-Status'));

        $this->fake([self::SANDBOX.'/invoices/reporting/single' => Http::response(['validationResults' => ['errorMessages' => [['code' => 'BR-KSA-26', 'message' => 'Invalid PIH']], 'warningMessages' => []], 'reportingStatus' => 'NOT_REPORTED'], 400)]);
        $rejected = $this->service()->process($this->invoice(['invoice_type' => 'simplified']), $this->admin());
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('NOT_REPORTED', $rejected->zatca_status);
    }

    public function test_warnings_are_kept_on_an_accepted_document(): void
    {
        $this->fake([self::SANDBOX.'/invoices/reporting/single' => Http::response(['validationResults' => ['warningMessages' => [['code' => 'BR-KSA-09', 'message' => 'Seller address warning']], 'errorMessages' => [], 'status' => 'WARNING'], 'reportingStatus' => 'REPORTED'], 202)]);
        $record = $this->service()->process($this->invoice(['invoice_type' => 'simplified']), $this->admin());
        $this->assertSame('reported', $record->status);
        $this->assertTrue($record->accepted_with_warnings);
        $this->assertSame('BR-KSA-09', $record->response_warnings[0]['code']);
    }

    public function test_timeout_never_fabricates_a_rejection_and_retry_reuses_the_same_document(): void
    {
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds')]);
        $invoice = $this->invoice();
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertSame('outcome_unknown', $record->status);
        $this->assertNotNull($record->outcome_unknown_at);
        $this->assertSame('timeout', $record->last_error_code);
        $this->assertDatabaseHas('zatca_submissions', ['zatca_invoice_record_id' => $record->id, 'outcome' => 'timeout']);
        $hash = $record->invoice_hash;
        $uuid = $record->uuid;

        // Retry: identical hash and UUID, no new stamp, no new counter value.
        $this->fakeZatca();
        $retried = $this->service()->submit($invoice->fresh(['zatcaRecord']), $this->admin());
        Http::assertSent(fn (ClientRequest $request) => $request->data()['invoiceHash'] === $hash && $request->data()['uuid'] === $uuid);
        $this->assertSame('cleared', $retried->status);
        $this->assertSame(2, $retried->submission_attempts);
        $this->assertSame(1, $retried->icv);
        $this->assertSame(1, $this->unit->fresh()->icv_counter);
        $this->assertSame(2, ZatcaSubmission::where('zatca_invoice_record_id', $record->id)->count());

        // A duplicate answer to a retry after an unknown outcome stays "verification required".
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => fn () => throw new ConnectionException('timed out')]);
        $second = $this->service()->process($this->invoice(), $this->admin());
        $this->assertSame('outcome_unknown', $second->status);
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => Http::response(['validationResults' => ['errorMessages' => [['code' => 'DUPLICATE_INVOICE', 'message' => 'Invoice already exists']], 'warningMessages' => []]], 400)]);
        $dup = $this->service()->submit($second->customerInvoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame('outcome_unknown', $dup->status);
        $this->assertSame('duplicate_on_retry', $dup->last_error_code);
    }

    public function test_transient_server_errors_leave_the_signed_document_retryable(): void
    {
        $this->fake([self::SANDBOX.'/invoices/clearance/single' => Http::sequence()->push(['message' => 'boom'], 500)->push(['message' => 'slow down'], 429)->push(['message' => 'unauthorized'], 401)]);
        $invoice = $this->invoice();
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertSame('signed', $record->status);
        $this->assertSame(ZatcaException::SERVER_ERROR, $record->last_error_category);
        $record = $this->service()->submit($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame(ZatcaException::RATE_LIMIT, $record->last_error_category);
        $record = $this->service()->submit($invoice->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame(ZatcaException::AUTH, $record->last_error_category);
        $this->assertSame('signed', $record->status);
        $this->assertSame(3, $record->submission_attempts);
        $this->assertSame(1, $this->unit->fresh()->icv_counter);
    }

    public function test_double_submit_creates_no_duplicate_invoice_or_record(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $before = CustomerInvoice::count();
        $this->service()->process($invoice, $this->admin());
        try {
            $this->service()->process($invoice->fresh(['zatcaRecord']), $this->admin());
            $this->fail('an accepted document cannot be resubmitted');
        } catch (ZatcaException $e) {
            $this->assertSame('already_accepted', $e->errorCode);
        }
        Http::assertSentCount(1);
        $this->assertSame($before, CustomerInvoice::count());
        $this->assertSame(1, ZatcaInvoiceRecord::where('customer_invoice_id', $invoice->id)->count());
    }

    // ---------------------------------------------- permissions / scope / UI

    public function test_artifacts_are_private_and_downloads_need_the_export_right(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $record = $this->service()->process($invoice, $this->admin());
        $this->assertTrue(Storage::disk('local')->exists($record->signed_xml_path));
        $this->assertFalse(Storage::disk('local')->exists('public/'.$record->signed_xml_path));
        $viewer = $this->user(['ZATCA Invoicing' => ['view']]);
        $this->actingAs($viewer)->get(route('admin.accounting.zatca.xml', [$record, 'signed']))->assertForbidden();
        $exporter = $this->user(['ZATCA Invoicing' => ['view', 'export']]);
        $response = $this->actingAs($exporter)->get(route('admin.accounting.zatca.xml', [$record, 'signed']))->assertOk();
        $this->assertStringContainsString('300123456700003_', $response->headers->get('content-disposition'));
        $this->actingAs($exporter)->get(route('admin.accounting.zatca.xml', [$record, 'cleared']))->assertOk();
        $this->actingAs($exporter)->get(route('admin.accounting.zatca.xml', [$record, 'other']))->assertNotFound();
    }

    public function test_unauthorized_users_are_blocked_per_action(): void
    {
        $invoice = $this->invoice();
        $record = $invoice->zatcaRecord;
        $this->actingAs($this->user(['Accounts Receivable' => ['view']]))->get(route('admin.accounting.zatca.index'))->assertForbidden();
        $viewer = $this->user(['ZATCA Invoicing' => ['view']]);
        $this->actingAs($viewer)->get(route('admin.accounting.zatca.show', $record))->assertOk();
        $this->actingAs($viewer)->post(route('admin.accounting.zatca.submit', $record))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.accounting.zatca.retry', $record))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.accounting.zatca.settings.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.accounting.zatca.settings.csr', $this->unit))->assertForbidden();
        $this->assertSame('not_generated', $record->fresh()->status);
    }

    public function test_project_scope_hides_other_projects_records(): void
    {
        $invoice = $this->invoice();
        $other = Project::create(['name' => 'Other', 'code' => 'PRJ-OTHER', 'status' => 'active']);
        $outsider = $this->user(['ZATCA Invoicing' => ['view', 'post'], 'Accounts Receivable' => ['view']], $other);
        $this->actingAs($outsider)->get(route('admin.accounting.zatca.show', $invoice->zatcaRecord))->assertNotFound();
        $this->actingAs($outsider)->post(route('admin.accounting.zatca.submit', $invoice->zatcaRecord))->assertNotFound();
        $this->actingAs($outsider)->get(route('admin.accounting.zatca.index'))->assertOk()->assertDontSee($invoice->invoice_number);
    }

    public function test_a_stamped_invoice_cannot_be_reopened_or_edited(): void
    {
        $invoice = $this->invoice();
        $this->service()->sign($invoice, $this->admin());
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.reopen', $invoice), ['reason' => 'typo'])->assertSessionHasErrors('invoice');
        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertFalse($invoice->isEditable());
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertNotNull($invoice->zatca_locked_at);
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()->assertSee('can no longer be edited or reopened')->assertDontSee('Reopen for Correction');

        // An approved but never stamped invoice can still be reopened; its record is cancelled and a new one is issued on re-approval.
        $plain = $this->invoice();
        $uuid = $plain->zatcaRecord->uuid;
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.reopen', $plain), ['reason' => 'wrong amount'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $plain->fresh()->zatcaRecord->status);
        $this->assertNull($plain->fresh()->seller_snapshot);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $plain))->assertSessionHasNoErrors();
        $this->assertSame('not_generated', $plain->fresh()->zatcaRecord->status);
        $this->assertSame($uuid, $plain->fresh()->zatcaRecord->uuid);
    }

    public function test_receipts_and_the_invoice_workspace_still_work_after_clearance(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $this->service()->process($invoice, $this->admin());
        $bank = \App\Models\ChartOfAccount::where('account_code', \App\Services\Accounting\PostingService::BANK)->value('id');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), ['receipt_date' => now()->toDateString(), 'amount' => 1150, 'payment_method' => 'Bank Transfer', 'receipt_account_id' => $bank, 'idempotency_key' => 'ZATCA-RCPT-1'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertSame('cleared', $invoice->fresh()->zatca_status);

        $finance = $this->user(['Accounts Receivable' => ['view'], 'ZATCA Invoicing' => ['view', 'post', 'retry', 'export'], 'Customers' => ['view']]);
        $this->actingAs($finance)->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()
            ->assertSee('Cleared by ZATCA')->assertSee('Standard tax invoice')->assertSee($invoice->zatcaRecord->uuid)->assertSee('Download signed XML')->assertDontSee('not verified live');
        $this->actingAs($finance)->get(route('admin.accounting.zatca.show', $invoice->zatcaRecord))->assertOk()->assertSee('CLEARED')->assertSee('invoices/clearance/single');
        $this->actingAs($finance)->get(route('admin.accounting.zatca.index'))->assertOk()->assertSee($invoice->invoice_number);
    }

    public function test_arabic_readiness_warning_and_bilingual_fields(): void
    {
        CompanyProfile::query()->update(['name_ar' => null]);
        $invoice = $this->invoice();
        $warnings = collect($this->service()->readiness($invoice)['warnings'])->pluck('code')->all();
        $this->assertContains('seller_name_ar_missing', $warnings);
        $this->assertSame([], $this->service()->readiness($invoice)['errors'], 'Arabic is a human-readable requirement, not an XML blocker');
        $arabicUser = $this->admin();
        $arabicUser->update(['language' => 'Arabic']);
        $this->actingAs($arabicUser->fresh())->get(route('admin.accounting.zatca.show', $invoice->zatcaRecord))->assertOk()->assertSee('سجل الامتثال');
    }

    // ------------------------------------------------------------ onboarding

    public function test_onboarding_generates_a_csr_and_walks_compliance_to_production_against_the_faked_portal(): void
    {
        $unit = ZatcaEgsUnit::create(['name' => 'Onboarding unit', 'environment' => 'sandbox', 'invoice_types' => '1000', 'location' => 'RRRD2929', 'industry' => 'Construction']);
        $onboarding = app(ZatcaOnboardingService::class);
        $onboarding->generateCsr($unit, $this->admin());
        $unit->refresh();
        $this->assertSame('csr_generated', $unit->status);
        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $unit->csr);
        $this->assertTrue($unit->hasPrivateKey());
        $parsed = openssl_csr_get_subject($unit->csr);
        $this->assertSame('Onboarding unit', $parsed['CN']);
        $this->assertSame('SA', $parsed['C']);
        $this->assertSame('Seera Construction Co.', $parsed['O']);
        $this->assertSame('300123456700003', $unit->vat_number);
        $this->assertStringStartsWith('1-Seera ERP|2-', $unit->egs_serial);

        try {
            $onboarding->requestComplianceCsid($unit, '12', $this->admin());
            $this->fail('otp must be six digits');
        } catch (ZatcaException $e) {
            $this->assertSame('otp_invalid', $e->errorCode);
        }

        $token = base64_encode($this->certificateBase64);
        $this->fake([
            self::SANDBOX.'/compliance' => Http::response(['requestID' => 1234567890123, 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => $token, 'secret' => 'compliance-secret'], 200),
            self::SANDBOX.'/compliance/invoices' => Http::response(['validationResults' => ['warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'], 'reportingStatus' => 'REPORTED', 'clearanceStatus' => 'CLEARED'], 200),
            self::SANDBOX.'/production/csids' => Http::response(['requestID' => 1234567890123, 'tokenType' => 'PCSID', 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => $token, 'secret' => 'production-secret'], 200),
        ]);
        $onboarding->requestComplianceCsid($unit, '123345', $this->admin());
        $unit->refresh();
        $this->assertSame('compliance', $unit->status);
        $this->assertSame('1234567890123', $unit->compliance_request_id);
        $this->assertSame('compliance-secret', $unit->secret('compliance_secret_encrypted'));
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SANDBOX.'/compliance' && $r->hasHeader('OTP', '123345') && $r->hasHeader('Accept-Version', 'V2') && base64_decode($r->data()['csr']) === $unit->csr);
        $this->assertSame(0, ActivityLog::where('description', 'like', '%123345%')->count(), 'the OTP is never logged');

        try {
            $onboarding->requestProductionCsid($unit, $this->admin());
            $this->fail('checks first');
        } catch (ZatcaException $e) {
            $this->assertSame('compliance_checks_missing', $e->errorCode);
        }
        $onboarding->runComplianceChecks($unit, $this->admin());
        $unit->refresh();
        $this->assertSame(['standard_388', 'standard_383', 'standard_381'], array_keys($unit->compliance_checks));
        $this->assertTrue($onboarding->complianceChecksPassed($unit));
        $this->assertSame(3, $unit->icv_counter, 'compliance documents consume counter values like any generated document');
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SANDBOX.'/compliance/invoices' && $r->hasHeader('Authorization', 'Basic '.base64_encode($token.':compliance-secret')));
        $this->assertSame(3, ZatcaSubmission::where('zatca_egs_unit_id', $unit->id)->where('endpoint', 'compliance/invoices')->count());

        $onboarding->requestProductionCsid($unit, $this->admin());
        $unit->refresh();
        $this->assertSame('production', $unit->status);
        $this->assertSame('production-secret', $unit->secret('production_secret_encrypted'));
        $this->assertNotNull($unit->certificate_expires_at);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SANDBOX.'/production/csids' && $r->data()['compliance_request_id'] === '1234567890123');
    }

    public function test_settings_page_and_unit_form_for_the_configurer(): void
    {
        $finance = $this->user(['ZATCA Invoicing' => ['view', 'edit']]);
        $this->actingAs($finance)->get(route('admin.accounting.zatca.settings.index'))->assertOk()->assertSee('ZATCA Settings')->assertSee('Developer Portal sandbox')->assertSee('Seera test unit');
        $this->actingAs($finance)->post(route('admin.accounting.zatca.settings.store'), ['name' => 'Branch unit', 'invoice_types' => '1100', 'location' => 'RRRD2929', 'industry' => 'Construction'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('zatca_egs_units', ['name' => 'Branch unit', 'environment' => 'sandbox', 'status' => 'new']);
        $this->actingAs($finance)->post(route('admin.accounting.zatca.settings.store'), ['name' => 'Bad', 'invoice_types' => '0000'])->assertSessionHasErrors('invoice_types');
    }
}
