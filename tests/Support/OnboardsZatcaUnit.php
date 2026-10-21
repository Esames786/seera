<?php

namespace Tests\Support;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Zatca\ZatcaCrypto;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Shared ZATCA test fixtures: a self-signed secp256k1 certificate standing in
 * for a sandbox production CSID, a faked FATOORA platform, and role helpers.
 * Nothing here talks to a real ZATCA endpoint.
 */
trait OnboardsZatcaUnit
{
    protected const SANDBOX = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal';

    protected string $certificateBase64 = '';

    protected int $seq = 0;

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** A test key pair and a self-signed secp256k1 certificate standing in for a sandbox production CSID. */
    protected function onboardedUnit(string $environment = 'sandbox', string $types = '1100'): ZatcaEgsUnit
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

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null): User
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

    /** Laravel keeps earlier stubs and the first match wins; reset the client before each new fake so a test can stage several ZATCA answers. */
    protected function fake(array $responses): void
    {
        app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();
        Http::fake($responses);
    }

    protected function fakeZatca(array $responses = []): void
    {
        $this->fake($responses + [
            self::SANDBOX.'/invoices/clearance/single' => fn (ClientRequest $request) => Http::response(['validationResults' => ['infoMessages' => [], 'warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'],
                'clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode($this->clearedXml($request))], 200),
            self::SANDBOX.'/invoices/reporting/single' => Http::response(['validationResults' => ['infoMessages' => [], 'warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'], 'reportingStatus' => 'REPORTED'], 200),
        ]);
    }

    /** The faked platform returns the submitted document with a ZATCA QR in place of the seller's. */
    protected function clearedXml(ClientRequest $request): string
    {
        $xml = base64_decode($request->data()['invoice']);

        return preg_replace('/<cbc:EmbeddedDocumentBinaryObject mimeCode="text\/plain">[^<]*<\/cbc:EmbeddedDocumentBinaryObject>(\s*<\/cac:Attachment>\s*<\/cac:AdditionalDocumentReference>\s*<cac:Signature>)/',
            '<cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">'.base64_encode('ZATCA-QR').'</cbc:EmbeddedDocumentBinaryObject>$1', $xml, 1);
    }

    protected function signedXml(ZatcaInvoiceRecord $record): string
    {
        return Storage::disk('local')->get($record->signed_xml_path);
    }
}
