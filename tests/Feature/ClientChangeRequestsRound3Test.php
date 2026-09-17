<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * September media batch (NR-01 .. NR-34), stage A: access and quick fixes.
 */
class ClientChangeRequestsRound3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // NR-34 -----------------------------------------------------------------

    public function test_a_project_manager_sees_every_project_assigned_to_them(): void
    {
        $manager = $this->user('nabeel@example.com'); // Project Manager, project-level scope
        $this->assertSame('project', $manager->effectiveAccessScope());

        $assigned = Project::create(['name' => 'Jeddah Corniche Works', 'code' => 'PRJ-MGR-1', 'manager_id' => $manager->id, 'status' => 'active']);
        $other = Project::create(['name' => 'Unrelated Job', 'code' => 'PRJ-OTHER-1', 'status' => 'active']);

        $this->assertNotSame($assigned->id, $manager->project_id, 'the project is assigned on the project form only');

        $this->actingAs($manager)->get(route('admin.master.projects.index'))
            ->assertOk()
            ->assertSee('Jeddah Corniche Works')
            ->assertDontSee('Unrelated Job');

        $this->actingAs($manager)->get(route('admin.master.projects.show', $assigned))->assertOk();
        $this->actingAs($manager)->get(route('admin.master.projects.show', $other))->assertNotFound();
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertSee('Jeddah Corniche Works');

        // Writing into the managed project is allowed; into another project is not.
        $this->actingAs($manager)
            ->post(route('admin.master.sites.store'), [
                'name' => 'Corniche Site A', 'code' => 'CORN-A', 'project_id' => $assigned->id,
                'latitude' => 21.5, 'longitude' => 39.2, 'geofence_radius' => 300, 'status' => 'active',
            ])
            ->assertRedirect();
        $this->actingAs($manager)
            ->post(route('admin.master.sites.store'), [
                'name' => 'Nope', 'code' => 'NOPE-1', 'project_id' => $other->id,
                'latitude' => 21.5, 'longitude' => 39.2, 'geofence_radius' => 300, 'status' => 'active',
            ])
            ->assertForbidden();
    }

    // NR-32 -----------------------------------------------------------------

    public function test_activity_is_visible_only_down_the_reporting_hierarchy(): void
    {
        $admin = $this->user('admin@example.com');
        $projectManager = $this->user('nabeel@example.com');
        $accountsManager = $this->user('zubair@example.com');
        $mechanic = $this->user('kamran@example.com');

        ActivityLog::create(['user_id' => $admin->id, 'user_name' => $admin->name, 'module' => 'Users', 'action' => 'SUPER-ADMIN-ONLY-ACTION', 'status' => 'success']);
        ActivityLog::create(['user_id' => $mechanic->id, 'user_name' => $mechanic->name, 'module' => 'Attendance', 'action' => 'MECHANIC-ACTION', 'status' => 'success']);
        ActivityLog::create(['user_id' => $accountsManager->id, 'user_name' => $accountsManager->name, 'module' => 'Accounting', 'action' => 'ACCOUNTS-ACTION', 'status' => 'success']);

        // Super Admin sees everything.
        $this->actingAs($admin)->get(route('admin.activity-logs.index'))
            ->assertOk()->assertSee('SUPER-ADMIN-ONLY-ACTION')->assertSee('MECHANIC-ACTION')->assertSee('ACCOUNTS-ACTION');

        // Project Manager's dashboard shows the mechanic (Mechanic sits under Site In-Charge under
        // Project Manager) but never the admin's or the accounts team's activity.
        $this->actingAs($projectManager)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('MECHANIC-ACTION')->assertDontSee('SUPER-ADMIN-ONLY-ACTION')->assertDontSee('ACCOUNTS-ACTION');

        // Accounts Manager sees their own team's activity, not the site staff or the admin.
        $this->actingAs($accountsManager)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('ACCOUNTS-ACTION')->assertDontSee('MECHANIC-ACTION')->assertDontSee('SUPER-ADMIN-ONLY-ACTION');

        $this->assertNull($admin->visibleUserIds());
        $this->assertContains($mechanic->id, $projectManager->visibleUserIds());
        $this->assertNotContains($admin->id, $projectManager->visibleUserIds());
    }

    // NR-22 / NR-23 / NR-24 -------------------------------------------------

    public function test_accounting_dashboard_shows_separate_cash_and_bank_and_readable_labels(): void
    {
        $html = $this->actingAs($this->user('admin@example.com'))
            ->get(route('admin.accounting.dashboard'))
            ->assertOk()
            ->assertSee('Cash in Hand')
            ->assertSee('Bank Balance')
            ->assertSee('Payable Ageing')
            ->assertSee('Receivable Ageing')
            ->assertSee('ZATCA Failed Invoices')
            ->assertSee('clearance attempt was')
            ->getContent();

        $this->assertStringContainsString('Profit &amp; Expense Trend', $html, 'ampersand escaped exactly once');
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Aging', $html);
    }

    // NR-19 -----------------------------------------------------------------

    public function test_eosb_form_only_stars_fields_that_are_actually_required(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->user('admin@example.com'))->get(route('admin.hr.eosb.create'))
            ->assertOk()
            ->assertSee('Final Wage (SAR) *')
            ->assertDontSee('Leave Salary *')
            ->assertDontSee('Other Dues *');

        // Optional amounts may be omitted entirely...
        $this->actingAs($this->user('admin@example.com'))
            ->post(route('admin.hr.eosb.store'), [
                'employee_id' => $employee->id,
                'termination_date' => now()->toDateString(),
                'termination_reason' => 'termination',
                'service_years' => 3,
                'last_basic_salary' => 6000,
            ])
            ->assertRedirect();

        // ...but a zero final wage is refused instead of silently saved.
        $this->actingAs($this->user('admin@example.com'))
            ->post(route('admin.hr.eosb.store'), [
                'employee_id' => $employee->id,
                'termination_date' => now()->toDateString(),
                'termination_reason' => 'termination',
                'service_years' => 3,
                'last_basic_salary' => 0,
            ])
            ->assertSessionHasErrors('last_basic_salary');
    }

    // NR-10 -----------------------------------------------------------------

    public function test_transaction_dates_cannot_be_in_the_future_but_expiry_and_due_dates_can(): void
    {
        $admin = $this->user('admin@example.com');
        $employee = Employee::firstOrFail();
        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code, 'first_name' => $employee->first_name,
                'contract_type' => $employee->contract_type, 'employee_classification' => 'Sponsorship',
                'basic_salary' => $employee->basic_salary, 'payment_method' => 'Bank Transfer', 'status' => 'active',
                'joining_date' => $tomorrow,
            ])
            ->assertSessionHasErrors('joining_date');

        // Contract end in the future is fine.
        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code, 'first_name' => $employee->first_name,
                'contract_type' => $employee->contract_type, 'employee_classification' => 'Sponsorship',
                'basic_salary' => $employee->basic_salary, 'payment_method' => 'Bank Transfer', 'status' => 'active',
                'joining_date' => now()->subYear()->toDateString(),
                'contract_end_date' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $this->actingAs($admin)
            ->post(route('admin.hr.attendance.store'), [
                'employee_id' => $employee->id, 'shift_id' => Shift::firstOrFail()->id,
                'attendance_date' => $tomorrow, 'late_minutes' => 0, 'overtime_minutes' => 0,
                'status' => 'present', 'source' => 'manual', 'geofence_status' => 'inside',
            ])
            ->assertSessionHasErrors('attendance_date');

        $this->actingAs($admin)
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'FUTURE-1',
                'bill_date' => $tomorrow, 'vat_rate' => 15,
                'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10]],
            ])
            ->assertSessionHasErrors('bill_date');

        // A due date after today is exactly what payment terms need.
        $this->actingAs($admin)
            ->post(route('admin.accounting.accounts-payable.store'), [
                'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'FUTURE-DUE-1',
                'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(45)->toDateString(), 'vat_rate' => 15,
                'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10]],
            ])
            ->assertRedirect();
    }

    // Stage B: NR-01 / NR-02 / NR-04 / NR-05 -----------------------------------

    public function test_supplier_gets_city_rating_bank_details_payment_types_and_project_links(): void
    {
        $admin = $this->user('admin@example.com');
        $project = Project::firstOrFail();

        // A new supplier category is added inline and reused.
        $this->actingAs($admin)
            ->postJson(route('admin.master.lookup-values.store'), ['type' => 'supplier_category', 'value' => 'Scaffolding'])
            ->assertCreated()
            ->assertJsonPath('label', 'Scaffolding');
        $this->actingAs($admin)
            ->postJson(route('admin.master.lookup-values.store'), ['type' => 'supplier_category', 'value' => 'Scaffolding'])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->post(route('admin.master.suppliers.store'), [
                'name' => 'Riyadh Scaffolding Co', 'category' => 'Scaffolding', 'city' => 'Riyadh', 'rating' => 'Green',
                'bank_name' => 'Al Rajhi Bank', 'bank_account_name' => 'Riyadh Scaffolding Co', 'iban' => 'SA0380000000608010167519',
                'allowed_payment_types' => 'Bank', 'status' => 'active', 'project_ids' => [$project->id],
            ])
            ->assertRedirect(route('admin.master.suppliers.index'));

        $supplier = Supplier::where('name', 'Riyadh Scaffolding Co')->firstOrFail();
        $this->assertSame('Riyadh', $supplier->city);
        $this->assertSame('Green', $supplier->rating);
        $this->assertSame('Bank', $supplier->allowed_payment_types);
        $this->assertSame(['1120'], $supplier->allowedPaymentAccountCodes());
        $this->assertTrue($supplier->projects->contains($project));

        $this->actingAs($admin)->get(route('admin.master.suppliers.show', $supplier))
            ->assertOk()->assertSee('Riyadh')->assertSee('Al Rajhi Bank')->assertSee($project->name);
        $this->actingAs($admin)->get(route('admin.master.projects.show', $project))
            ->assertOk()->assertSee('Suppliers for this Project')->assertSee('Riyadh Scaffolding Co');
        $this->actingAs($admin)->get(route('admin.master.suppliers.create'))
            ->assertOk()->assertSee('Scaffolding')->assertSee('data-quick-create="qc-supplier-category"', false);
        $this->actingAs($admin)->get(route('admin.master.suppliers.index', ['rating' => 'Green']))
            ->assertOk()->assertSee('Riyadh Scaffolding Co');

        // A supplier with an invalid rating is refused.
        $this->actingAs($admin)
            ->post(route('admin.master.suppliers.store'), ['name' => 'Bad', 'rating' => 'Purple', 'status' => 'active'])
            ->assertSessionHasErrors('rating');
    }

    // NR-06 / NR-08 / NR-09 ---------------------------------------------------

    public function test_customer_shows_rating_overdue_days_contacts_and_shared_notes(): void
    {
        $admin = $this->user('admin@example.com');
        $customer = \App\Models\Customer::firstOrFail();
        $customer->update(['rating' => 'Amber']);

        \App\Models\CustomerInvoice::create([
            'customer_id' => $customer->id, 'invoice_number' => 'INV-OVERDUE-1',
            'invoice_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString(),
            'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150,
            'received_amount' => 0, 'balance_amount' => 1150, 'payment_status' => 'unpaid', 'zatca_status' => 'pending',
        ]);

        $summary = $customer->overdueSummary();
        $this->assertSame(10, $summary['days']);
        $this->assertSame(1150.0, $summary['amount']);

        $this->actingAs($admin)->get(route('admin.master.customers.show', $customer))
            ->assertOk()->assertSee('Amber')->assertSee('10 days')->assertSee('Payment overdue');
        $this->actingAs($admin)->get(route('admin.master.customers.index'))
            ->assertOk()->assertSee('10 days');

        $this->actingAs($admin)
            ->post(route('admin.master.customers.contacts.store', $customer), [
                'location_type' => 'site', 'name' => 'Eng. Saleh', 'title' => 'Site engineer', 'phone' => '+966 55 000 1111',
            ])
            ->assertRedirect(route('admin.master.customers.show', $customer));
        $this->actingAs($admin)
            ->post(route('admin.master.customers.notes.store', $customer), ['note' => 'Gate pass needed; ask for Saleh at gate 2.'])
            ->assertRedirect(route('admin.master.customers.show', $customer));

        $this->actingAs($admin)->get(route('admin.master.customers.show', $customer))
            ->assertOk()->assertSee('Eng. Saleh')->assertSee('Site engineer')->assertSee('Gate pass needed');

        $contact = $customer->contacts()->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.master.customers.contacts.destroy', [$customer, $contact]))->assertRedirect();
        $this->assertSame(0, $customer->contacts()->count());
    }

    // NR-03 -----------------------------------------------------------------

    public function test_employee_codes_are_numbered_per_classification_when_left_blank(): void
    {
        $admin = $this->user('admin@example.com');

        $payload = fn (string $name, string $classification) => [
            'first_name' => $name, 'contract_type' => 'Full Time', 'employee_classification' => $classification,
            'basic_salary' => 3000, 'payment_method' => 'Cash', 'status' => 'active',
        ];

        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $payload('Freelance One', 'Freelancer'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $payload('Freelance Two', 'Freelancer'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $payload('Sponsored One', 'Sponsorship'))->assertRedirect();

        $this->assertSame('FL-001', Employee::where('first_name', 'Freelance One')->value('employee_code'));
        $this->assertSame('FL-002', Employee::where('first_name', 'Freelance Two')->value('employee_code'));
        $this->assertSame('SP-001', Employee::where('first_name', 'Sponsored One')->value('employee_code'));

        // A typed code still wins.
        $this->actingAs($admin)->post(route('admin.hr.employees.store'), $payload('Manual Code', 'Sponsorship') + ['employee_code' => 'X-777'])->assertRedirect();
        $this->assertSame('X-777', Employee::where('first_name', 'Manual Code')->value('employee_code'));

        $this->actingAs($admin)->get(route('admin.hr.employees.create'))
            ->assertOk()->assertSee('Employee Code (auto)')->assertSee('data-quick-create="qc-nationality"', false);

        // Nationality list is extensible, but only by HR users.
        $this->actingAs($admin)->postJson(route('admin.master.lookup-values.store'), ['type' => 'nationality', 'value' => 'Jordanian'])->assertCreated();
        $this->actingAs($this->user('shaban@example.com'))->postJson(route('admin.master.lookup-values.store'), ['type' => 'nationality', 'value' => 'Nope'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.hr.employees.create'))->assertOk()->assertSee('Jordanian');
    }

    // NR-25 / NR-07 / NR-15 -----------------------------------------------------

    public function test_classifications_edit_inline_locations_are_renamed_and_project_page_lists_staff(): void
    {
        $admin = $this->user('admin@example.com');
        $project = Project::firstOrFail();

        $classification = \App\Models\ProjectClassification::create(['name' => 'Infrastucture', 'status' => 'active']);
        $this->actingAs($admin)
            ->putJson(route('admin.master.project-classifications.update', $classification), ['name' => 'Infrastructure'])
            ->assertOk()
            ->assertJsonPath('label', 'Infrastructure');
        $this->assertSame('active', $classification->fresh()->status);

        $this->actingAs($admin)->get(route('admin.master.projects.create'))
            ->assertOk()->assertSee('data-quick-edit="qc-classification"', false);

        $this->actingAs($admin)->get(route('admin.master.sites.index'))->assertOk()->assertSee('+ Add Location')->assertDontSee('Sites Listing');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('<span>Locations</span>', false);
        $this->actingAs($admin)->get(route('admin.master.sites.create', ['project' => $project->id]))
            ->assertOk()->assertSee('Add Location')->assertSee('value="'.$project->id.'" selected', false);

        $employee = Employee::firstOrFail();
        $employee->update(['project_id' => $project->id]);
        $this->actingAs($admin)->get(route('admin.master.projects.show', $project))
            ->assertOk()->assertSee('Assigned Staff')->assertSee($employee->name)->assertSee('+ Add Location')->assertSee('Locations in this Project');
    }
}
