<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CustomerOverdueNotification;
use App\Services\Notifications\CustomerOverdueReminders;
use App\Services\Notifications\NotificationAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOverdueRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private Role $role;

    private CustomerInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        config(['seera.overdue_reminders.enabled' => true]);
        $this->role = Role::create(['name' => 'Accounts Manager', 'code' => 'FINANCE_MANAGER', 'status' => 'active', 'access_scope' => 'Company Level']);
        $this->role->permissions()->attach(Permission::firstOrCreate(['module' => 'Accounts Receivable', 'action' => 'view']));
        $this->finance = User::create(['name' => 'Synthetic Finance', 'email' => 'overdue-finance@example.test', 'password' => 'test-password', 'status' => 'active']);
        $this->finance->roles()->attach($this->role, ['is_primary' => true]);
        $project = Project::create(['name' => 'Reminder project', 'code' => 'REM-A', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Synthetic customer', 'code' => 'REM-C', 'type' => 'Company', 'status' => 'active']);
        $journal = JournalEntry::create(['journal_number' => 'REM-J', 'journal_date' => '2026-11-01', 'status' => 'posted']);
        $this->invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'REM-INV-A', 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-10', 'project_id' => $project->id, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'received_amount' => 0, 'balance_amount' => 115, 'payment_status' => 'unpaid', 'journal_entry_id' => $journal->id]);
    }

    public function test_one_day_overdue_in_app_alert_is_idempotent_for_each_day(): void
    {
        $publisher = app(CustomerOverdueReminders::class);
        $this->assertSame(1, $publisher->publish());
        $this->assertSame(0, $publisher->publish());
        $notice = $this->finance->notifications()->sole();
        $this->assertSame(CustomerOverdueNotification::class, $notice->type);
        $this->assertSame('REM-INV-A', $notice->data['reference']);
        $this->assertSame(['database'], (new CustomerOverdueNotification)->via($this->finance));
        $this->travel(1)->days();
        $this->assertSame(1, $publisher->publish());
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_due_today_paid_draft_cancelled_or_unposted_invoices_do_not_send(): void
    {
        foreach ([['due_date' => '2026-11-11'], ['due_date' => null], ['payment_status' => 'paid'], ['payment_status' => 'draft'], ['payment_status' => 'cancelled'], ['balance_amount' => 0], ['journal_entry_id' => null]] as $change) {
            $this->invoice->update(['due_date' => '2026-11-10', 'payment_status' => 'unpaid', 'balance_amount' => 115, 'journal_entry_id' => JournalEntry::first()->id]);
            $this->invoice->update($change);
            $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_nonfinance_and_revoked_finance_users_are_not_recipients(): void
    {
        $this->role->update(['code' => 'PROJECT_MANAGER']);
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        $this->role->update(['code' => 'FINANCE_MANAGER']);
        $this->role->permissions()->detach();
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_expired_finance_role_and_personal_deny_do_not_receive(): void
    {
        $this->finance->roles()->updateExistingPivot($this->role->id, ['is_temporary' => true, 'access_end_date' => '2026-11-10']);
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        $this->finance->roles()->updateExistingPivot($this->role->id, ['is_temporary' => false]);
        DB::table('user_permission_overrides')->insert(['user_id' => $this->finance->id, 'permission_id' => $this->role->permissions->first()->id, 'effect' => 'deny', 'reason' => 'Private restriction']);
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
    }

    public function test_project_restrictions_apply_to_delivery_and_old_notice_access(): void
    {
        $this->role->update(['access_scope' => 'Project Level']);
        $foreign = Project::create(['name' => 'Other project', 'code' => 'REM-B', 'status' => 'active']);
        $this->finance->update(['project_id' => $foreign->id]);
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        $this->finance->update(['project_id' => $this->invoice->project_id]);
        $this->assertSame(1, app(CustomerOverdueReminders::class)->publish());
        $notice = $this->finance->notifications()->sole();
        $this->finance->update(['project_id' => $foreign->id]);
        $this->assertFalse(app(NotificationAccess::class)->visible($notice, $this->finance->fresh()));
        $this->actingAs($this->finance->fresh())->post(route('admin.notifications.read', $notice->id))->assertNotFound();
    }

    public function test_receipt_or_credit_settlement_hides_existing_alert_without_rewriting_it(): void
    {
        app(CustomerOverdueReminders::class)->publish();
        $notice = $this->finance->notifications()->sole();
        $this->invoice->update(['payment_status' => 'paid', 'balance_amount' => 0]);
        $this->assertSame(0, app(CustomerOverdueReminders::class)->publish());
        $this->assertFalse(app(NotificationAccess::class)->visible($notice, $this->finance->fresh()));
        $this->assertSame(0, app(NotificationAccess::class)->unreadCount($this->finance->fresh()));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_owner_and_current_permission_are_enforced(): void
    {
        app(CustomerOverdueReminders::class)->publish();
        $notice = $this->finance->notifications()->sole();
        $other = User::create(['name' => 'Other Finance', 'email' => 'other-finance@example.test', 'password' => 'test-password', 'status' => 'active']);
        $other->roles()->attach($this->role, ['is_primary' => true]);
        $this->actingAs($other)->post(route('admin.notifications.read', $notice->id))->assertNotFound();
        $this->actingAs($this->finance)->get(route('admin.notifications.index'))->assertOk()->assertSee('REM-INV-A')->assertSee(route('admin.accounting.accounts-receivable.show', $this->invoice));
        $this->role->permissions()->detach();
        $this->actingAs($this->finance->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('REM-INV-A');
        $this->post(route('admin.notifications.read', $notice->id))->assertNotFound();
    }

    public function test_dry_run_does_not_send_and_delivery_requires_explicit_activation(): void
    {
        $this->artisan('seera:overdue-reminders')->expectsOutputToContain('Dry run: 1')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);
        config(['seera.overdue_reminders.enabled' => false]);
        $this->artisan('seera:overdue-reminders', ['--send' => true])->assertFailed();
        $this->assertDatabaseCount('notifications', 0);
        config(['seera.overdue_reminders.enabled' => true]);
        $this->artisan('seera:overdue-reminders', ['--send' => true])->expectsOutputToContain('created: 1')->assertSuccessful();
    }

    public function test_reminders_do_not_change_financial_or_vat_records(): void
    {
        $before = $this->invoice->fresh()->getAttributes();
        $journals = DB::table('journal_entries')->count();
        $vat = DB::table('vat_transactions')->count();
        app(CustomerOverdueReminders::class)->publish();
        $this->assertSame($before, $this->invoice->fresh()->getAttributes());
        $this->assertSame($journals, DB::table('journal_entries')->count());
        $this->assertSame($vat, DB::table('vat_transactions')->count());
    }

    public function test_many_notices_use_batched_scope_queries_instead_of_one_query_per_notice(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->finance->notifications()->create(['id' => (string) Str::uuid(), 'type' => CustomerOverdueNotification::class, 'data' => ['event' => 'overdue', 'module' => 'Accounts Receivable', 'source_type' => 'customer_invoice', 'source_id' => $this->invoice->id, 'reference' => 'REM-INV-A']]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(40, app(NotificationAccess::class)->unreadCount($this->finance->fresh()));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(12, $queries, 'Scope checks must be batched per source type.');
    }
}
