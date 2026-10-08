<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wave 2 Batch C: the Customer Invoice View is a read-only finance document
 * workspace (identity header, lines, customer and project context, VAT,
 * journal, receipts, balance, local e-invoice record, activity), every section
 * behind its own permission and access scope; approval, receipts and reopening
 * stay explicit actions with their existing safeguards, and each flow returns
 * to the context it was started from.
 */
class CustomerInvoiceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Project $mine;

    protected Project $theirs;

    private int $userSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->customer = Customer::orderBy('id')->firstOrFail();
        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-CI-A', 'customer_id' => $this->customer->id, 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-CI-B', 'customer_id' => $this->customer->id, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->userSeq;
        $role = Role::create(['name' => 'CI role '.$suffix, 'code' => 'CI_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'CI '.$suffix, 'email' => 'ci-'.$suffix.'@example.test', 'username' => 'ci.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    /** A finance reader who may read every section but change nothing. */
    protected function reader(?Project $project = null): User
    {
        return $this->user([
            'Accounts Receivable' => ['view'], 'Customers' => ['view'], 'Projects' => ['view'],
            'Journal Entries' => ['view'], 'ZATCA Invoicing' => ['view'], 'Activity Logs' => ['view'],
        ], $project, 'reader');
    }

    protected function draftInvoice(Project $project, float $net = 1000, float $vatRate = 15, array $extra = []): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), $extra + [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'project_id' => $project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => $vatRate,
            'lines' => [['description' => 'Progress claim 3', 'quantity' => 2, 'unit_price' => $net / 2]],
        ])->assertSessionHasNoErrors();

        return CustomerInvoice::latest('id')->firstOrFail();
    }

    protected function approvedInvoice(Project $project, float $net = 1000, ?int $dueDaysAgo = null): CustomerInvoice
    {
        $invoice = $this->draftInvoice($project, $net);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        // The approval flash (it names the local record UUID) must not leak into the next page a different user opens.
        $this->flushSession();
        if ($dueDaysAgo !== null) {
            $invoice->update(['due_date' => now()->subDays($dueDaysAgo)->toDateString()]);
        }

        return $invoice->fresh();
    }

    protected function receiptPayload(float $amount, string $key, array $extra = []): array
    {
        return $extra + [
            'receipt_date' => now()->toDateString(), 'amount' => $amount, 'payment_method' => 'Bank Transfer',
            'receipt_account_id' => ChartOfAccount::where('account_code', PostingService::BANK)->value('id'), 'idempotency_key' => $key,
        ];
    }

    protected function receive(CustomerInvoice $invoice, float $amount, string $key, array $extra = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), $this->receiptPayload($amount, $key, $extra));
    }

    protected function open(User $user, CustomerInvoice $invoice, array $query = []): TestResponse
    {
        return $this->actingAs($user)->get(route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice] + $query));
    }

    // ------------------------------------------------------------------ tests

    public function test_view_is_read_only_shows_every_section_and_a_get_writes_nothing(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $this->receive($invoice, 150, 'ci-view-1')->assertSessionHasNoErrors();

        $reader = $this->reader();
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $page = $this->open($reader, $invoice)->assertOk();
        $this->assertSame([], $writes, 'a GET of the invoice workspace must not write to the database');

        $page->assertSee('Read-only invoice view');
        foreach (['information', 'lines', 'customer', 'project', 'vat', 'accounting', 'receipts', 'balance', 'zatca', 'activity'] as $anchor) {
            $page->assertSee('id="'.$anchor.'"', false);
        }
        foreach (['Invoice Information', 'Invoice Lines', 'Project / Cost Center', 'Balance / Ageing', 'Local e-Invoice Record'] as $label) {
            $page->assertSee($label);
        }
        // No editor, no save buttons and no form that writes this invoice or a receipt.
        $page->assertDontSee('name="'.SaveAction::FIELD.'"', false)
            ->assertDontSee('action="'.route('admin.accounting.accounts-receivable.update', $invoice).'"', false)
            ->assertDontSee('action="'.route('admin.accounting.accounts-receivable.receipt.store', $invoice).'"', false)
            ->assertDontSee('action="'.route('admin.accounting.accounts-receivable.approve', $invoice).'"', false)
            ->assertDontSee('Edit Draft')->assertDontSee('Record Receipt')->assertDontSee('Reopen for Correction');
    }

    public function test_header_carries_identity_amounts_state_and_context_links(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $page = $this->open($this->reader(), $invoice)->assertOk();
        $page->assertSee($invoice->invoice_number)->assertSee($this->customer->name)->assertSee('Awaiting receipt')
            ->assertSee('SAR 1,150.00')->assertSee('SAR 0.00')->assertSee('Riyadh Commercial Tower')
            ->assertSee(route('admin.master.customers.show', $this->customer))->assertSee(route('admin.master.projects.show', $this->mine))
            ->assertSee($invoice->journalEntry->journal_number)->assertSee('Local record pending; not sent to ZATCA, not verified live');

        $this->receive($invoice, 150, 'ci-head-1')->assertSessionHasNoErrors();
        $this->open($this->reader(), $invoice)->assertOk()->assertSee('Partly received')->assertSee('SAR 150.00')->assertSee('SAR 1,000.00');

        $overdue = $this->approvedInvoice($this->mine, 1000, 40);
        $this->open($this->reader(), $overdue)->assertOk()->assertSee('Overdue by 40 days')->assertSee('31-60 days')->assertSee('<span class="badge red">40</span>', false);
    }

    public function test_customer_project_accounting_zatca_and_activity_sections_follow_their_own_permissions(): void
    {
        $invoice = $this->approvedInvoice($this->mine);

        $arOnly = $this->user(['Accounts Receivable' => ['view']], suffix: 'aronly');
        $page = $this->open($arOnly, $invoice)->assertOk();
        foreach (['customer', 'project', 'accounting', 'zatca', 'activity'] as $anchor) {
            $page->assertDontSee('id="'.$anchor.'"', false);
        }
        // Context stays visible as plain text; links and money-of-other-modules do not.
        $page->assertSee($this->customer->name)->assertSee('Riyadh Commercial Tower')
            ->assertDontSee(route('admin.master.customers.show', $this->customer))
            ->assertDontSee(route('admin.master.projects.show', $this->mine))
            ->assertDontSee(route('admin.accounting.journal-entries.show', $invoice->journalEntry))
            ->assertDontSee($invoice->zatcaRecord->uuid)
            ->assertSee('Local record pending');

        $manager = $this->user(['Accounts Receivable' => ['view'], 'Customers' => ['view', 'edit']], suffix: 'mgr');
        $this->open($manager, $invoice)->assertOk()->assertSee('View Customer')->assertSee('Manage Customer')->assertSee(route('admin.master.customers.edit', $this->customer));

        $this->open($this->reader(), $invoice)->assertOk()->assertSee('View Customer')->assertDontSee('Manage Customer')->assertSee('View Project');
    }

    public function test_invoice_lines_and_vat_summary_use_the_stored_calculations(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $page = $this->open($this->reader(), $invoice)->assertOk();
        $page->assertSeeInOrder(['Progress claim 3', '2.00', '500.00', '1,000.00', '15.00%', '150.00', '1,150.00'])
            ->assertSee('Standard rated output VAT (account 2210)')->assertSee('Recorded when the invoice was approved');

        $zero = $this->draftInvoice($this->mine, 800, 0);
        $this->open($this->reader(), $zero)->assertOk()->assertSee('Zero VAT amount: no VAT ledger row is created')->assertSee('Not yet; recorded when the invoice is approved');
    }

    public function test_accounting_section_shows_the_posted_journal_only_with_journal_permission(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $journal = $invoice->journalEntry;

        $page = $this->open($this->reader(), $invoice)->assertOk();
        $page->assertSee('View Journal')->assertSee($journal->journal_number)->assertSee(route('admin.accounting.journal-entries.show', $journal))
            ->assertSee('1200')->assertSee('2210')->assertSee(number_format($journal->total_debit, 2));

        $noJournal = $this->user(['Accounts Receivable' => ['view']], suffix: 'nojournal');
        $this->open($noJournal, $invoice)->assertOk()->assertDontSee('View Journal')->assertDontSee($journal->journal_number)->assertSee('Posted');

        $draft = $this->draftInvoice($this->mine);
        $this->open($this->reader(), $draft)->assertOk()->assertSee('No accounting entry yet')->assertSee('Not posted yet')->assertSee('Draft, not posted');
    }

    public function test_receipts_list_only_this_invoice_and_paginate(): void
    {
        $a = $this->approvedInvoice($this->mine, 1000);
        $b = $this->approvedInvoice($this->mine, 1000);
        $this->receive($b, 200, 'ci-b-1', ['reference_number' => 'REF-ON-B'])->assertSessionHasNoErrors();
        for ($i = 1; $i <= 11; $i++) {
            $this->receive($a, 10, 'ci-a-'.$i, ['reference_number' => 'REF-ON-A-'.$i])->assertSessionHasNoErrors();
        }

        $page = $this->open($this->reader(), $a)->assertOk();
        $page->assertSee('REF-ON-A-11')->assertDontSee('REF-ON-B')->assertSee('Showing the latest 10 of 11')->assertSee('page_receipts=2')->assertSee('(11 receipts)');
        $this->open($this->reader(), $a, ['page_receipts' => 2])->assertOk()->assertSee('Showing the latest 1 of 11');
        $this->assertSame(110.0, (float) $a->fresh()->received_amount);
        $this->assertSame(1040.0, (float) $a->fresh()->balance_amount);

        // A receipt row forged onto another invoice never shows here, whatever its customer.
        $foreign = CustomerReceipt::create(['customer_id' => $this->customer->id, 'customer_invoice_id' => $b->id, 'receipt_date' => now()->toDateString(), 'amount' => 5, 'reference_number' => 'FORGED-REF', 'payment_method' => 'Cash']);
        $this->open($this->reader(), $a)->assertOk()->assertDontSee('FORGED-REF');
        $this->assertSame(110.0, (float) $a->fresh()->received_amount);
    }

    public function test_record_receipt_needs_process_permission_and_an_open_invoice(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $origin = '/admin/master/customers/'.$this->customer->id.'#invoices';

        $this->open($this->reader(), $invoice)->assertOk()->assertDontSee('Record Receipt');
        $this->actingAs($this->reader())->get(route('admin.accounting.accounts-receivable.receipt', $invoice))->assertForbidden();

        $cashier = $this->user(['Accounts Receivable' => ['view', 'process']], suffix: 'cashier');
        $page = $this->open($cashier, $invoice, ['return_to' => $origin])->assertOk()->assertSee('Record Receipt');
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $origin]), html_entity_decode($page->getContent()));

        $draft = $this->draftInvoice($this->mine);
        $this->open($cashier, $draft)->assertOk()->assertDontSee('Record Receipt');
        $this->receive($draft, 100, 'ci-draft-rcpt', [], $cashier)->assertSessionHasErrors('receipt');
        $this->assertSame(0, $draft->receipts()->count());

        $this->receive($invoice, 1150, 'ci-full', [], $cashier)->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->open($cashier, $invoice)->assertOk()->assertSee('Received in full')->assertDontSee('Record Receipt');
    }

    public function test_receipt_replay_overpayment_and_wrong_invoice_keep_the_existing_safeguards(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $other = $this->approvedInvoice($this->mine);
        $journals = \App\Models\JournalEntry::count();

        $this->receive($invoice, 500, 'ci-replay')->assertSessionHasNoErrors();
        $this->receive($invoice, 500, 'ci-replay')->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->receipts()->count());
        $this->assertSame($journals + 1, \App\Models\JournalEntry::count());
        $this->assertSame(500.0, (float) $invoice->fresh()->received_amount);
        $this->assertSame(650.0, (float) $invoice->fresh()->balance_amount);

        // Same key against a different invoice, or changed details, is refused and adds nothing.
        $this->receive($other, 500, 'ci-replay')->assertSessionHasErrors();
        $this->receive($invoice, 501, 'ci-replay')->assertSessionHasErrors();
        $this->assertSame(0, $other->receipts()->count());
        $this->assertSame(1, $invoice->receipts()->count());

        // More than the outstanding balance is refused.
        $this->receive($invoice, 650.01, 'ci-over')->assertSessionHasErrors('amount');
        $this->assertSame(650.0, (float) $invoice->fresh()->balance_amount);

        $this->open($this->reader(), $invoice)->assertOk()->assertSee('SAR 500.00')->assertSee('SAR 650.00')->assertSee('(1 receipt)');
    }

    public function test_activity_section_follows_permission_and_visibility(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $this->receive($invoice, 100, 'ci-act')->assertSessionHasNoErrors();

        $this->open($this->admin(), $invoice)->assertOk()->assertSee('Created customer invoice')->assertSee('Approved customer invoice')->assertSee('Recorded customer receipt');
        // A lower role reads the section but never the Super Admin's entries (NR-32).
        $this->open($this->reader(), $invoice)->assertOk()->assertSee('id="activity"', false)->assertDontSee('Approved customer invoice');
        $this->open($this->user(['Accounts Receivable' => ['view']], suffix: 'noact'), $invoice)->assertOk()->assertDontSee('id="activity"', false);
    }

    public function test_access_scope_denies_invoices_outside_the_users_project_and_limits_customer_totals(): void
    {
        $mine = $this->approvedInvoice($this->mine, 1000);
        $theirs = $this->approvedInvoice($this->theirs, 5000);

        $scoped = $this->reader($this->mine);
        $this->open($scoped, $theirs)->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.accounting.accounts-receivable.receipt', $theirs))->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.accounting.accounts-receivable.edit', $theirs))->assertNotFound();
        $this->actingAs($scoped)->post(route('admin.accounting.accounts-receivable.receipt.store', $theirs), $this->receiptPayload(10, 'ci-scope'))->assertNotFound();

        // Customer outstanding on the visible invoice counts only the invoices this user may see.
        $this->open($scoped, $mine)->assertOk()->assertSee('SAR 1,150.00')->assertSee('(1 open invoice(s))')->assertDontSee('SAR 6,900.00');
        $this->open($this->admin(), $mine)->assertOk()->assertSee('SAR 6,900.00')->assertSee('(2 open invoice(s))');
    }

    public function test_return_to_is_honoured_only_for_safe_admin_paths(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $origin = '/admin/master/projects/'.$this->mine->id.'#invoices';

        $this->open($this->admin(), $invoice, ['return_to' => $origin])->assertOk()->assertSee('Back to origin')->assertSee('href="'.$origin.'"', false);
        $this->open($this->admin(), $invoice, ['return_to' => 'https://evil.example/phish'])->assertOk()->assertDontSee('evil.example')->assertSee('Back to Accounts Receivable');

        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $origin]))
            ->assertOk()->assertSee('name="_return_to" value="'.$origin.'"', false)->assertSee('href="'.$origin.'"', false)->assertSee('>Back<', false);
        $this->receive($invoice, 100, 'ci-ret-1', ['_return_to' => $origin])->assertSessionHasNoErrors()->assertRedirect($origin);
        // A replay returns to the same origin without adding anything; an off-site origin falls back to the invoice.
        $this->receive($invoice, 100, 'ci-ret-1', ['_return_to' => $origin])->assertRedirect($origin);
        $this->receive($invoice, 100, 'ci-ret-2', ['_return_to' => 'https://evil.example/'])->assertRedirect(route('admin.accounting.accounts-receivable.show', $invoice));
        $this->assertSame(2, $invoice->receipts()->count());
    }

    public function test_draft_edit_is_permitted_posted_edit_is_blocked_and_save_flows_keep_context(): void
    {
        $draft = $this->draftInvoice($this->mine);
        $origin = '/admin/master/customers/'.$this->customer->id.'/edit#invoices';

        $this->open($this->reader(), $draft)->assertOk()->assertDontSee('Edit Draft')->assertDontSee('Approve &amp; Post', false);
        $editor = $this->user(['Accounts Receivable' => ['view', 'edit']], suffix: 'editor');
        $page = $this->open($editor, $draft, ['return_to' => $origin])->assertOk()->assertSee('Edit Draft');
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.edit', ['accounts_receivable' => $draft, 'return_to' => $origin]), html_entity_decode($page->getContent()));
        $this->actingAs($editor)->get(route('admin.accounting.accounts-receivable.edit', ['accounts_receivable' => $draft, 'return_to' => $origin]))
            ->assertOk()->assertSee('name="_return_to" value="'.$origin.'"', false)->assertSee('Save &amp; close', false)->assertSee('View Details');

        $payload = ['customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'project_id' => $this->mine->id, 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim 3 (revised)', 'quantity' => 1, 'unit_price' => 1200]]];
        $this->actingAs($editor)->put(route('admin.accounting.accounts-receivable.update', $draft), $payload + ['_save_action' => 'stay', '_return_to' => $origin])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.accounting.accounts-receivable.show', [$draft, 'return_to' => $origin]));
        $this->actingAs($editor)->put(route('admin.accounting.accounts-receivable.update', $draft), $payload + ['_save_action' => 'close', '_return_to' => $origin])
            ->assertSessionHasNoErrors()->assertRedirect($origin);
        $this->assertSame(1380.0, (float) $draft->fresh()->total_amount);

        $approver = $this->user(['Accounts Receivable' => ['view', 'approve']], suffix: 'approver');
        $this->open($approver, $draft)->assertOk()->assertSee('Approve &amp; Post', false)->assertDontSee('Edit Draft');
        $this->actingAs($approver)->post(route('admin.accounting.accounts-receivable.approve', $draft))->assertSessionHasNoErrors();

        $this->open($editor, $draft)->assertOk()->assertDontSee('Edit Draft')->assertDontSee('Approve &amp; Post', false);
        $this->actingAs($editor)->get(route('admin.accounting.accounts-receivable.edit', $draft))->assertForbidden();
        $this->actingAs($editor)->put(route('admin.accounting.accounts-receivable.update', $draft), $payload)->assertSessionHasErrors('invoice');
        $this->assertSame(1380.0, (float) $draft->fresh()->total_amount);
    }

    public function test_reopen_appears_only_for_a_super_admin_on_an_unpaid_untouched_invoice(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $this->open($this->admin(), $invoice)->assertOk()->assertSee('Reopen for Correction');
        $this->open($this->user(['Accounts Receivable' => ['view', 'approve']], suffix: 'notsuper'), $invoice)->assertOk()->assertDontSee('Reopen for Correction');
        $this->receive($invoice, 100, 'ci-reopen')->assertSessionHasNoErrors();
        $this->open($this->admin(), $invoice)->assertOk()->assertDontSee('Reopen for Correction');
    }

    public function test_customer_and_project_workspaces_open_the_invoice_with_their_context(): void
    {
        $invoice = $this->approvedInvoice($this->mine);

        $editOrigin = route('admin.master.customers.edit', $this->customer, false).'#invoices';
        $html = $this->actingAs($this->admin())->getJson(route('admin.master.customers.workspace.panel', [$this->customer, 'invoices']))->assertOk()->json('html');
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice, 'return_to' => $editOrigin]), html_entity_decode($html));
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $editOrigin]), html_entity_decode($html));

        $showOrigin = route('admin.master.customers.show', $this->customer, false).'#invoices';
        $page = $this->actingAs($this->admin())->get(route('admin.master.customers.show', $this->customer))->assertOk();
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice, 'return_to' => $showOrigin]), html_entity_decode($page->getContent()));
        $this->open($this->admin(), $invoice, ['return_to' => $showOrigin])->assertOk()->assertSee('href="'.$showOrigin.'"', false)->assertSee('Back to origin');

        $projectOrigin = route('admin.master.projects.show', $this->mine, false).'#invoices';
        $html = $this->actingAs($this->admin())->getJson(route('admin.master.projects.workspace.panel', [$this->mine, 'invoices']))->assertOk()->json('html');
        $this->assertStringContainsString(route('admin.accounting.accounts-receivable.show', [$invoice->id, 'return_to' => $projectOrigin]), html_entity_decode($html));
        $this->open($this->admin(), $invoice, ['return_to' => $projectOrigin])->assertOk()->assertSee('Back to origin')->assertSee('View Project');
    }

    public function test_local_e_invoice_wording_never_claims_live_clearance(): void
    {
        $invoice = $this->approvedInvoice($this->mine);
        $record = $invoice->zatcaRecord;

        $page = $this->open($this->reader(), $invoice)->assertOk();
        $page->assertSee($record->uuid)->assertSee('Local e-Invoice Record')->assertSee('local record, not verified live')->assertSee('NOT YET OPERATIONAL')
            ->assertSee('Local payload stored (not a ZATCA-issued QR)')->assertSee('file not generated')->assertSee('no certificate or real signing exists')
            ->assertSee('Open Local Record')->assertSee(route('admin.accounting.zatca.show', $record))
            ->assertDontSee('Cleared by ZATCA')->assertDontSee('ZATCA cleared');

        $draft = $this->draftInvoice($this->mine);
        $this->open($this->reader(), $draft)->assertOk()->assertSee('No local record yet')->assertSee('No local e-invoice record yet');
    }
}
