<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Project;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaSubmission;
use App\Services\Zatca\ZatcaEnvironment;
use App\Services\Zatca\ZatcaException;
use App\Services\Zatca\ZatcaSubmissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\OnboardsZatcaUnit;
use Tests\TestCase;

/**
 * Production environment controls and failure behaviour that must hold before
 * any production onboarding: production disabled by default and never inferred
 * from APP_ENV, environment separation of credentials and records, certificate
 * expiry, no secret leakage, and a monotonic chain under out-of-order stamping.
 */
class ZatcaProductionControlsTest extends TestCase
{
    use OnboardsZatcaUnit;
    use RefreshDatabase;

    private Project $project;

    private Customer $customer;

    private ZatcaEgsUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => false, 'zatca.endpoints.sandbox' => self::SANDBOX]);
        CompanyProfile::query()->update(['name' => 'Seera Construction Co.', 'name_ar' => 'شركة سيرا للإنشاءات', 'vat_number' => '300123456700003', 'cr_number' => '1010123456',
            'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN']);
        $this->project = Project::create(['name' => 'Controls Tower', 'code' => 'PRJ-CTL', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-CTL', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
        $this->unit = $this->onboardedUnit();
    }

    private function invoice(): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => $this->project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'invoice_type' => 'standard', 'lines' => [['description' => 'Works', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->flushSession();

        return $invoice->fresh(['lines', 'zatcaRecord']);
    }

    public function test_production_is_disabled_by_default_and_never_inferred_from_app_env(): void
    {
        $this->assertFalse(config('zatca.production_enabled'));
        $this->assertSame('sandbox', app(ZatcaEnvironment::class)->current());
        // APP_ENV=production changes nothing about the ZATCA environment.
        app()->detectEnvironment(fn () => 'production');
        $this->assertSame('sandbox', app(ZatcaEnvironment::class)->current());
        $this->assertSame(self::SANDBOX, app(ZatcaEnvironment::class)->baseUrl());
        // Production selected but not enabled: refused before any call.
        config(['zatca.environment' => 'production', 'zatca.production_enabled' => false]);
        try {
            app(ZatcaEnvironment::class)->assertUsable();
            $this->fail('production must be refused');
        } catch (ZatcaException $e) {
            $this->assertSame(ZatcaException::CONFIGURATION, $e->category);
        }
        // Enabled flag without the environment is still sandbox.
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => true]);
        $this->assertSame('sandbox', app(ZatcaEnvironment::class)->current());
    }

    public function test_a_record_of_another_environment_is_never_submitted_and_sandbox_credentials_never_reach_production(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        app(ZatcaSubmissionService::class)->sign($invoice, $this->admin());
        config(['zatca.environment' => 'production', 'zatca.production_enabled' => true, 'zatca.endpoints.production' => 'https://zatca.invalid/core']);
        try {
            app(ZatcaSubmissionService::class)->submit($invoice->fresh(['zatcaRecord']), $this->admin());
            $this->fail('environment mismatch must be refused');
        } catch (ZatcaException $e) {
            $this->assertSame('environment_mismatch', $e->errorCode);
        }
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame('signed', $invoice->fresh(['zatcaRecord'])->zatcaRecord->status);
        // The production unit lookup ignores the sandbox unit.
        $this->assertNull(app(ZatcaSubmissionService::class)->unit());
    }

    public function test_expired_production_certificate_blocks_readiness(): void
    {
        $invoice = $this->invoice();
        $this->unit->forceFill(['certificate_expires_at' => now()->subDay()])->save();
        $readiness = app(ZatcaSubmissionService::class)->readiness($invoice);
        $this->assertContains('unit_certificate_expired', array_column($readiness['errors'], 'code'));
        try {
            app(ZatcaSubmissionService::class)->generate($invoice, $this->admin());
            $this->fail('expired certificate must not generate');
        } catch (ZatcaException $e) {
            $this->assertSame('unit_certificate_expired', $e->errorCode);
        }
        $this->unit->forceFill(['certificate_expires_at' => now()->addYear()])->save();
        $this->assertNotContains('unit_certificate_expired', array_column(app(ZatcaSubmissionService::class)->readiness($invoice)['errors'], 'code'));
    }

    public function test_no_secret_reaches_logs_submissions_or_screens(): void
    {
        $this->fakeZatca();
        $invoice = $this->invoice();
        $record = app(ZatcaSubmissionService::class)->process($invoice, $this->admin());
        $private = $this->unit->secret('private_key_encrypted');
        $token = $this->unit->secret('production_token_encrypted');
        $needles = ['test-secret-value', $token, substr(preg_replace('/\s+/', '', $private), 30, 40)];
        foreach (ActivityLog::all() as $log) {
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, (string) $log->description.' '.$log->action);
            }
        }
        foreach (ZatcaSubmission::all() as $submission) {
            $blob = json_encode($submission->getAttributes());
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $blob);
            }
        }
        $pages = [route('admin.accounting.zatca.show', $record), route('admin.accounting.zatca.settings.index'), route('admin.accounting.accounts-receivable.show', $invoice), route('admin.accounting.accounts-receivable.print', $invoice)];
        foreach ($pages as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $html, $url);
            }
        }
        // Encrypted at rest: the raw columns are not the plaintext.
        $raw = DB::table('zatca_egs_units')->where('id', $this->unit->id)->first();
        $this->assertNotSame('test-secret-value', $raw->production_secret_encrypted);
        $this->assertStringNotContainsString('BEGIN', (string) $raw->private_key_encrypted);
        // Private XML is only served through the export permission.
        $viewer = $this->user(['ZATCA Invoicing' => ['view']]);
        $this->actingAs($viewer)->get(route('admin.accounting.zatca.xml', [$record, 'signed']))->assertForbidden();
    }

    public function test_out_of_order_stamping_keeps_the_chain_monotonic_and_linked(): void
    {
        $this->fakeZatca();
        $a = $this->invoice();
        $b = $this->invoice();
        $service = app(ZatcaSubmissionService::class);
        $service->generate($a, $this->admin()); // preview for A first (consumes nothing)
        $recordB = $service->sign($b, $this->admin()); // B stamped before A
        $recordA = $service->sign($a->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame([1, 2], [$recordB->icv, $recordA->icv]);
        $this->assertSame(ZatcaEgsUnit::FIRST_PREVIOUS_HASH, $recordB->previous_hash);
        $this->assertSame($recordB->invoice_hash, $recordA->previous_hash, 'A chains to B because B was stamped first');
        $this->assertSame(2, $this->unit->fresh()->icv_counter);
        // Submitting A before B is allowed; each keeps its own identity and the stored hashes never change.
        $service->submit($a->fresh(['zatcaRecord']), $this->admin());
        $service->submit($b->fresh(['zatcaRecord']), $this->admin());
        $this->assertSame([$recordA->invoice_hash, $recordB->invoice_hash], [$a->fresh(['zatcaRecord'])->zatcaRecord->invoice_hash, $b->fresh(['zatcaRecord'])->zatcaRecord->invoice_hash]);
        $this->assertSame(['cleared', 'cleared'], [$a->fresh(['zatcaRecord'])->zatcaRecord->status, $b->fresh(['zatcaRecord'])->zatcaRecord->status]);
    }
}
