<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client follow-up of 8 September 2026 (CR-15 .. CR-18).
 */
class ClientChangeRequestsRound2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    // CR-15 -----------------------------------------------------------------

    public function test_project_classification_can_be_created_inline_and_saved_on_a_project(): void
    {
        $this->actingAs($this->admin())->get(route('admin.master.projects.create'))
            ->assertOk()
            ->assertSee('data-quick-create="qc-classification"', false)
            ->assertSee('Not classified');

        $classification = $this->actingAs($this->admin())
            ->postJson(route('admin.master.project-classifications.store'), ['name' => 'Infrastructure', 'status' => 'active'])
            ->assertCreated()
            ->assertJsonPath('label', 'Infrastructure')
            ->json();

        // Same name again is refused so the list stays clean.
        $this->actingAs($this->admin())
            ->postJson(route('admin.master.project-classifications.store'), ['name' => 'Infrastructure'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);

        $this->actingAs($this->admin())
            ->post(route('admin.master.projects.store'), [
                'name' => 'Ring Road Extension',
                'code' => 'PRJ-CLS-1',
                'project_classification_id' => $classification['id'],
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.projects.index'));

        $project = Project::where('code', 'PRJ-CLS-1')->firstOrFail();
        $this->assertSame('Infrastructure', $project->classification->name);

        $this->actingAs($this->admin())->get(route('admin.master.projects.show', $project))->assertOk()->assertSee('Infrastructure');
        $this->actingAs($this->admin())->get(route('admin.master.projects.index', ['classification' => $classification['id']]))->assertOk()->assertSee('Ring Road Extension');
        $this->actingAs($this->admin())->get(route('admin.master.project-classifications.index'))->assertOk()->assertSee('Infrastructure');

        // In use, so it cannot be deleted.
        $this->actingAs($this->admin())
            ->delete(route('admin.master.project-classifications.destroy', $classification['id']))
            ->assertSessionHasErrors('classification');
    }

    // CR-17 -----------------------------------------------------------------

    public function test_payment_terms_start_with_the_current_choices_and_can_be_extended_inline(): void
    {
        $this->assertSame(['Cash', '15 Days', '30 Days', '60 Days'], PaymentTerm::orderBy('days')->pluck('name')->all());

        // Seeded suppliers were saved with the old text and are linked to the matching term.
        $cement = Supplier::where('code', 'SUP-001')->firstOrFail();
        $this->assertSame('30 Days', $cement->paymentTerm?->name);
        $this->assertSame(30, $cement->paymentTerm->days);

        $this->actingAs($this->admin())->get(route('admin.master.suppliers.create'))
            ->assertOk()
            ->assertSee('data-quick-create="qc-payment-term"', false)
            ->assertSee('30 Days (30 days)');

        $term = $this->actingAs($this->admin())
            ->postJson(route('admin.master.payment-terms.store'), ['name' => '45 Days', 'days' => 45])
            ->assertCreated()
            ->assertJsonPath('label', '45 Days (45 days)')
            ->json();

        $this->actingAs($this->admin())
            ->put(route('admin.master.suppliers.update', $cement), [
                'name' => $cement->name,
                'code' => $cement->code,
                'status' => 'active',
                'payment_term_id' => $term['id'],
            ])
            ->assertRedirect(route('admin.master.suppliers.index'));

        $cement->refresh();
        $this->assertSame($term['id'], $cement->payment_term_id);
        $this->assertSame('45 Days', $cement->payment_terms, 'legacy text stays in step with the chosen term');

        // A bill saved without a due date falls due after the agreed days.
        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $cement->id,
                'bill_number' => 'BILL-TERMS-001',
                'bill_date' => '2026-09-01',
                'vat_rate' => 15,
                'lines' => [['description' => 'Cement bags', 'quantity' => 100, 'unit_price' => 20]],
            ])
            ->assertRedirect();

        $bill = SupplierBill::where('bill_number', 'BILL-TERMS-001')->firstOrFail();
        $this->assertSame('2026-10-16', $bill->due_date->toDateString());

        $this->actingAs($this->admin())->get(route('admin.master.payment-terms.index'))->assertOk()->assertSee('45 Days');
    }

    // CR-18 -----------------------------------------------------------------

    public function test_linked_payable_account_is_a_chart_of_accounts_link_that_drives_posting(): void
    {
        $control = $this->account('2100');

        $this->actingAs($this->admin())->get(route('admin.master.suppliers.create'))
            ->assertOk()
            ->assertSee('data-quick-create="qc-payable-account"', false)
            ->assertSee($control->label());

        // The dialog creates a liability sub-account under 2100 with the next free code.
        $account = $this->actingAs($this->admin())
            ->postJson(route('admin.accounting.chart-of-accounts.store'), [
                'account_name' => 'Accounts Payable - Gulf Steel',
                'account_type' => 'liability',
                'normal_balance' => 'credit',
                'opening_balance' => 0,
                'status' => 'active',
                'parent_id' => $control->id,
            ])
            ->assertCreated()
            ->assertJsonPath('code', '2101')
            ->json();

        $steel = Supplier::where('code', 'SUP-003')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.master.suppliers.update', $steel), [
                'name' => $steel->name,
                'code' => $steel->code,
                'status' => 'active',
                'linked_account_id' => $account['id'],
            ])
            ->assertRedirect(route('admin.master.suppliers.index'));

        $steel->refresh();
        $this->assertSame($account['id'], $steel->linked_account_id);
        $this->assertSame('2101 - Accounts Payable - Gulf Steel', $steel->linked_account);

        // An account outside the payables group is refused.
        $this->actingAs($this->admin())
            ->put(route('admin.master.suppliers.update', $steel), [
                'name' => $steel->name, 'code' => $steel->code, 'status' => 'active',
                'linked_account_id' => $this->account('1110')->id,
            ])
            ->assertSessionHasErrors('linked_account_id');

        $groupBefore = $control->groupBalance();

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $steel->id,
                'bill_number' => 'BILL-LINK-001',
                'bill_date' => now()->toDateString(),
                'vat_rate' => 15,
                'lines' => [['description' => 'Rebar', 'quantity' => 10, 'unit_price' => 1000]],
            ])
            ->assertRedirect();

        $bill = SupplierBill::where('bill_number', 'BILL-LINK-001')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertRedirect();

        $entry = $bill->refresh()->journalEntry;
        $this->assertNotNull($entry);
        $this->assertSame(11500.0, (float) $entry->lines->firstWhere('chart_of_account_id', $account['id'])->credit, 'the bill credits the supplier\'s linked sub-account');
        $this->assertNull($entry->lines->firstWhere('chart_of_account_id', $control->id), 'nothing goes to the control account for a linked supplier');

        // The dashboard payables figure covers the whole 2100 group.
        $this->assertSame(11500.0, round($control->fresh()->groupBalance() - $groupBefore, 2));
        $this->actingAs($this->admin())->get(route('admin.accounting.dashboard'))->assertOk();

        // A supplier without a link still posts to 2100 exactly as before.
        $fuel = Supplier::where('code', 'SUP-002')->firstOrFail();
        $this->assertNull($fuel->linked_account_id);

        $this->actingAs($this->admin())
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => $fuel->id,
                'bill_number' => 'BILL-LINK-002',
                'bill_date' => now()->toDateString(),
                'vat_rate' => 15,
                'lines' => [['description' => 'Diesel', 'quantity' => 1, 'unit_price' => 1000]],
            ])
            ->assertRedirect();
        $fuelBill = SupplierBill::where('bill_number', 'BILL-LINK-002')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $fuelBill))->assertRedirect();
        $this->assertSame(1150.0, (float) $fuelBill->refresh()->journalEntry->lines->firstWhere('chart_of_account_id', $control->id)->credit);

        $this->actingAs($this->admin())->get(route('admin.master.suppliers.show', $steel))->assertOk()->assertSee('2101 - Accounts Payable - Gulf Steel');
    }

    // CR-16 -----------------------------------------------------------------

    public function test_site_pages_show_a_real_map_with_the_saved_pin_and_radius(): void
    {
        $site = Site::whereNotNull('latitude')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.master.sites.show', $site))
            ->assertOk()
            ->assertSee('id="site-map"', false)
            ->assertSee('data-lat="'.$site->latitude.'"', false)
            ->assertSee('data-lng="'.$site->longitude.'"', false)
            ->assertSee('data-radius="'.$site->geofence_radius.'"', false)
            ->assertSee('leaflet.min.js')
            ->assertSee('tile.openstreetmap.org')
            ->assertDontSee('map-placeholder');

        $this->actingAs($this->admin())->get(route('admin.master.sites.edit', $site))
            ->assertOk()
            ->assertSee('data-editable="1"', false)
            ->assertSee('Click the map to drop the site pin');

        $this->actingAs($this->admin())->get(route('admin.master.sites.create'))
            ->assertOk()
            ->assertSee('No coordinates saved yet.');
    }
}
