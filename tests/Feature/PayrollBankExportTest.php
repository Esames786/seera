<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\PayrollBankExport;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Support\Iban;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Generic payroll bank transfer export: stored payroll values, employee bank
 * data frozen at generation, incomplete employees block with a readable list,
 * Payroll export + whole-run authorization only, no public URL. Not a verified
 * Saudi WPS / Mudad format.
 */
class PayrollBankExportTest extends TestCase
{
    use RefreshDatabase;

    protected Project $mine;

    protected Project $theirs;

    protected Employee $ahmed;

    protected Employee $khalid;

    protected Employee $sara;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-EXP-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-EXP-B', 'status' => 'active']);
        foreach ([$this->mine, $this->theirs] as $project) {
            CostCenter::create(['code' => 'CC-'.$project->code, 'name' => $project->name, 'type' => 'project', 'linked_id' => $project->id, 'status' => 'active']);
        }
        Employee::query()->update(['status' => 'inactive']);
        $this->ahmed = Employee::create(['employee_code' => 'EMP-EXP-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-03-01', 'project_id' => $this->mine->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500,
            'payment_method' => 'Bank Transfer', 'bank_name' => 'Al Rajhi Bank', 'iban' => 'SA03 8000 0000 6080 1016 7519']);
        $this->khalid = Employee::create(['employee_code' => 'EMP-EXP-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-01-01', 'project_id' => $this->theirs->id, 'basic_salary' => 9000, 'payment_method' => 'Cash']);
        $this->sara = Employee::create(['employee_code' => 'EMP-EXP-C', 'first_name' => 'Sara', 'last_name' => 'Nasser', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2025-01-01', 'project_id' => $this->mine->id, 'basic_salary' => 4000, 'payment_method' => 'Bank Transfer', 'bank_name' => 'SNB', 'iban' => 'SA0310000000000000000001']);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'fixed_deduction' => 300, 'effective_from' => '2026-01-01', 'status' => 'active']);
        $deductions = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Payroll Deductions Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
        AutomaticPostingRule::where('source_module', 'Payroll')->where('trigger_event', 'Payroll Approved')->update(['deduction_account_id' => $deductions->id, 'auto_post' => true, 'status' => 'active']);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'EXP role '.$suffix, 'code' => 'EXP_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'EXP '.$suffix, 'email' => 'exp-'.$suffix.'@example.test', 'username' => 'exp.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function exporter(): User
    {
        return $this->user(['Payroll' => ['view', 'export']], suffix: 'exporter');
    }

    protected function makeRun(int $month = 9, string $status = 'approved'): PayrollRun
    {
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.store'), ['payroll_month' => $month, 'payroll_year' => 2026])->assertSessionHasNoErrors();
        $run = PayrollRun::where('payroll_month', $month)->where('payroll_year', 2026)->latest('id')->firstOrFail();
        if (in_array($status, ['processed', 'approved'], true)) {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasNoErrors();
        }
        if ($status === 'approved') {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        }
        $this->flushSession();
        auth()->logout();

        return $run->fresh();
    }

    public function test_iban_helper_validates_normalizes_and_masks(): void
    {
        $this->assertTrue(Iban::isValid('SA03 8000 0000 6080 1016 7519'));
        $this->assertTrue(Iban::isValid('GB82 WEST 1234 5698 7654 32'));
        $this->assertFalse(Iban::isValid('SA03 8000 0000 6080 1016 7518'), 'check digits');
        $this->assertFalse(Iban::isValid('PRIVATE-IBAN'));
        $this->assertFalse(Iban::isValid(''));
        $this->assertFalse(Iban::isValid(null));
        $this->assertSame('SA0380000000608010167519', Iban::normalize('sa03 8000-0000 6080 1016 7519'));
        $this->assertSame('SA•• •••• 7519', Iban::mask('SA03 8000 0000 6080 1016 7519'));
        $this->assertNull(Iban::mask(null));
    }

    public function test_employee_form_refuses_an_invalid_iban_and_stores_a_normalized_one(): void
    {
        $base = ['employee_code' => 'EMP-EXP-F', 'first_name' => 'Form', 'last_name' => 'Tester', 'employee_classification' => 'Sponsorship', 'joining_date' => '2026-01-01',
            'contract_type' => 'Full Time', 'basic_salary' => 3000, 'payment_method' => 'Bank Transfer', 'bank_name' => 'SNB', 'status' => 'active', 'annual_leave_entitlement' => 21];
        $this->actingAs($this->admin())->post(route('admin.hr.employees.store'), $base + ['iban' => 'SA03 8000 0000 6080 1016 7518'])->assertSessionHasErrors('iban');
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-EXP-F']);
        $this->actingAs($this->admin())->post(route('admin.hr.employees.store'), $base + ['iban' => 'sa03 8000 0000 6080 1016 7519'])->assertSessionHasNoErrors();
        $this->assertSame('SA0380000000608010167519', Employee::where('employee_code', 'EMP-EXP-F')->value('iban'));
        // The employee view masks the IBAN.
        $this->actingAs($this->admin())->get(route('admin.hr.employees.show', Employee::where('employee_code', 'EMP-EXP-F')->first()))->assertOk()->assertSee('SA•• •••• 7519')->assertDontSee('SA0380000000608010167519');
    }

    public function test_readiness_lists_incomplete_employees_and_blocks_generation_until_fixed(): void
    {
        $run = $this->makeRun();
        $exporter = $this->exporter();
        $page = $this->actingAs($exporter)->get(route('admin.hr.payroll.show', $run))->assertOk();
        $page->assertSee('Bank Export')->assertSee('3 total')->assertSee('2 bank transfer')->assertSee('1 cash')->assertSee('EMP-EXP-C')->assertSee('IBAN invalid')->assertSee('incomplete bank details')->assertSee('not a verified Saudi WPS');

        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasErrors('export');
        $this->assertSame(0, PayrollBankExport::count());
        $errors = session('errors')?->get('export') ?? [];
        $this->assertStringContainsString('EMP-EXP-C Sara Nasser (IBAN invalid)', implode(' ', $errors));

        $this->sara->update(['iban' => null]);
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasErrors('export');
        $this->sara->update(['iban' => 'SA0380000000608010167519', 'bank_name' => '']);
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasErrors('export');
        $this->assertSame(0, PayrollBankExport::count());

        $this->sara->update(['bank_name' => 'SNB']);
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasNoErrors();
        $this->assertSame(1, PayrollBankExport::count());
        $this->actingAs($exporter)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Ready')->assertSee('payroll-bank-transfer-'.$run->code.'-v1.csv');
    }

    public function test_export_uses_stored_payroll_values_freezes_bank_details_and_excludes_cash_employees(): void
    {
        $this->sara->update(['iban' => 'SA0380000000608010167519']);
        $run = $this->makeRun();
        $exporter = $this->exporter();
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasNoErrors();
        $export = PayrollBankExport::firstOrFail();
        $this->assertSame(2, $export->row_count);
        $this->assertSame(1, $export->excluded_count, 'the cash employee is excluded and counted');
        $this->assertEqualsWithDelta(12200, (float) $export->total_amount, 0.01, 'Ahmed 8,200 + Sara 4,000 (stored net amounts)');
        $this->assertSame($exporter->id, $export->generated_by);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Payroll', 'action' => 'Bank export generated']);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'SA0380000000608010167519']);

        $download = $this->actingAs($exporter)->get(route('admin.hr.payroll.bank-export.download', [$run, $export->id]))->assertOk();
        $download->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('attachment; filename="payroll-bank-transfer-'.$run->code.'-v1.csv"', $download->headers->get('Content-Disposition'));
        $csv = $download->getContent();
        $this->assertStringContainsString('"Payroll Period","Payroll Run","Employee Code","Employee Name","Payment Method","Bank Name",IBAN,"Net Salary (SAR)",Reference', str_replace("\xEF\xBB\xBF", '', $csv));
        $this->assertStringContainsString('"September 2026",'.$run->code.',EMP-EXP-A,"Ahmed Hassan","Bank Transfer","Al Rajhi Bank",SA0380000000608010167519,8200.00,'.$run->code.'/EMP-EXP-A', $csv);
        $this->assertStringContainsString('EMP-EXP-C,"Sara Nasser","Bank Transfer",SNB,SA0380000000608010167519,4000.00', $csv);
        $this->assertStringNotContainsString('EMP-EXP-B', $csv, 'cash employee not in the bank file');
        $this->assertSame(hash('sha256', $csv), $export->file_hash);

        // Later salary / bank changes never alter the generated file; a new generation is a new version.
        $this->ahmed->update(['basic_salary' => 12000, 'iban' => 'GB82WEST12345698765432', 'bank_name' => 'Other Bank']);
        SalaryStructure::where('employee_id', $this->ahmed->id)->update(['basic_salary' => 12000]);
        $again = $this->actingAs($exporter)->get(route('admin.hr.payroll.bank-export.download', [$run, $export->id]))->assertOk()->getContent();
        $this->assertSame($csv, $again, 'frozen file is byte-identical');
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasNoErrors();
        $v2 = PayrollBankExport::where('payroll_run_id', $run->id)->where('sequence', 2)->firstOrFail();
        $v2csv = $this->actingAs($exporter)->get(route('admin.hr.payroll.bank-export.download', [$run, $v2->id]))->getContent();
        $this->assertStringContainsString('"Other Bank",GB82WEST12345698765432,8200.00', $v2csv, 'new bank details, same stored salary');
        $this->assertStringNotContainsString('12000', $v2csv, 'the stored approved net is never recalculated');
    }

    public function test_export_requires_approval_and_is_locked_after_payment(): void
    {
        $this->sara->update(['iban' => 'SA0380000000608010167519']);
        $exporter = $this->exporter();
        $processed = $this->makeRun(8, 'processed');
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $processed))->assertSessionHasErrors('export');
        $this->actingAs($exporter)->get(route('admin.hr.payroll.show', $processed))->assertOk()->assertSee('must be approved before a transfer file');

        $run = $this->makeRun();
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasNoErrors();
        $bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        $finance = $this->user(['Payroll' => ['view', 'post', 'approve', 'export'], 'Accounts Payable' => ['view', 'process']], suffix: 'fin');
        $this->actingAs($finance)->post(route('admin.hr.payroll.settle', $run), ['payment_date' => '2026-10-05', 'payment_account_id' => $bank->id, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($exporter)->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasErrors('export');
        $this->assertSame(1, PayrollBankExport::count());
        $this->actingAs($exporter)->get(route('admin.hr.payroll.bank-export.download', [$run, PayrollBankExport::first()->id]))->assertOk();
        $this->actingAs($exporter)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('The run is paid: the file generated before payment stays downloadable');
    }

    public function test_export_is_payroll_export_and_whole_run_only_with_no_public_url(): void
    {
        $this->sara->update(['iban' => 'SA0380000000608010167519']);
        $run = $this->makeRun();
        $this->actingAs($this->exporter())->post(route('admin.hr.payroll.bank-export', $run))->assertSessionHasNoErrors();
        $export = PayrollBankExport::firstOrFail();
        $download = route('admin.hr.payroll.bank-export.download', [$run, $export->id]);

        auth()->logout();
        $this->get($download)->assertRedirect(route('login'));
        $this->actingAs($this->user(['Payroll' => ['view', 'post', 'approve']], suffix: 'noexport'))->get($download)->assertForbidden();
        $this->actingAs($this->user(['Payroll' => ['view', 'post', 'approve']], suffix: 'noexport2'))->post(route('admin.hr.payroll.bank-export', $run))->assertForbidden();
        $this->actingAs($this->user(['HR' => ['view', 'export']], suffix: 'hr'))->get($download)->assertForbidden();

        // A project-scoped exporter sees only part of the company-wide run: no file, no download, clear note.
        $scoped = $this->user(['Payroll' => ['view', 'export']], $this->mine, 'scoped');
        $this->actingAs($scoped)->post(route('admin.hr.payroll.bank-export', $run))->assertForbidden();
        $this->actingAs($scoped)->get($download)->assertForbidden();
        $this->actingAs($scoped)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('outside your scope')->assertDontSee('Generate bank transfer file');
        $this->assertSame(1, PayrollBankExport::count());

        // A file of another run is not reachable through this run.
        $other = $this->makeRun(10);
        $this->actingAs($this->exporter())->get(route('admin.hr.payroll.bank-export.download', [$other, $export->id]))->assertNotFound();
    }
}
