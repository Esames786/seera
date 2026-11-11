<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EquipmentAsset;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RecordUploads;
use App\Support\CodeGenerator;
use App\Support\Iban;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientFeedbackClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($this->admin);
        Storage::fake('local');
    }

    private function employee(array $extra = []): Employee
    {
        return Employee::create($extra + ['employee_code' => 'SP-FEEDBACK', 'first_name' => 'Training', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'annual_leave_entitlement' => 21]);
    }

    private function document(): array
    {
        return ['document_type' => 'cr', 'document_number' => 'TRAIN-CR', 'file' => UploadedFile::fake()->create('registration.pdf', 12, 'application/pdf')];
    }

    public function test_blank_or_omitted_initial_meter_uses_zero_and_supplied_meter_is_preserved(): void
    {
        foreach (['', null, 0, 42.5, 'omitted'] as $i => $reading) {
            $data = ['asset_code' => 'FIX-METER-'.$i, 'name' => 'Training asset', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => $i === 1 ? 'none' : 'hours'];
            if ($reading !== 'omitted') {
                $data['current_meter'] = $reading;
            }
            $this->post(route('admin.equipment.store'), $data)->assertSessionHasNoErrors();
            $this->assertEquals($reading === 'omitted' || $reading === '' || $reading === null ? 0 : $reading, EquipmentAsset::where('asset_code', $data['asset_code'])->firstOrFail()->current_meter);
        }
        $this->get(route('admin.equipment.index'))->assertOk();
    }

    public function test_invalid_meter_does_not_save_asset_or_initial_documents(): void
    {
        $this->post(route('admin.equipment.store'), ['asset_code' => 'FIX-BAD', 'name' => 'Bad meter', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours', 'current_meter' => -1, 'new_documents' => [$this->document()]])->assertSessionHasErrors('current_meter');
        $this->assertDatabaseMissing('equipment_assets', ['asset_code' => 'FIX-BAD']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_equipment_can_upload_documents_during_initial_save_and_edit(): void
    {
        $row = $this->document();
        $row['document_type'] = 'registration';
        $data = ['asset_code' => 'FIX-DOC', 'name' => 'Training asset', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours', 'current_meter' => ''];
        $this->post(route('admin.equipment.store'), $data + ['new_documents' => [$row]])->assertSessionHasNoErrors();
        $asset = EquipmentAsset::where('asset_code', 'FIX-DOC')->firstOrFail();
        $doc = $asset->documents()->sole();
        Storage::disk('local')->assertExists($doc->path);
        $this->get(route('admin.equipment.documents.file', [$asset, $doc->id]))->assertOk();
        $this->put(route('admin.equipment.update', $asset), $data + ['status' => 'available', 'new_documents' => [['document_type' => 'insurance', 'file' => UploadedFile::fake()->create('insurance.pdf', 2, 'application/pdf')]]])->assertSessionHasNoErrors();
        $this->assertSame(2, $asset->documents()->count());
    }

    public function test_customer_and_supplier_documents_are_private_and_bound_to_the_parent(): void
    {
        $journals = DB::table('journal_entries')->count();
        foreach (['customer', 'supplier'] as $kind) {
            $class = $kind === 'customer' ? Customer::class : Supplier::class;
            $base = ['name' => 'Training '.$kind, 'status' => 'active', 'type' => 'Company'];
            $this->post(route('admin.master.'.$kind.'s.store'), $base + ['new_documents' => [$this->document()]])->assertSessionHasNoErrors();
            $owner = $class::where('name', $base['name'])->firstOrFail();
            $doc = $owner->documents()->sole();
            $url = route('admin.master.'.$kind.'s.documents.show', [$owner, $doc->id]);
            $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get($url.'?download=1')->assertDownload();
            $other = $class::create(['name' => 'Different party', 'code' => 'OTHER-'.$kind, 'type' => 'Company', 'status' => 'active']);
            $this->get(route('admin.master.'.$kind.'s.documents.show', [$other, $doc->id]))->assertNotFound();
            $this->get(route('admin.master.'.$kind.'s.show', $owner))->assertOk()->assertSee('registration.pdf')->assertDontSee('type="file"', false);
            $this->get(route('admin.master.'.$kind.'s.edit', $owner))->assertOk()->assertSee('new_documents[0][file]', false);
            $this->assertArrayNotHasKey('path', $doc->toArray());
        }
        $this->assertSame($journals, DB::table('journal_entries')->count());
        $outsider = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $this->actingAs($outsider)->get($url)->assertForbidden();
    }

    public function test_invalid_party_upload_is_rejected_before_saving_the_master(): void
    {
        $this->post(route('admin.master.customers.store'), ['name' => 'Must not save', 'type' => 'Company', 'status' => 'active', 'new_documents' => [['file' => UploadedFile::fake()->create('script.svg', 2, 'image/svg+xml')]]])->assertSessionHasErrors('new_documents.0.file');
        $this->assertDatabaseMissing('customers', ['name' => 'Must not save']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_transaction_failure_removes_new_document_and_rolls_back_parent(): void
    {
        $request = Request::create('/', 'POST', ['new_documents' => [['document_type' => 'cr']]], [], ['new_documents' => [['file' => $this->document()['file']]]]);
        $request->setUserResolver(fn () => $this->admin);
        $uploads = new RecordUploads;
        try {
            $uploads->transaction(function () use ($uploads, $request) {
                $customer = Customer::create(['name' => 'Rolled back', 'code' => 'ROLLBACK-ONLY', 'type' => 'Company', 'status' => 'active']);
                $uploads->documents($request, $customer);
                throw new \RuntimeException('Synthetic downstream failure');
            });
            $this->fail('Expected rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic downstream failure', $error->getMessage());
        }
        $this->assertDatabaseMissing('customers', ['code' => 'ROLLBACK-ONLY']);
        $this->assertDatabaseCount('customer_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_employee_photo_upload_replace_remove_and_private_read(): void
    {
        $employee = $this->employee();
        $base = ['first_name' => $employee->first_name, 'employee_code' => $employee->employee_code, 'status' => 'active', 'employee_classification' => 'Sponsorship', 'contract_type' => 'Full Time', 'basic_salary' => 0, 'payment_method' => 'Cash'];
        $this->put(route('admin.hr.employees.update', $employee), $base + ['profile_photo' => UploadedFile::fake()->image('portrait.jpg')])->assertSessionHasNoErrors();
        $path = $employee->fresh()->profile_photo_path;
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);
        $url = route('admin.profile-photos.show', ['employee', $employee->id]);
        $this->get($url)->assertOk();
        $this->put(route('admin.hr.employees.update', $employee), $base + ['profile_photo' => UploadedFile::fake()->image('replacement.png')])->assertSessionHasNoErrors();
        $this->assertNotSame($path, $employee->fresh()->profile_photo_path);
        $this->put(route('admin.hr.employees.update', $employee), $base + ['remove_profile_photo' => 1])->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->profile_photo_path);
        $this->get($url)->assertNotFound();
    }

    public function test_user_photo_works_in_create_and_connected_manage_without_changing_roles(): void
    {
        $role = $this->admin->primaryRole();
        $this->post(route('admin.users.store'), ['name' => 'Training account', 'email' => 'photo@example.test', 'status' => 'active', 'role_id' => $role->id, 'profile_photo' => UploadedFile::fake()->image('photo.jpg')])->assertSessionHasNoErrors();
        $user = User::where('email', 'photo@example.test')->firstOrFail();
        $this->assertNotEmpty($user->profile_photo_path);
        $roles = $user->roles()->pluck('roles.id')->all();
        $this->put(route('admin.users.update', $user), ['_workspace_profile' => 1, 'name' => $user->name, 'email' => $user->email, 'language' => 'English', 'profile_photo' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $this->assertSame($roles, $user->roles()->pluck('roles.id')->all());
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee('name="profile_photo"', false);
        $this->assertArrayNotHasKey('profile_photo_path', $user->fresh()->toArray());
    }

    public function test_photo_routes_refuse_unprivileged_or_out_of_scope_reads_and_svg_uploads(): void
    {
        $employee = $this->employee();
        $this->put(route('admin.hr.employees.update', $employee), ['first_name' => 'Training', 'employee_code' => $employee->employee_code, 'status' => 'active', 'employee_classification' => 'Sponsorship', 'contract_type' => 'Full Time', 'basic_salary' => 0, 'payment_method' => 'Cash', 'profile_photo' => UploadedFile::fake()->create('avatar.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('profile_photo');
        $outsider = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $this->actingAs($outsider)->get(route('admin.profile-photos.show', ['employee', $employee->id]))->assertForbidden();
        $this->get(route('admin.profile-photos.show', ['user', $this->admin->id]))->assertForbidden();
    }

    public function test_successive_automatic_party_codes_and_next_preview_do_not_reuse_old_codes(): void
    {
        foreach (['customer', 'supplier'] as $kind) {
            $class = $kind === 'customer' ? Customer::class : Supplier::class;
            $prefix = $kind === 'customer' ? 'CUS-' : 'SUP-';
            $codes = [];
            foreach ([1, 2] as $n) {
                $next = CodeGenerator::sequential($kind.'s', 'code', $prefix);
                $this->get(route('admin.master.'.$kind.'s.create'))->assertOk()->assertSee($next)->assertSee('data-code-manual', false);
                $this->post(route('admin.master.'.$kind.'s.store'), ['name' => 'Auto '.$kind.$n, 'code' => '', 'type' => 'Company', 'status' => 'active', '_save_action' => 'new'])->assertRedirect(route('admin.master.'.$kind.'s.create'));
                $codes[] = $class::where('name', 'Auto '.$kind.$n)->value('code');
                $this->assertSame($next, $codes[$n - 1]);
            }
            $this->assertNotSame($codes[0], $codes[1]);
        }
    }

    public function test_party_manual_code_validation_reopen_preserves_editable_value_in_both_languages(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (['customer', 'supplier'] as $kind) {
                $code = 'TRAIN-'.$kind.'-101';
                $response = $this->withSession(['locale' => $locale, '_old_input' => ['code' => $code]])
                    ->get(route('admin.master.'.$kind.'s.create'))->assertOk();
                $document = new \DOMDocument;
                libxml_use_internal_errors(true);
                $document->loadHTML($response->getContent());
                $xpath = new \DOMXPath($document);
                $input = $xpath->query('//*[@data-code-input]')->item(0);
                $preview = $xpath->query('//*[@data-code-preview]')->item(0);
                $this->assertSame($code, $input->getAttribute('value'));
                $this->assertFalse($input->hasAttribute('hidden'));
                $this->assertFalse($input->hasAttribute('readonly'));
                $this->assertTrue($preview->hasAttribute('hidden'));
                $this->assertTrue($xpath->query('//*[@data-code-manual]')->item(0)->hasAttribute('checked'));
                $this->assertSame('code', $xpath->query('//*[@data-code-label]')->item(0)->getAttribute('for'));
            }
        }
    }

    public function test_bank_copy_paste_normalization_preserves_checksum_validation(): void
    {
        $valid = 'SA03 8000 0000 6080 1016 7519';
        $this->assertTrue(Iban::isValid($valid));
        $this->assertTrue(Iban::isValid("SA٠٣\u{00A0}٨٠٠٠\u{200B}٠٠٠٠ ٦٠٨٠ ١٠١٦ ٧٥١٩"));
        $this->assertFalse(Iban::isValid('SA0380000000608010167518'));
        $this->assertSame('SA0380000000608010167519', Iban::normalize($valid));
        $this->assertStringContainsString('check digits', Iban::validationMessage('SA0380000000608010167518'));
    }

    public function test_annual_balance_excludes_other_years_types_and_pending_days(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10));
        $employee = $this->employee();
        $annual = LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'status' => 'active']);
        $personal = LeaveType::firstOrCreate(['code' => 'PERSONAL-FIX'], ['name' => 'Personal Leave', 'status' => 'active']);
        foreach ([[$annual, '2026-08-01', 3, 'approved'], [$annual, '2026-12-01', 2, 'pending'], [$personal, '2025-08-21', 9, 'approved'], [$annual, '2025-07-01', 2, 'approved']] as [$type,$date,$days,$status]) {
            LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $date, 'end_date' => $date, 'total_days' => $days, 'status' => $status]);
        }
        $this->assertEquals(18, $employee->leaveBalance()['remaining']);
        $this->assertEquals(2, $employee->leaveBalance()['pending']);
        $this->assertEquals(19, $employee->leaveBalance(2025)['remaining']);
        $this->get(route('admin.hr.employees.show', [$employee, 'leave_year' => 2025]))->assertOk()->assertSee('2025')->assertSee(__('feedback.balance_rule'));
        DB::table('leave_types')->where('id', $annual->id)->update(['code' => 'ANNUAL-RENAMED']);
        $this->assertEquals(21, $employee->leaveBalance()['remaining']);
    }

    public function test_own_leave_requires_no_hr_wide_permission_but_refuses_forged_identity_and_approval(): void
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $employee = $this->employee(['user_id' => $user->id]);
        $type = LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'status' => 'active']);
        $this->actingAs($user)->get(route('admin.my-leave.index'))->assertOk()->assertSee($employee->employee_code);
        $data = ['leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03', 'reason' => 'Training request'];
        $this->post(route('admin.my-leave.store'), $data + ['employee_id' => $employee->id, 'status' => 'approved'])->assertSessionHasErrors(['employee_id', 'status']);
        $this->post(route('admin.my-leave.store'), $data)->assertRedirect(route('admin.my-leave.index'));
        $leave = LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->sole();
        $this->assertSame('pending', $leave->status);
        $this->assertEquals(3, $leave->total_days);
        $this->assertNull($leave->approved_by);
        $this->post(route('admin.my-leave.store'), $data)->assertSessionHasErrors('start_date');
        $this->assertSame(1, LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
        $this->get(route('admin.hr.employees.index'))->assertForbidden();
    }

    public function test_unlinked_login_cannot_request_leave_or_read_another_employees_attachment(): void
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $this->actingAs($user)->get(route('admin.my-leave.index'))->assertForbidden();
        $employee = $this->employee(['user_id' => $user->id]);
        $other = $this->employee(['employee_code' => 'SP-OTHER']);
        $type = LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'status' => 'active']);
        $leave = LeaveRequest::create(['employee_id' => $other->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-01', 'total_days' => 1, 'status' => 'pending', 'attachment_path' => 'private.pdf']);
        $this->get(route('admin.my-leave.attachment', $leave->id))->assertNotFound();
    }

    public function test_cleanup_inventory_is_read_only_and_not_a_delete_plan(): void
    {
        $before = [];
        foreach (['users', 'roles', 'projects', 'cost_centers', 'customers', 'supplier_bills', 'journal_entries'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $this->artisan('seera:entry-audit', ['--json' => true])->expectsOutputToContain('"read_only": true')->assertSuccessful();
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->artisan('seera:go-live-reset')->expectsOutputToContain('Dry run only.')->assertSuccessful();
    }

    public function test_document_lengths_fit_existing_mysql_columns_before_inserting(): void
    {
        $data = ['asset_code' => 'BOUNDS-EQ', 'name' => 'Bounded asset', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours'];
        $this->post(route('admin.equipment.store'), $data + ['new_documents' => [['document_type' => 'registration', 'document_number' => str_repeat('A', 81), 'file' => $this->document()['file']]]])->assertSessionHasErrors('new_documents.0.document_number');
        $this->assertDatabaseMissing('equipment_assets', ['asset_code' => 'BOUNDS-EQ']);
        $this->post(route('admin.master.customers.store'), ['name' => 'Long filename', 'type' => 'Company', 'status' => 'active', 'new_documents' => [['file' => UploadedFile::fake()->create(str_repeat('x', 200).'.pdf', 2, 'application/pdf')]]])->assertSessionHasNoErrors();
        $document = Customer::where('name', 'Long filename')->firstOrFail()->documents()->sole();
        $this->assertLessThanOrEqual(191, mb_strlen($document->original_filename));
    }

    public function test_own_leave_rejects_date_ranges_that_would_overflow_the_mysql_day_column(): void
    {
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $employee = $this->employee(['user_id' => $user->id]);
        $type = LeaveType::firstOrCreate(['code' => 'ANNUAL'], ['name' => 'Annual Leave', 'status' => 'active']);
        $this->actingAs($user)->post(route('admin.my-leave.store'), ['leave_type_id' => $type->id, 'start_date' => '1900-01-01', 'end_date' => '2100-01-01'])->assertSessionHasErrors('end_date');
        $this->assertSame(0, LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->count());
    }
}
