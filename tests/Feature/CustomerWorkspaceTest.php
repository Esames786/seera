<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Customer connected workspace: List → View (read-only) → Edit / Manage.
 * Each panel keeps its own permission and access scope; the customer in the
 * URL is authoritative; invoice approval and receipts never happen here.
 */
class CustomerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Project $mine;

    protected Project $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->customer = Customer::orderBy('id')->firstOrFail();
        $this->mine = Project::create(['name' => 'Mine', 'code' => 'PRJ-CW-A', 'customer_id' => $this->customer->id, 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Theirs', 'code' => 'PRJ-CW-B', 'customer_id' => $this->customer->id, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'a'): User
    {
        $role = Role::create(['name' => 'CW role '.$suffix, 'code' => 'CW_ROLE_'.strtoupper($suffix), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'CW '.$suffix, 'email' => 'cw-'.$suffix.'@example.test', 'username' => 'cw.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function approvedInvoice(Project $project, float $net = 1000, ?string $dueDaysAgo = null): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'project_id' => $project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Claim', 'quantity' => 1, 'unit_price' => $net]],
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        if ($dueDaysAgo !== null) {
            $invoice->update(['due_date' => now()->subDays((int) $dueDaysAgo)->toDateString()]);
        }

        return $invoice->fresh();
    }

    protected function receive(CustomerInvoice $invoice, float $amount, string $key): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => now()->toDateString(), 'amount' => $amount, 'payment_method' => 'Bank Transfer',
            'receipt_account_id' => ChartOfAccount::where('account_code', PostingService::BANK)->value('id'), 'idempotency_key' => $key,
        ]);
    }

    protected function panel(User $user, string $panel, array $query = []): TestResponse
    {
        return $this->actingAs($user)->getJson(route('admin.master.customers.workspace.panel', [$this->customer, $panel] + $query));
    }

    protected function profile(array $extra = []): array
    {
        return $extra + ['name' => $this->customer->name, 'code' => $this->customer->code, 'type' => $this->customer->type, 'status' => 'active'];
    }

    // ------------------------------------------------------------------ tests

    public function test_list_offers_separate_view_and_edit_manage_actions_by_permission(): void
    {
        $this->actingAs($this->admin())->get(route('admin.master.customers.index', ['search' => $this->customer->code]))->assertOk()
            ->assertSee('>View<', false)->assertSee('Edit / Manage')->assertDontSee('>Open<', false);

        $viewer = $this->user(['Customers' => ['view']], null, 'viewer');
        $this->actingAs($viewer)->get(route('admin.master.customers.index', ['search' => $this->customer->code]))->assertOk()
            ->assertSee('>View<', false)->assertDontSee('Edit / Manage')->assertDontSee('>Delete</button>', false);
        $this->actingAs($viewer)->get(route('admin.master.customers.edit', $this->customer))->assertForbidden();
    }

    public function test_view_is_read_only_and_a_get_causes_no_write(): void
    {
        $this->customer->contacts()->create(['location_type' => 'office', 'name' => 'Office Person']);
        $this->customer->notes()->create(['user_id' => $this->admin()->id, 'note' => 'Call before visiting']);
        $before = [$this->customer->fresh()->updated_at, $this->customer->contacts()->count(), $this->customer->notes()->count(), \App\Models\ActivityLog::count()];

        $page = $this->actingAs($this->admin())->get(route('admin.master.customers.show', $this->customer))->assertOk();
        $page->assertSee($this->customer->code)->assertSee($this->customer->name)->assertSee('Read-only customer view')
            ->assertSee('Office Person')->assertSee('Call before visiting')->assertSee('id="invoices"', false)->assertSee('id="ageing"', false);
        // No editor, no save button and no form that targets this customer (the page shell's sign-out form is not a write to data).
        $page->assertDontSee('data-related-save', false)->assertDontSee('name="'.SaveAction::FIELD.'"', false)
            ->assertDontSee('data-workspace-related-host', false)
            ->assertDontSee('action="'.route('admin.master.customers.update', $this->customer).'"', false)
            ->assertDontSee(route('admin.master.customers.workspace.save', [$this->customer, 'contacts']), false)
            ->assertDontSee(route('admin.master.customers.contacts.store', $this->customer), false);

        $this->assertEquals($before, [$this->customer->fresh()->updated_at, $this->customer->contacts()->count(), $this->customer->notes()->count(), \App\Models\ActivityLog::count()]);

        // Without finance rights the money panels and header figures are absent.
        $viewer = $this->user(['Customers' => ['view']], null, 'viewer');
        $this->actingAs($viewer)->get(route('admin.master.customers.show', $this->customer))->assertOk()
            ->assertDontSee('id="invoices"', false)->assertDontSee('Outstanding receivable')->assertDontSee('Edit / Manage');
    }

    public function test_edit_workspace_shows_identity_and_only_permitted_panels(): void
    {
        $page = $this->actingAs($this->admin())->get(route('admin.master.customers.edit', $this->customer))->assertOk();
        $page->assertSee('Customer Workspace: '.$this->customer->name)->assertSee($this->customer->code)->assertSee('data-workspace-nav', false);
        foreach (['contacts', 'notes', 'projects', 'invoices', 'receipts', 'ageing', 'accounting', 'zatca', 'activity'] as $key) {
            $page->assertSee('data-workspace-related="'.$key.'"', false);
        }
        $page->assertSee('name="'.SaveAction::FIELD.'" value="new"', false)->assertDontSee('name="new_note"', false);

        $clerk = $this->user(['Customers' => ['view', 'edit'], 'Projects' => ['view']], null, 'clerk');
        $page = $this->actingAs($clerk)->get(route('admin.master.customers.edit', $this->customer))->assertOk();
        $page->assertSee('data-workspace-related="contacts"', false)->assertSee('data-workspace-related="projects"', false);
        foreach (['invoices', 'receipts', 'ageing', 'accounting', 'zatca', 'activity'] as $key) {
            $page->assertDontSee('data-workspace-related="'.$key.'"', false);
        }
        $page->assertDontSee('Outstanding receivable');
    }

    public function test_profile_save_stays_close_exits_and_new_opens_a_fresh_form(): void
    {
        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $this->customer), $this->profile(['phone' => '+966 11 000 0000', SaveAction::FIELD => SaveAction::STAY]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.master.customers.edit', $this->customer));
        $this->assertSame('+966 11 000 0000', $this->customer->fresh()->phone);

        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $this->customer), $this->profile([SaveAction::FIELD => SaveAction::CLOSE]))->assertRedirect(route('admin.master.customers.index'));
        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $this->customer), $this->profile([SaveAction::FIELD => SaveAction::CLOSE, SaveAction::RETURN_FIELD => '/admin/accounting/dashboard']))->assertRedirect('/admin/accounting/dashboard');
        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $this->customer), $this->profile([SaveAction::FIELD => SaveAction::NEW]))->assertRedirect(route('admin.master.customers.create'));

        $this->actingAs($this->admin())->post(route('admin.master.customers.store'), ['name' => 'New Client', 'type' => 'Company', 'status' => 'active', SaveAction::FIELD => SaveAction::STAY])->assertSessionHasNoErrors();
        $created = Customer::where('name', 'New Client')->firstOrFail();
        $this->assertSame(0, $created->contacts()->count());
    }

    public function test_contacts_and_notes_are_managed_inside_the_workspace_and_belong_to_this_customer(): void
    {
        $other = Customer::where('id', '!=', $this->customer->id)->orderBy('id')->firstOrFail();
        $foreignContact = $other->contacts()->create(['location_type' => 'office', 'name' => 'Foreign Person']);
        $foreignNote = $other->notes()->create(['user_id' => $this->admin()->id, 'note' => 'Foreign note']);
        $mySite = Site::create(['name' => 'Mine site', 'code' => 'SITE-CW-A', 'project_id' => $this->mine->id, 'status' => 'active']);
        $otherProject = Project::create(['name' => 'Other customer project', 'code' => 'PRJ-CW-X', 'customer_id' => $other->id, 'status' => 'active']);
        $otherSite = Site::create(['name' => 'Other site', 'code' => 'SITE-CW-X', 'project_id' => $otherProject->id, 'status' => 'active']);

        // Add a site contact on a permitted site; a site of another customer's project is refused.
        $save = fn (array $body) => $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'contacts']), $body);
        $save(['location_type' => 'site', 'site_id' => $otherSite->id, 'name' => 'Wrong site'])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $save(['location_type' => 'site', 'site_id' => $mySite->id, 'name' => 'Site Person', 'phone' => '+966 5'])->assertOk()
            ->assertJsonPath('panel_url', route('admin.master.customers.workspace.panel', [$this->customer, 'contacts']));
        $contact = $this->customer->contacts()->where('name', 'Site Person')->firstOrFail();
        $this->assertSame($mySite->id, $contact->site_id);

        // Edit in place; the record must be this customer's.
        $this->panel($this->admin(), 'contacts', ['record' => $contact->id])->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, 'name="record_id" value="'.$contact->id.'"'));
        $this->panel($this->admin(), 'contacts', ['record' => $foreignContact->id])->assertNotFound();
        $save(['record_id' => $contact->id, 'location_type' => 'office', 'name' => 'Site Person Renamed'])->assertOk();
        $this->assertSame('Site Person Renamed', $contact->fresh()->name);
        $this->assertNull($contact->fresh()->site_id, 'an office contact carries no site');
        $save(['record_id' => $foreignContact->id, 'location_type' => 'office', 'name' => 'Hijack'])->assertNotFound();
        $this->assertSame('Foreign Person', $foreignContact->fresh()->name);

        // Remove: only this customer's records.
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'contacts', $foreignContact->id, 'remove']))->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'contacts', $contact->id, 'remove']))->assertOk();
        $this->assertSame(0, $this->customer->contacts()->count());
        $this->assertTrue($foreignContact->fresh()->exists());

        // Notes: add, show author and date, remove; foreign note refused.
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'notes']), ['note' => 'Gate opens at 7'])->assertOk();
        $html = $this->panel($this->admin(), 'notes')->assertOk()->json('html');
        $this->assertStringContainsString('Gate opens at 7', $html);
        $this->assertStringContainsString($this->admin()->name, $html);
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'notes', $foreignNote->id, 'remove']))->assertNotFound();
        $note = $this->customer->notes()->firstOrFail();
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'notes', $note->id, 'remove']))->assertOk();
        $this->assertSame(0, $this->customer->notes()->count());

        // Permission rules: view-only cannot add; create-only cannot edit or remove.
        $viewer = $this->user(['Customers' => ['view']], null, 'viewer');
        $this->actingAs($viewer)->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'notes']), ['note' => 'x'])->assertForbidden();
        $creator = $this->user(['Customers' => ['view', 'edit', 'create']], null, 'creator');
        $this->actingAs($creator)->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'contacts']), ['location_type' => 'office', 'name' => 'By creator'])->assertOk();
        $created = $this->customer->contacts()->where('name', 'By creator')->firstOrFail();
        $this->actingAs($creator)->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'contacts', $created->id, 'remove']))->assertForbidden();
    }

    public function test_projects_and_invoice_panels_show_only_scoped_rows_and_totals_and_ageing_exclude_hidden_invoices(): void
    {
        $invoiceA = $this->approvedInvoice($this->mine, 1000, '40');    // 1,150 open, 40 days late
        $invoiceB = $this->approvedInvoice($this->theirs, 5000);         // 5,750 open, hidden from the scoped user
        $this->receive($invoiceA, 150, 'cw-rcpt-a')->assertSessionHasNoErrors();

        $scoped = $this->user(['Customers' => ['view', 'edit'], 'Projects' => ['view'], 'Accounts Receivable' => ['view']], $this->mine, 'scoped');

        $projects = $this->panel($scoped, 'projects')->assertOk()->json('html');
        $this->assertStringContainsString('PRJ-CW-A', $projects);
        $this->assertStringNotContainsString('PRJ-CW-B', $projects);

        $invoices = $this->panel($scoped, 'invoices')->assertOk()->json('html');
        $this->assertStringContainsString($invoiceA->invoice_number, $invoices);
        $this->assertStringNotContainsString($invoiceB->invoice_number, $invoices);

        // Header, receipts and ageing use the visible invoice only: 1,150 − 150 = 1,000 outstanding, in the 31–60 bucket.
        $edit = $this->actingAs($scoped)->get(route('admin.master.customers.edit', $this->customer))->assertOk();
        $edit->assertSee('SAR 1,000.00')->assertDontSee('SAR 6,750.00')->assertDontSee('5,750.00');
        $receipts = $this->panel($scoped, 'receipts')->assertOk()->json('html');
        $this->assertStringContainsString('SAR 150.00', $receipts);
        $this->assertStringContainsString('SAR 1,000.00', $receipts);
        $ageing = $this->panel($scoped, 'ageing')->assertOk()->json('html');
        $this->assertStringContainsString('Current-state ageing', $ageing);
        $this->assertStringContainsString($invoiceA->invoice_number, $ageing);
        $this->assertStringNotContainsString($invoiceB->invoice_number, $ageing);
        $this->assertMatchesRegularExpression('/31-60 days.*?SAR 1,000\.00|SAR 1,000\.00.*?31-60 days/s', $ageing);
        $this->assertStringNotContainsString('SAR 6,750.00', $ageing);

        // The company administrator sees both and the combined figure.
        $this->actingAs($this->admin())->get(route('admin.master.customers.edit', $this->customer))->assertOk()->assertSee('SAR 6,750.00');
        $this->assertStringContainsString($invoiceB->invoice_number, $this->panel($this->admin(), 'invoices')->json('html'));

        // Without Projects view the projects panel is refused and its tab absent.
        $noProjects = $this->user(['Customers' => ['view', 'edit']], null, 'noproj');
        $this->panel($noProjects, 'projects')->assertForbidden();
        $this->actingAs($noProjects)->get(route('admin.master.customers.edit', $this->customer))->assertOk()->assertDontSee('data-workspace-related="projects"', false);
    }

    public function test_accounting_zatca_and_activity_panels_require_their_own_permissions_and_use_truthful_wording(): void
    {
        $invoice = $this->approvedInvoice($this->mine, 1000);

        $accounting = $this->panel($this->admin(), 'accounting')->assertOk()->json('html');
        $this->assertStringContainsString($invoice->journalEntry->journal_number, $accounting);
        $this->assertStringContainsString('Linked receivable account', $accounting);

        $zatca = $this->panel($this->admin(), 'zatca')->assertOk()->json('html');
        $this->assertStringContainsString($invoice->zatcaRecord->uuid, $zatca);
        $this->assertStringContainsString('Local ZATCA Record', $zatca);
        $this->assertStringContainsString('not verified live', $zatca);
        $this->assertStringContainsString('not yet operational', $zatca);
        $this->assertStringNotContainsString('Cleared by ZATCA', $zatca);

        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $this->customer), $this->profile(['phone' => '+966 12']))->assertSessionHasNoErrors();
        $activity = $this->panel($this->admin(), 'activity')->assertOk()->json('html');
        $this->assertStringContainsString('Updated customer', $activity);
        $this->assertStringContainsString('Approved customer invoice', $activity);

        $noFinance = $this->user(['Customers' => ['view', 'edit'], 'Accounts Receivable' => ['view']], null, 'nofin');
        $this->panel($noFinance, 'accounting')->assertForbidden();
        $this->panel($noFinance, 'zatca')->assertForbidden();
        $this->panel($noFinance, 'activity')->assertForbidden();
        $this->actingAs($noFinance)->get(route('admin.master.customers.edit', $this->customer))->assertOk()
            ->assertDontSee('data-workspace-related="accounting"', false)->assertDontSee('data-workspace-related="zatca"', false)
            ->assertSee('data-workspace-related="invoices"', false);
    }

    public function test_direct_panel_routes_enforce_authorization_and_parent_verification(): void
    {
        $other = Customer::where('id', '!=', $this->customer->id)->orderBy('id')->firstOrFail();

        $this->panel($this->admin(), 'nope')->assertNotFound();
        $viewer = $this->user(['Customers' => ['view']], null, 'viewer');
        foreach (['projects', 'invoices', 'receipts', 'ageing', 'accounting', 'zatca', 'activity'] as $key) {
            $this->panel($viewer, $key)->assertForbidden();
        }

        // A forged customer_id in the body is refused in favour of the URL; documents are never written here.
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'notes']), ['note' => 'x', 'customer_id' => $other->id])->assertForbidden();
        $this->assertSame(0, $other->notes()->count());
        $this->assertSame(0, $this->customer->notes()->count());
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.save', [$this->customer, 'invoices']), ['invoice_number' => 'X'])->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'invoices', 1, 'approve']))->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.master.customers.workspace.action', [$this->customer, 'receipts', 1, 'remove']))->assertNotFound();
    }
}
