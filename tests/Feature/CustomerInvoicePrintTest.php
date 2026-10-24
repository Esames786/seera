<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Project;
use App\Models\ZatcaEgsUnit;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Zatca\ZatcaSubmissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\OnboardsZatcaUnit;
use Tests\TestCase;

/**
 * Bilingual printable customer invoice (and the shared note print view): issued
 * snapshot values, Arabic / English labels, ZATCA state and QR from the record,
 * permission and scope, and no database write on a print GET.
 */
class CustomerInvoicePrintTest extends TestCase
{
    use OnboardsZatcaUnit;
    use RefreshDatabase;

    private Project $project;

    private Project $otherProject;

    private Customer $customer;

    private ZatcaEgsUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => false, 'zatca.endpoints.sandbox' => self::SANDBOX]);
        CompanyProfile::query()->update(['name' => 'Seera Construction Co.', 'name_ar' => 'شركة سيرا للإنشاءات', 'vat_number' => '300123456700003', 'cr_number' => '1010123456', 'logo_path' => null,
            'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN']);
        $this->project = Project::create(['name' => 'Print Tower', 'code' => 'PRJ-PRINT', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Other Site', 'code' => 'PRJ-PRINT-O', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-PRINT', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
        $this->unit = $this->onboardedUnit();
    }

    private function invoice(array $extra = [], ?array $lines = null, bool $approve = true): CustomerInvoice
    {
        $lines ??= [['description' => 'Progress claim 1 — أعمال الخرسانة للطابق الأول', 'quantity' => 2, 'unit_price' => 500], ['description' => 'Steel reinforcement حديد تسليح', 'quantity' => 3, 'unit_price' => 250.5]];
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), $extra + [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => $this->project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'invoice_type' => 'standard', 'notes' => 'Payment within 30 days', 'lines' => $lines,
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        if ($approve) {
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        }
        $this->flushSession();

        return $invoice->fresh(['lines', 'customer', 'zatcaRecord']);
    }

    public function test_print_view_is_bilingual_and_shows_issued_values_totals_and_zatca_state(): void
    {
        $invoice = $this->invoice();
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertOk();
        $page->assertSee('Tax Invoice')->assertSee('فاتورة ضريبية')->assertSee('البائع')->assertSee('المشتري')->assertSee('الوصف')->assertSee('الإجمالي المستحق شامل الضريبة');
        $page->assertSee('Seera Construction Co.')->assertSee('شركة سيرا للإنشاءات')->assertSee('300123456700003')->assertSee('Riyadh Developers LLC')->assertSee('300987654300003');
        $page->assertSee($invoice->invoice_number)->assertSee($invoice->invoice_date->toDateString())->assertSee('7890, King Fahd Road, Al Olaya, Riyadh, 12211, SA');
        $page->assertSee('أعمال الخرسانة للطابق الأول')->assertSee('حديد تسليح')->assertSee('1,000.00')->assertSee('751.50')->assertSee('1,751.50')->assertSee('262.73')->assertSee('2,014.23');
        $page->assertSee(__('zatca.status_not_generated'))->assertSee(__('zatca.not_verified'))->assertSee('Payment within 30 days')->assertDontSee('zatca-qr');
        $page->assertSee('dir="rtl"', false)->assertSee('class="ar"', false)->assertSee('@page', false);
        // Simplified wording.
        $simple = $this->invoice(['invoice_type' => 'simplified']);
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $simple))->assertOk()->assertSee('Simplified Tax Invoice')->assertSee('فاتورة ضريبية مبسطة');
    }

    public function test_print_uses_the_frozen_snapshots_not_the_current_masters(): void
    {
        $invoice = $this->invoice();
        $this->customer->update(['name' => 'Renamed Buyer', 'vat_number' => '300000000000003', 'street' => 'New Street']);
        CompanyProfile::query()->update(['name' => 'Renamed Seller', 'name_ar' => 'اسم جديد', 'vat_number' => '399999999900003']);
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertOk();
        $page->assertSee('Riyadh Developers LLC')->assertSee('Seera Construction Co.')->assertSee('شركة سيرا للإنشاءات')->assertSee('300123456700003')->assertSee('Olaya Street');
        $page->assertDontSee('Renamed Buyer')->assertDontSee('Renamed Seller')->assertDontSee('اسم جديد')->assertDontSee('399999999900003')->assertDontSee('New Street');
        $this->assertSame('1,751.50', number_format((float) $invoice->fresh()->taxable_amount, 2), 'totals are the stored ones');
    }

    public function test_print_shows_the_stored_qr_and_the_real_zatca_state_after_clearance(): void
    {
        $invoice = $this->invoice();
        $this->fakeZatca();
        $record = app(ZatcaSubmissionService::class)->process($invoice, $this->admin());
        $this->assertSame('cleared', $record->status);
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertOk();
        $page->assertSee(__('zatca.status_cleared'))->assertDontSee(__('zatca.not_verified'))->assertSee($record->uuid)->assertSee('data-qr="'.$record->qr_code.'"', false)->assertSee('vendor/qrcode-generator/qrcode.js');
        $this->assertFileExists(public_path('vendor/qrcode-generator/qrcode.js'));
        // Nothing private leaks: no XML paths, hashes or secrets on the customer copy.
        $page->assertDontSee('signed_xml_path')->assertDontSee($record->invoice_hash)->assertDontSee('test-secret-value')->assertDontSee('BEGIN');
    }

    public function test_print_is_read_only_permission_and_scope_gated_and_refuses_drafts(): void
    {
        $invoice = $this->invoice();
        $before = collect(['customer_invoices', 'customer_invoice_lines', 'activity_logs', 'zatca_invoice_records', 'journal_entries'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $updated = $invoice->updated_at->toDateTimeString();
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertOk();
        $after = collect(['customer_invoices', 'customer_invoice_lines', 'activity_logs', 'zatca_invoice_records', 'journal_entries'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $this->assertSame($before->all(), $after->all(), 'a print GET writes nothing');
        $this->assertSame($updated, $invoice->fresh()->updated_at->toDateTimeString());

        $noAr = $this->user(['Customers' => ['view']]);
        $this->actingAs($noAr)->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertForbidden();
        $other = $this->user(['Accounts Receivable' => ['view']], $this->otherProject);
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertNotFound();
        $same = $this->user(['Accounts Receivable' => ['view']], $this->project);
        $this->actingAs($same)->get(route('admin.accounting.accounts-receivable.print', $invoice))->assertOk();
        $draft = $this->invoice(approve: false);
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.print', $draft))->assertNotFound();
        // The workspace offers Print only for posted invoices.
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()->assertSee(__('workspace.inv_print'));
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $draft))->assertOk()->assertDontSee(__('workspace.inv_print'));
    }

    public function test_note_print_shares_the_bilingual_layout_and_the_stored_qr(): void
    {
        $invoice = $this->invoice();
        $note = app(CustomerNoteService::class)->post(app(CustomerNoteService::class)->createDraft($invoice, 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'ترجيع السلع — returned goods'],
            [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => 1, 'unit_price' => 500]], $this->admin()), $this->admin());
        $this->fakeZatca();
        $record = app(ZatcaSubmissionService::class)->process($note, $this->admin());
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.print', $note))->assertOk();
        $page->assertSee('Credit Note')->assertSee('إشعار دائن')->assertSee('ترجيع السلع')->assertSee($invoice->invoice_number)->assertSee('شركة سيرا للإنشاءات')->assertSee('data-qr="'.$record->qr_code.'"', false)->assertSee('@page', false);
        $other = $this->user(['Accounts Receivable' => ['view']], $this->otherProject);
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.notes.print', $note))->assertNotFound();
    }
}
