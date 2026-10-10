<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee payslip: a print-friendly page rendered from the stored payroll
 * row, for Payroll-authorized users only. Historical values never change when
 * the employee's salary changes later; there is no employee self-service.
 */
class PayrollPayslipTest extends TestCase
{
    use RefreshDatabase;

    protected Project $mine;

    protected Project $theirs;

    protected Employee $ahmed;

    protected Employee $khalid;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-PS-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-PS-B', 'status' => 'active']);
        // Seeded 5100 Salary Expense requires a cost center: each project gets its single active one.
        foreach ([$this->mine, $this->theirs] as $project) {
            CostCenter::create(['code' => 'CC-'.$project->code, 'name' => $project->name, 'type' => 'project', 'linked_id' => $project->id, 'status' => 'active']);
        }
        Employee::query()->update(['status' => 'inactive']);
        $this->ahmed = Employee::create(['employee_code' => 'EMP-PS-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-03-01', 'project_id' => $this->mine->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'iqama_number' => '2412345678', 'payment_method' => 'Bank Transfer', 'bank_name' => 'Al Rajhi']);
        $this->khalid = Employee::create(['employee_code' => 'EMP-PS-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'joining_date' => '2024-01-01', 'project_id' => $this->theirs->id, 'basic_salary' => 9000]);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'fixed_deduction' => 300, 'effective_from' => '2026-01-01', 'status' => 'active']);
        $deductions = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Payroll Deductions Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
        AutomaticPostingRule::where('source_module', 'Payroll')->where('trigger_event', 'Payroll Approved')->update(['deduction_account_id' => $deductions->id, 'auto_post' => true, 'status' => 'active']);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u', bool $mobile = false): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'PS role '.$suffix, 'code' => 'PS_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'PS '.$suffix, 'email' => 'ps-'.$suffix.'@example.test', 'username' => 'ps.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id, 'mobile_access' => $mobile]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function approvedRun(): PayrollRun
    {
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.store'), ['payroll_month' => 9, 'payroll_year' => 2026])->assertSessionHasNoErrors();
        $run = PayrollRun::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        $this->flushSession();
        $run->refresh();
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->accounting_status, (string) $run->posting_error);

        return $run;
    }

    public function test_payslip_renders_the_stored_row_and_survives_later_salary_changes(): void
    {
        $run = $this->approvedRun();
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();

        $page = $this->actingAs($this->admin())->get(route('admin.hr.payroll.payslip', [$run, $item->id]))->assertOk();
        $page->assertSee('Payslip')->assertSee('September 2026')->assertSee($run->code)->assertSee('EMP-PS-A')->assertSee('Ahmed Hassan')
            ->assertSee('Riyadh Commercial Tower')->assertSee('2412345678')->assertSee('Bank Transfer')
            ->assertSeeInOrder(['Basic Salary', '6,500.00', 'Allowances', '2,000.00', 'Approved Overtime', '0.00', 'Gross', '8,500.00', 'Deductions', '300.00', 'Net Salary', '8,200.00'])
            ->assertSee('Print / Save as PDF')->assertSee('do not change pay')->assertSee('approved')->assertSee('not part of this document');

        // A later raise never rewrites the approved payslip.
        SalaryStructure::where('employee_id', $this->ahmed->id)->update(['status' => 'inactive']);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 12000, 'fixed_deduction' => 0, 'effective_from' => '2026-01-01', 'status' => 'active']);
        $this->ahmed->update(['basic_salary' => 12000]);
        $this->actingAs($this->admin())->get(route('admin.hr.payroll.payslip', [$run, $item->id]))->assertOk()->assertSee('6,500.00')->assertSee('8,200.00')->assertDontSee('12,000.00');
    }

    public function test_payslip_is_payroll_authorized_only_and_bound_to_its_run(): void
    {
        $run = $this->approvedRun();
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();
        $url = route('admin.hr.payroll.payslip', [$run, $item->id]);

        auth()->logout(); // the fixture signed in as admin
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user(['HR' => ['view']], suffix: 'hr'))->get($url)->assertForbidden();
        // The employee's own login (linked, mobile) has no self-service payslip in this phase.
        $self = $this->user(['Attendance' => ['view', 'mobile']], $this->mine, 'self', true);
        $this->ahmed->update(['user_id' => $self->id]);
        $this->actingAs($self)->get($url)->assertForbidden();

        $this->actingAs($this->user(['Payroll' => ['view']], suffix: 'pay'))->get($url)->assertOk();

        // An item id that belongs to another run, or a foreign-scope employee, is not found through this run.
        $other = PayrollRun::create(['code' => 'PR-PS-OTHER', 'payroll_month' => 8, 'payroll_year' => 2026, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'status' => 'approved']);
        $this->actingAs($this->admin())->get(route('admin.hr.payroll.payslip', [$other, $item->id]))->assertNotFound();
        $scoped = $this->user(['Payroll' => ['view']], $this->theirs, 'scoped');
        $this->actingAs($scoped)->get($url)->assertNotFound();
        $khalidItem = $run->items()->where('employee_id', $this->khalid->id)->firstOrFail();
        $this->actingAs($scoped)->get(route('admin.hr.payroll.payslip', [$run, $khalidItem->id]))->assertOk();
    }

    public function test_employee_workspace_offers_payslip_and_run_links_only_with_payroll_view(): void
    {
        $run = $this->approvedRun();
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();
        $link = route('admin.hr.payroll.payslip', [$run, $item->id, 'return_to' => '/admin/hr/employees/'.$this->ahmed->id.'#payroll']);

        $page = $this->actingAs($this->user(['HR' => ['view'], 'Payroll' => ['view']], suffix: 'hrpay'))->get(route('admin.hr.employees.show', $this->ahmed))->assertOk();
        $this->assertStringContainsString($link, html_entity_decode($page->getContent()));
        $page->assertSee('Posted to accounting')->assertSee('Open payroll run');

        $this->actingAs($this->user(['HR' => ['view']], suffix: 'hronly'))->get(route('admin.hr.employees.show', $this->ahmed))->assertOk()->assertDontSee('Payslip')->assertDontSee('id="payroll"', false);

        // Back from the payslip returns to the employee's payroll section.
        $this->actingAs($this->admin())->get($link)->assertOk()->assertSee('href="/admin/hr/employees/'.$this->ahmed->id.'#payroll"', false);
    }
}
