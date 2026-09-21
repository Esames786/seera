<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\MarketingLead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupplierBill;
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

        // The next code is now shown on the form itself; see the FR-06 test for the detail.
        $this->actingAs($admin)->get(route('admin.hr.employees.create'))
            ->assertOk()->assertSee('Assigned when you save')->assertSee('data-quick-create="qc-nationality"', false);

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

    // Stage C: NR-11 / NR-12 / NR-13 / NR-14 -----------------------------------

    public function test_documents_are_one_source_with_subtypes_renewals_and_list_filters(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->user('admin@example.com');
        $employee = Employee::firstOrFail();

        // The duplicate numbers/expiry section is gone from the form.
        $this->actingAs($admin)->get(route('admin.hr.employees.edit', $employee))
            ->assertOk()
            ->assertDontSee('id="iqama_number"', false)
            ->assertSee('Profession / Class')
            ->assertSee('Already attached');

        $base = [
            'employee_code' => $employee->employee_code, 'first_name' => $employee->first_name,
            'contract_type' => $employee->contract_type, 'employee_classification' => 'Sponsorship',
            'basic_salary' => $employee->basic_salary, 'payment_method' => 'Bank Transfer', 'status' => 'active',
        ];
        $expiry = now()->addDays(300)->toDateString();

        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), $base + [
                'documents' => [[
                    'document_type' => 'IQAMA', 'document_subtype' => 'Electrician', 'document_number' => '2455001122',
                    'issue_date' => now()->subYear()->toDateString(), 'expiry_date' => $expiry,
                    'file' => \Illuminate\Http\UploadedFile::fake()->create('iqama.pdf', 40, 'application/pdf'),
                ]],
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $document = $employee->documents()->where('document_number', '2455001122')->firstOrFail();
        $this->assertSame('Electrician', $document->document_subtype);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($document->file_path);

        // Employee summary columns follow the document, so dashboard, list and register agree.
        $employee->refresh();
        $this->assertSame('2455001122', $employee->iqama_number);
        $this->assertSame($expiry, $employee->iqama_expiry_date->toDateString());

        // Renewal: new expiry and a replacement file on the existing row.
        $oldPath = $document->file_path;
        $renewed = now()->addDays(700)->toDateString();
        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), $base + [
                'existing_documents' => [$document->id => [
                    'document_subtype' => 'Senior Electrician', 'document_number' => '2455001122',
                    'issue_date' => now()->toDateString(), 'expiry_date' => $renewed,
                    'file' => \Illuminate\Http\UploadedFile::fake()->create('iqama-renewed.pdf', 40, 'application/pdf'),
                ]],
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $document->refresh();
        $this->assertSame('Senior Electrician', $document->document_subtype);
        $this->assertSame($renewed, $document->expiry_date->toDateString());
        $this->assertNotSame($oldPath, $document->file_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($oldPath);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame($renewed, $employee->refresh()->iqama_expiry_date->toDateString());
        $this->assertSame(1, $employee->documents()->where('document_type', 'IQAMA')->where('document_number', '2455001122')->count(), 'renewal edits the row instead of adding one');

        // Rows of another employee cannot be edited through this form.
        $other = Employee::whereKeyNot($employee->id)->firstOrFail();
        $foreign = $other->documents()->create(['document_type' => 'Passport', 'document_number' => 'P-OTHER', 'expiry_date' => now()->subDays(5)->toDateString(), 'status' => 'active']);
        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), $base + ['existing_documents' => [$foreign->id => ['document_number' => 'HACKED']]])
            ->assertRedirect();
        $this->assertSame('P-OTHER', $foreign->fresh()->document_number);

        // List filters by document type and validity.
        $this->actingAs($admin)->get(route('admin.hr.employees.index', ['doc_type' => 'Passport', 'doc_status' => 'expired']))
            ->assertOk()->assertSee($other->name)->assertDontSee($employee->employee_code);
        $this->actingAs($admin)->get(route('admin.hr.employees.index', ['doc_type' => 'IQAMA', 'doc_status' => 'valid']))
            ->assertOk()->assertSee($employee->employee_code);
        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))->assertOk()->assertSee('Senior Electrician');
    }

    // NR-17 / NR-18 -----------------------------------------------------------

    public function test_leave_types_attachments_and_balance(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->user('admin@example.com');
        $employee = Employee::firstOrFail();

        // The production defaults add what is missing and never duplicate.
        $before = \App\Models\LeaveType::count();
        $this->seed(\Database\Seeders\ProductionHrDefaultsSeeder::class);
        $this->assertSame($before + 1, \App\Models\LeaveType::count(), 'only the missing Urgent / Personal type is added');
        $this->seed(\Database\Seeders\ProductionHrDefaultsSeeder::class);
        $this->assertSame($before + 1, \App\Models\LeaveType::count());

        $balanceBefore = $employee->leaveBalance();
        $annual = \App\Models\LeaveType::where('code', 'ANNUAL')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.hr.leaves.store'), [
                'employee_id' => $employee->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->startOfYear()->addMonths(10)->toDateString(),
                'end_date' => now()->startOfYear()->addMonths(10)->addDays(2)->toDateString(),
                'reason' => 'Family visit', 'status' => 'approved',
                'attachment' => \Illuminate\Http\UploadedFile::fake()->create('tickets.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.hr.leaves.index'));

        $leave = \App\Models\LeaveRequest::latest('id')->firstOrFail();
        $this->assertSame('tickets.pdf', $leave->attachment_name);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($leave->attachment_path);
        $this->actingAs($admin)->get(route('admin.hr.leaves.attachment', $leave))->assertOk()->assertDownload('tickets.pdf');

        $balance = $employee->fresh()->leaveBalance();
        $this->assertSame(21, $balance['entitlement']);
        $this->assertSame($balanceBefore['used'] + 3.0, $balance['used']);
        $this->assertSame(round(21 - $balance['used'], 1), $balance['remaining']);

        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee('Leave Data')->assertSee('Entitlement')->assertSee('Remaining');
        $this->actingAs($admin)->get(route('admin.hr.leaves.show', $leave))->assertOk()->assertSee('tickets.pdf')->assertSee('Remaining Balance');

        // Entitlement is editable per employee.
        $this->actingAs($admin)
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code, 'first_name' => $employee->first_name,
                'contract_type' => $employee->contract_type, 'employee_classification' => 'Sponsorship',
                'basic_salary' => $employee->basic_salary, 'payment_method' => 'Bank Transfer', 'status' => 'active',
                'annual_leave_entitlement' => 30,
            ])
            ->assertRedirect();
        $this->assertSame(30, $employee->fresh()->leaveBalance()['entitlement']);
    }

    // NR-27 / NR-28 / NR-29 / NR-30 / NR-31 -----------------------------------

    private function createBill(User $user, Supplier $supplier, string $number = 'BILL-NR-1'): SupplierBill
    {
        $this->actingAs($user)->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => $supplier->id, 'bill_number' => $number,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Steel bars', 'quantity' => 10, 'unit_price' => 1000]],
        ])->assertSessionHasNoErrors();

        return SupplierBill::where('bill_number', $number)->firstOrFail();
    }

    private function createInvoice(User $user): CustomerInvoice
    {
        $this->actingAs($user)->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 20000]],
        ])->assertSessionHasNoErrors();

        return CustomerInvoice::latest('id')->firstOrFail();
    }

    public function test_approval_refuses_loudly_when_the_chart_of_accounts_is_incomplete(): void
    {
        $admin = $this->user('admin@example.com');
        $invoice = $this->createInvoice($admin);

        // The VAT screen shows the draft's VAT separately from the return (NR-30).
        $this->actingAs($admin)->get(route('admin.accounting.vat.index'))
            ->assertOk()
            ->assertSee('Draft Output VAT')
            ->assertSee(number_format(CustomerInvoice::where('payment_status', 'draft')->sum('vat_amount'), 2));

        $receivable = ChartOfAccount::where('account_code', '1200')->firstOrFail();
        $receivable->update(['account_code' => '1299']);

        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))
            ->assertSessionHasErrors('invoice');
        $invoice->refresh();
        $this->assertSame('draft', $invoice->payment_status, 'no half-approved invoice without a posting');
        $this->assertNull($invoice->journal_entry_id);
        $this->assertNull($invoice->zatcaRecord);

        $receivable->update(['account_code' => '1200']);
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $invoice->fresh()->payment_status);
    }

    public function test_supplier_payment_honours_accepted_types_method_and_purpose(): void
    {
        $admin = $this->user('admin@example.com');
        $supplier = Supplier::firstOrFail();
        $supplier->update(['allowed_payment_types' => 'Bank']);
        $bill = $this->createBill($admin, $supplier);
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        $cash = ChartOfAccount::where('account_code', '1110')->firstOrFail();
        $bank = ChartOfAccount::where('account_code', '1120')->firstOrFail();

        // The form only offers the channels this supplier accepts (NR-28) plus method and purpose (NR-29).
        $this->actingAs($admin)->get(route('admin.accounting.accounts-payable.payment', $bill))
            ->assertOk()
            ->assertSee($bank->label())
            ->assertDontSee($cash->label())
            ->assertSee('Payment Method')
            ->assertSee('Advance against this bill');

        $payload = ['payment_date' => now()->toDateString(), 'amount' => 1000, 'payment_method' => 'Cash', 'purpose' => 'Advance against this bill'];

        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.payment.store', $bill), $payload + ['payment_account_id' => $cash->id])
            ->assertSessionHasErrors('payment_account_id');

        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.payment.store', $bill), ['payment_account_id' => $bank->id, 'payment_method' => 'Bank Transfer'] + $payload)
            ->assertSessionHasNoErrors();

        $payment = $bill->payments()->firstOrFail();
        $this->assertSame('Bank Transfer', $payment->payment_method);
        $this->assertSame('Advance against this bill', $payment->purpose);
        $this->actingAs($admin)->get(route('admin.accounting.accounts-payable.show', $bill))->assertOk()->assertSee('Advance against this bill');

        // No usable account: clear guidance instead of an empty dropdown.
        $bank->update(['status' => 'inactive']);
        $this->actingAs($admin)->get(route('admin.accounting.accounts-payable.payment', $bill))
            ->assertOk()->assertSee('No payment account available')->assertSee('Chart of Accounts');
    }

    public function test_bill_and_invoice_lines_name_their_default_accounts(): void
    {
        $admin = $this->user('admin@example.com');

        $this->actingAs($admin)->get(route('admin.accounting.accounts-payable.create'))->assertOk()->assertSee('Default: 5200 - ');
        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.create'))->assertOk()->assertSee('Default: 4100 - ');
    }

    public function test_super_admin_can_reopen_posted_documents_with_a_reversing_entry(): void
    {
        $admin = $this->user('admin@example.com');
        $bill = $this->createBill($admin, Supplier::firstOrFail());
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $bill->refresh();
        $original = $bill->journalEntry;
        $this->assertDatabaseHas('vat_transactions', ['source_module' => 'Supplier Bill', 'source_id' => $bill->id]);

        // Only a Super Admin, and only with a reason.
        $this->actingAs($this->user('zubair@example.com'))
            ->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Wrong VAT'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.reopen', $bill), [])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'VAT rate keyed as 15% instead of 0%'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.accounting.accounts-payable.show', $bill));

        $bill->refresh();
        $this->assertSame('draft', $bill->status);
        $this->assertNull($bill->journal_entry_id);
        $this->assertStringContainsString('VAT rate keyed as 15% instead of 0%', $bill->notes);
        $this->assertDatabaseMissing('vat_transactions', ['source_module' => 'Supplier Bill', 'source_id' => $bill->id]);

        // The original posting is untouched; a posted reversing entry undoes it.
        $this->assertSame('posted', $original->fresh()->status);
        $reversal = JournalEntry::where('source_module', 'Manual')->where('source_id', $original->id)->firstOrFail();
        $this->assertSame('posted', $reversal->status);
        $this->assertSame($original->journal_number, $reversal->reference_number);
        $this->assertSame((float) $original->total_debit, (float) $reversal->total_credit);
        $payable = ChartOfAccount::where('account_code', '2100')->firstOrFail();
        $this->assertSame(11500.0, (float) $reversal->lines->firstWhere('chart_of_account_id', $payable->id)->debit);

        // Re-approval posts again; after a payment the bill can no longer be reopened.
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => now()->toDateString(), 'amount' => 500, 'payment_method' => 'Cash', 'purpose' => 'Bill payment',
            'payment_account_id' => ChartOfAccount::where('account_code', '1110')->firstOrFail()->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Try again'])->assertSessionHasErrors('bill');

        // Invoices: the pending ZATCA record is cancelled and re-issued on re-approval.
        $invoice = $this->createInvoice($admin);
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.reopen', $invoice), ['reason' => 'Customer PO number missing'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('draft', $invoice->payment_status);
        $this->assertNull($invoice->journal_entry_id);
        $this->assertSame('cancelled', $invoice->zatcaRecord->clearance_status);
        $this->assertDatabaseMissing('vat_transactions', ['source_module' => 'Customer Invoice', 'source_id' => $invoice->id]);

        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame('pending', $invoice->zatcaRecord->clearance_status);
    }

    // NR-20 / NR-21 -----------------------------------------------------------

    public function test_reports_offer_quick_date_ranges_and_csv_export_of_the_same_values(): void
    {
        $admin = $this->user('admin@example.com');

        // A preset resolves to explicit dates that are shown back to the user.
        $quarterStart = now()->startOfQuarter()->toDateString();
        $quarterEnd = now()->endOfQuarter()->toDateString();
        $this->actingAs($admin)->get(route('admin.accounting.reports.trial-balance', ['preset' => 'this_quarter']))
            ->assertOk()
            ->assertSee('This Quarter: '.$quarterStart.' to '.$quarterEnd)
            ->assertSee('Export Excel (CSV)');

        // The CSV carries the same filtered figures.
        $response = $this->actingAs($admin)->get(route('admin.accounting.reports.trial-balance', ['preset' => 'this_quarter', 'export' => 'csv']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('trial-balance-'.$quarterStart.'-to-'.$quarterEnd.'.csv', $response->headers->get('content-disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Code,Account,Type,"Debit Balance (SAR)","Credit Balance (SAR)"', $csv);
        $this->assertStringContainsString('1120,"Bank Account",Asset,', $csv);
        $this->assertStringContainsString(',Totals,', $csv);

        foreach (['balance-sheet', 'profit-loss', 'cash-flow', 'vat-report', 'project-cost-report'] as $report) {
            $this->actingAs($admin)->get(route('admin.accounting.reports.'.$report, ['preset' => 'this_year', 'export' => 'csv']))
                ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }

        // The VAT report honours the range: Q3 2026 overlaps July-September, Q2 does not.
        $this->actingAs($admin)->get(route('admin.accounting.reports.vat-report', ['from' => '2026-07-01', 'to' => '2026-09-30']))
            ->assertOk()->assertSee('Q3 2026')->assertDontSee('Q2 2026');
        $this->actingAs($admin)->get(route('admin.accounting.reports.vat-report', ['from' => '2027-01-01', 'to' => '2027-03-31']))
            ->assertOk()->assertSee('No VAT periods in the selected range');

        // A custom range is echoed as typed; garbage dates are ignored instead of crashing.
        $this->actingAs($admin)->get(route('admin.accounting.reports.profit-loss', ['from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()->assertSee('2026-01-01 to 2026-01-31');
        $this->actingAs($admin)->get(route('admin.accounting.reports.profit-loss', ['from' => 'not-a-date']))->assertOk();
    }

    // NR-16 -----------------------------------------------------------------

    private function marketingExecutive(): User
    {
        $department = Department::where('code', 'MKT')->firstOrFail();
        $managerRole = Role::where('code', 'MARKETING_MANAGER')->firstOrFail();

        $role = Role::create([
            'name' => 'Marketing Executive', 'code' => 'MARKETING_EXEC', 'department_id' => $department->id,
            'parent_id' => $managerRole->id, 'level' => 3, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Admin Dashboard', 'status' => 'active',
        ]);
        $role->permissions()->attach(Permission::where('module', 'Marketing')->whereIn('action', ['view', 'create', 'edit'])->pluck('id'));
        $role->permissions()->attach(Permission::where('module', 'Dashboard')->where('action', 'view')->pluck('id'));

        $user = User::create([
            'name' => 'Sara Executive', 'email' => 'sara@example.com', 'username' => 'sara.exec',
            'password' => 'a-strong-password-123', 'status' => 'active',
        ]);
        $user->roles()->attach($role->id, ['is_primary' => true]);

        return $user;
    }

    public function test_marketing_leads_flow_from_assignment_through_visits_to_a_customer(): void
    {
        $manager = $this->user('abdullah@example.com'); // Marketing Manager
        $executive = $this->marketingExecutive();

        // The module is visible to marketing, closed to others.
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->assertSee('Leads &amp; Visits', false);
        $this->actingAs($this->user('zubair@example.com'))->get(route('admin.marketing.leads.index'))->assertForbidden();

        // 1. The manager creates a lead and assigns it.
        $this->actingAs($manager)->post(route('admin.marketing.leads.store'), [
            'company_name' => 'Al Noor Real Estate', 'contact_name' => 'Eng. Faisal', 'contact_title' => 'Project Manager',
            'contact_phone' => '0501234567', 'city' => 'Riyadh', 'location' => 'Olaya District, Riyadh',
            'source' => 'Referral', 'requirement' => 'Villa compound, 12 units', 'estimated_value' => 2500000,
            'assigned_to' => $executive->id, 'next_follow_up_date' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();

        $lead = MarketingLead::where('company_name', 'Al Noor Real Estate')->firstOrFail();
        $this->assertSame('LD-001', $lead->lead_code);
        $this->assertSame('assigned', $lead->status);
        $this->assertSame($manager->id, $lead->created_by);

        $this->actingAs($manager)->post(route('admin.marketing.leads.store'), ['company_name' => 'Unassigned Prospect'])->assertSessionHasNoErrors();
        $hidden = MarketingLead::where('company_name', 'Unassigned Prospect')->firstOrFail();
        $this->assertSame('new', $hidden->status);

        // 2. The executive sees only their own lead.
        $this->actingAs($executive)->get(route('admin.marketing.leads.index'))
            ->assertOk()->assertSee('Al Noor Real Estate')->assertDontSee('Unassigned Prospect');
        $this->actingAs($executive)->get(route('admin.marketing.leads.show', $hidden))->assertNotFound();
        $this->actingAs($manager)->get(route('admin.marketing.leads.index'))
            ->assertOk()->assertSee('Al Noor Real Estate')->assertSee('Unassigned Prospect');

        // 3. A visit is recorded: person met, outcome and follow-up drive the lead status.
        $this->actingAs($executive)->post(route('admin.marketing.leads.visits.store', $lead), [
            'visit_date' => now()->addDay()->toDateString(), 'person_met' => 'Eng. Faisal', 'outcome' => 'follow_up',
        ])->assertSessionHasErrors('visit_date');

        $this->actingAs($executive)->post(route('admin.marketing.leads.visits.store', $lead), [
            'visit_date' => now()->toDateString(), 'visit_time' => '10:30', 'person_met' => 'Eng. Faisal', 'person_title' => 'Project Manager',
            'outcome' => 'quotation_requested', 'remarks' => 'Wants a quotation for phase 1 by next week.',
            'next_follow_up_date' => now()->addDays(7)->toDateString(), 'next_action' => 'Send quotation',
        ])->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame('follow_up', $lead->status);
        $this->assertSame(now()->addDays(7)->toDateString(), $lead->next_follow_up_date->toDateString());
        $this->assertSame($executive->id, $lead->visits()->first()->user_id);

        $this->actingAs($executive)->get(route('admin.marketing.leads.show', $lead))
            ->assertOk()->assertSee('Eng. Faisal')->assertSee('Quotation requested')->assertSee('Send quotation')->assertSee('10:30');

        // 4. The manager's report lists the visit and exports the same rows.
        $this->actingAs($manager)->get(route('admin.marketing.report', ['preset' => 'this_month']))
            ->assertOk()->assertSee('Al Noor Real Estate')->assertSee('Olaya District, Riyadh')->assertSee('Sara Executive')->assertSee('This Month');
        $csv = $this->actingAs($manager)->get(route('admin.marketing.report', ['preset' => 'this_month', 'export' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Date,Time,Lead,Client,Location,"Person Met"', $csv->streamedContent());
        $this->assertStringContainsString('Al Noor Real Estate', $csv->streamedContent());

        // 5. Won: the lead closes and becomes a customer; no more visits afterwards.
        $this->actingAs($executive)->post(route('admin.marketing.leads.visits.store', $lead), [
            'visit_date' => now()->toDateString(), 'person_met' => 'Eng. Faisal', 'outcome' => 'won', 'remarks' => 'Contract signed.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('won', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->next_follow_up_date);

        $this->actingAs($executive)->post(route('admin.marketing.leads.visits.store', $lead), [
            'visit_date' => now()->toDateString(), 'person_met' => 'Someone', 'outcome' => 'follow_up',
        ])->assertSessionHasErrors('visit');

        $this->actingAs($manager)->post(route('admin.marketing.leads.convert', $lead))->assertSessionHasNoErrors();
        $lead->refresh();
        $this->assertNotNull($lead->customer_id);
        $this->assertSame('Al Noor Real Estate', $lead->customer->name);
        $this->assertSame('Eng. Faisal', $lead->customer->contact_person);
        $this->actingAs($manager)->get(route('admin.master.customers.show', $lead->customer))->assertOk()->assertSee('Al Noor Real Estate');

        // Staff cannot delete; the manager can.
        $this->actingAs($executive)->delete(route('admin.marketing.leads.destroy', $lead))->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.marketing.leads.destroy', $hidden))->assertRedirect(route('admin.marketing.leads.index'));
        $this->assertDatabaseMissing('marketing_leads', ['id' => $hidden->id]);
    }
}
