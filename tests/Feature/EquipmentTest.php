<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentDocument;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Equipment & Vehicles Phase 1: asset master, project / site assignment with
 * history, meter rules, private documents, scope, workspace integration and
 * the truthful GPS readiness state.
 */
class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $projectA;

    protected Project $projectB;

    protected Site $siteA;

    protected Site $siteB;

    protected Employee $driver;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->projectA = Project::create(['code' => 'EQ-A', 'name' => 'Riyadh Commercial Tower', 'status' => 'active']);
        $this->projectB = Project::create(['code' => 'EQ-B', 'name' => 'Jeddah Warehouse', 'status' => 'active']);
        $this->siteA = Site::create(['name' => 'Tower Main Site', 'code' => 'EQ-A-S1', 'project_id' => $this->projectA->id, 'status' => 'active']);
        $this->siteB = Site::create(['name' => 'Jeddah Yard', 'code' => 'EQ-B-S1', 'project_id' => $this->projectB->id, 'status' => 'active']);
        $this->driver = Employee::create(['employee_code' => 'EQ-DRV', 'first_name' => 'Saeed', 'last_name' => 'Driver', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->projectA->id]);
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'EQ role '.$suffix, 'code' => 'EQ_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'EQ '.$suffix, 'email' => 'eq-'.$suffix.'@example.test', 'username' => 'eq.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $overrides */
    protected function asset(array $overrides = []): EquipmentAsset
    {
        $code = $overrides['asset_code'] ?? 'EXC-'.str_pad((string) ++$this->seq, 3, '0', STR_PAD_LEFT);
        $this->actingAs($this->admin)->post(route('admin.equipment.store'), $overrides + ['asset_code' => $code, 'name' => 'Excavator '.$code, 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours', 'current_meter' => 1000])->assertSessionHasNoErrors();

        return EquipmentAsset::where('asset_code', strtoupper($code))->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    protected function assign(EquipmentAsset $asset, array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.equipment.assign', $asset), $overrides + ['project_id' => $this->projectA->id, 'site_id' => $this->siteA->id, 'operator_employee_id' => $this->driver->id, 'assigned_from' => '2026-10-01']);
    }

    // ---------------------------------------------------------------- master

    public function test_create_view_edit_unique_code_and_vehicle_fields(): void
    {
        $this->actingAs($this->admin)->post(route('admin.equipment.store'), ['asset_code' => 'trk-001', 'name' => 'Hilux pickup', 'asset_type' => 'vehicle', 'ownership_type' => 'owned', 'meter_type' => 'odometer', 'current_meter' => 25000,
            'registration_number' => 'RUA 1234', 'chassis_number' => 'MR0FZ22G800123456', 'registration_expiry_date' => today()->addDays(20)->toDateString(), 'insurance_expiry_date' => today()->subDay()->toDateString(), 'make' => 'Toyota', 'model' => 'Hilux', 'year' => 2024])->assertSessionHasNoErrors();
        $truck = EquipmentAsset::where('asset_code', 'TRK-001')->firstOrFail();
        $this->assertSame('vehicle', $truck->asset_type);
        $this->assertSame('available', $truck->status);
        $this->assertSame('RUA 1234', $truck->registration_number);
        $this->assertSame($this->admin->id, $truck->created_by);

        $this->actingAs($this->admin)->post(route('admin.equipment.store'), ['asset_code' => 'TRK-001', 'name' => 'Duplicate', 'asset_type' => 'vehicle', 'ownership_type' => 'owned', 'meter_type' => 'odometer'])->assertSessionHasErrors('asset_code');
        $this->actingAs($this->admin)->post(route('admin.equipment.store'), ['asset_code' => 'bad code!', 'name' => 'x', 'asset_type' => 'vehicle', 'ownership_type' => 'owned', 'meter_type' => 'odometer'])->assertSessionHasErrors('asset_code');
        $this->assertSame(1, EquipmentAsset::where('asset_code', 'TRK-001')->count());

        $page = $this->actingAs($this->admin)->get(route('admin.equipment.show', $truck))->assertOk();
        $page->assertSee('TRK-001')->assertSee('RUA 1234')->assertSee('Document expired')->assertSee('25,000.0 km')->assertSee('Not assigned')->assertSee('Live GPS tracking is not connected to a telematics provider');
        $this->actingAs($this->admin)->get(route('admin.equipment.index'))->assertOk()->assertSee('TRK-001')->assertSee('Hilux pickup');
        $this->actingAs($this->admin)->get(route('admin.equipment.edit', $truck))->assertOk()->assertSee('Registration / plate number');

        // Edit: equipment type drops vehicle-only fields; status Assigned cannot be set by hand; lower meter needs a correction.
        $this->actingAs($this->admin)->put(route('admin.equipment.update', $truck), ['asset_code' => 'TRK-001', 'name' => 'Hilux pickup', 'asset_type' => 'vehicle', 'ownership_type' => 'rented', 'meter_type' => 'odometer', 'status' => 'assigned', 'current_meter' => 25000])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->put(route('admin.equipment.update', $truck), ['asset_code' => 'TRK-001', 'name' => 'Hilux pickup', 'asset_type' => 'vehicle', 'ownership_type' => 'rented', 'meter_type' => 'odometer', 'status' => 'available', 'current_meter' => 24000])->assertSessionHasErrors('meter_reading');
        $this->assertEqualsWithDelta(25000, (float) $truck->fresh()->current_meter, 0.01, 'meter never silently decreases');
        $this->actingAs($this->admin)->put(route('admin.equipment.update', $truck), ['asset_code' => 'TRK-001', 'name' => 'Hilux pickup', 'asset_type' => 'vehicle', 'ownership_type' => 'rented', 'meter_type' => 'odometer', 'status' => 'available', 'current_meter' => 24000, 'meter_correction' => 1])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(24000, (float) $truck->fresh()->current_meter, 0.01);
        $this->assertTrue(ActivityLog::where('module', 'Equipment')->where('action', 'Corrected equipment meter')->exists());
        $this->actingAs($this->admin)->put(route('admin.equipment.update', $truck), ['asset_code' => 'TRK-001', 'name' => 'Generator', 'asset_type' => 'equipment', 'ownership_type' => 'owned', 'meter_type' => 'hours', 'status' => 'available', 'registration_number' => 'KEEP?'])->assertSessionHasNoErrors();
        $this->assertNull($truck->fresh()->registration_number, 'plate is a vehicle-only field');
        $this->assertSame('owned', $truck->fresh()->ownership_type);

        $csv = $this->actingAs($this->admin)->get(route('admin.equipment.export'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('TRK-001', $csv);
        $this->assertStringContainsString('Not connected', $csv);
        $this->actingAs($this->user(['Equipment' => ['view']], suffix: 'noexport'))->get(route('admin.equipment.export'))->assertForbidden();
    }

    // ---------------------------------------------------------------- assignment

    public function test_assignment_validates_relations_keeps_history_and_one_active_assignment(): void
    {
        $asset = $this->asset();
        $this->assign($asset, ['site_id' => $this->siteB->id])->assertSessionHasErrors('site_id');
        $this->assign($asset, ['site_id' => 999999])->assertSessionHasErrors('site_id');
        $this->assign($asset, ['project_id' => 999999])->assertSessionHasErrors('project_id');
        $this->driver->update(['status' => 'inactive']);
        $this->assign($asset)->assertSessionHasErrors('operator_employee_id');
        $this->driver->update(['status' => 'active']);
        $this->assign($asset, ['operator_employee_id' => 999999])->assertSessionHasErrors('operator_employee_id');
        $this->assign($asset, ['assigned_from' => today()->addDay()->toDateString()])->assertSessionHasErrors('assigned_from');
        $this->assertSame(0, EquipmentAssignment::count());

        $this->assign($asset, ['meter_reading' => 1010])->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertSame('assigned', $asset->status);
        $this->assertSame($this->projectA->id, $asset->current_project_id);
        $this->assertSame($this->siteA->id, $asset->current_site_id);
        $this->assertSame($this->driver->id, $asset->current_operator_id);
        $this->assertEqualsWithDelta(1010, (float) $asset->current_meter, 0.01);
        $first = $asset->activeAssignment;
        $this->assertNotNull($first);
        $this->assertSame($first->id, $asset->current_assignment_id);
        $this->assertEqualsWithDelta(1010, (float) $first->start_meter, 0.01);
        $this->actingAs($this->admin)->get(route('admin.equipment.show', $asset))->assertOk()->assertSee('EQ-A')->assertSee('Tower Main Site')->assertSee('Saeed Driver')->assertSee('Reassign');

        // Reassign to project B: the first assignment is closed and kept; exactly one active remains.
        $this->assign($asset, ['project_id' => $this->projectB->id, 'site_id' => $this->siteB->id, 'operator_employee_id' => null, 'assigned_from' => '2026-10-05', 'meter_reading' => 1050])->assertSessionHasNoErrors();
        $asset->refresh();
        $first->refresh();
        $this->assertFalse($first->is_active);
        $this->assertSame('2026-10-05', $first->assigned_until->toDateString());
        $this->assertEqualsWithDelta(1050, (float) $first->end_meter, 0.01);
        $this->assertSame($this->admin->id, $first->closed_by);
        $this->assertSame(1, EquipmentAssignment::where('equipment_asset_id', $asset->id)->where('is_active', true)->count());
        $this->assertSame(2, EquipmentAssignment::where('equipment_asset_id', $asset->id)->count());
        $this->assertSame($this->projectB->id, $asset->current_project_id);
        $this->assertNull($asset->current_operator_id);

        // Release: history retained, asset available again.
        $this->actingAs($this->admin)->post(route('admin.equipment.release', $asset), ['assigned_until' => '2026-10-08'])->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertSame('available', $asset->status);
        $this->assertNull($asset->current_project_id);
        $this->assertNull($asset->activeAssignment);
        $this->assertSame(2, EquipmentAssignment::where('equipment_asset_id', $asset->id)->count());
        $this->actingAs($this->admin)->post(route('admin.equipment.release', $asset))->assertSessionHasErrors('equipment');
        $this->actingAs($this->admin)->get(route('admin.equipment.show', $asset))->assertOk()->assertSee('Closed')->assertSee('EQ-B');

        // A retired asset cannot be assigned; a viewer without process cannot assign.
        $asset->update(['status' => 'retired']);
        $this->assign($asset)->assertSessionHasErrors('equipment');
        $asset->update(['status' => 'available']);
        $this->assign($asset, [], $this->user(['Equipment' => ['view', 'create', 'edit']], suffix: 'noprocess'))->assertForbidden();
        foreach (['Assigned equipment', 'Released equipment'] as $action) {
            $this->assertTrue(ActivityLog::where('module', 'Equipment')->where('action', $action)->where('description', 'like', '[Equipment #'.$asset->id.']%')->exists(), $action);
        }
    }

    public function test_project_scoped_users_only_see_assets_of_their_projects(): void
    {
        $mine = $this->asset(['asset_code' => 'EXC-MINE']);
        $theirs = $this->asset(['asset_code' => 'EXC-THEIRS']);
        $free = $this->asset(['asset_code' => 'EXC-FREE']);
        $this->assign($mine)->assertSessionHasNoErrors();
        $this->assign($theirs, ['project_id' => $this->projectB->id, 'site_id' => $this->siteB->id, 'operator_employee_id' => null])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.equipment.documents.store', $theirs), ['document_type' => 'insurance', 'file' => UploadedFile::fake()->create('ins.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $document = $theirs->documents()->firstOrFail();

        $scoped = $this->user(['Equipment' => ['view', 'create', 'edit', 'process', 'post', 'export']], $this->projectA, 'scoped');
        $this->actingAs($scoped)->get(route('admin.equipment.index'))->assertOk()->assertSee('EXC-MINE')->assertDontSee('EXC-THEIRS')->assertDontSee('EXC-FREE');
        $this->actingAs($scoped)->get(route('admin.equipment.show', $mine))->assertOk();
        $this->actingAs($scoped)->get(route('admin.equipment.show', $theirs))->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.equipment.show', $free))->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.equipment.edit', $theirs))->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.equipment.documents.file', [$theirs, $document->id]))->assertNotFound();
        $this->actingAs($scoped)->post(route('admin.equipment.assign', $theirs), ['project_id' => $this->projectA->id])->assertNotFound();
        $this->actingAs($scoped)->post(route('admin.equipment.fuel.store', $theirs), ['fuel_date' => today()->toDateString(), 'quantity' => 1, 'rate' => 1, 'payment_type' => 'Cash', 'expense_category_id' => 1])->assertNotFound();
        // Within scope the user cannot move an asset to a project outside their scope.
        $this->actingAs($scoped)->post(route('admin.equipment.assign', $mine), ['project_id' => $this->projectB->id])->assertForbidden();
        $this->assertSame($this->projectA->id, $mine->fresh()->current_project_id);
        $this->assertStringNotContainsString('EXC-THEIRS', $this->actingAs($scoped)->get(route('admin.equipment.export'))->streamedContent());
    }

    // ---------------------------------------------------------------- documents, GPS, workspace

    public function test_documents_are_private_authorized_and_drive_expiry_warnings(): void
    {
        $asset = $this->asset(['asset_type' => 'vehicle', 'registration_number' => 'ABC 1'])->fresh();
        $this->actingAs($this->admin)->post(route('admin.equipment.documents.store', $asset), ['document_type' => 'registration', 'document_number' => 'REG-9', 'expiry_date' => today()->subDays(3)->toDateString(), 'file' => UploadedFile::fake()->create('registration.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.equipment.documents.store', $asset), ['document_type' => 'insurance', 'expiry_date' => today()->addDays(10)->toDateString(), 'file' => UploadedFile::fake()->image('insurance.png')])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.equipment.documents.store', $asset), ['document_type' => 'other', 'file' => UploadedFile::fake()->create('virus.exe', 5, 'application/octet-stream')])->assertSessionHasErrors('file');
        $this->assertSame(2, EquipmentDocument::where('equipment_asset_id', $asset->id)->count());
        $asset->refresh();
        $this->assertSame(today()->subDays(3)->toDateString(), $asset->registration_expiry_date->toDateString(), 'latest registration document sets the expiry');
        $this->assertSame(today()->addDays(10)->toDateString(), $asset->insurance_expiry_date->toDateString());
        $page = $this->actingAs($this->admin)->get(route('admin.equipment.show', $asset))->assertOk();
        $page->assertSee('Document expired')->assertSee('registration.pdf')->assertSee('expiring');

        $registration = $asset->documents()->where('document_type', 'registration')->firstOrFail();
        Storage::disk('local')->assertExists($registration->getAttributes()['path']);
        $this->assertStringNotContainsString('/storage/', $page->getContent(), 'no public storage URL');
        $file = $this->actingAs($this->admin)->get(route('admin.equipment.documents.file', [$asset, $registration->id]))->assertOk();
        $this->assertSame('nosniff', $file->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $file->headers->get('Content-Security-Policy'));
        $this->actingAs($this->user(['HR' => ['view']], suffix: 'hr'))->get(route('admin.equipment.documents.file', [$asset, $registration->id]))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.equipment.documents.file', [$asset, $registration->id]))->assertRedirect(route('login'));
        $other = $this->asset();
        $this->actingAs($this->admin)->get(route('admin.equipment.documents.file', [$other, $registration->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.equipment.index'))->assertOk()->assertSee('Registration / insurance expiring (60 days)');
    }

    public function test_view_is_read_only_paged_and_gps_section_makes_no_live_claim(): void
    {
        $asset = $this->asset(['tracker_provider' => 'Some Provider', 'tracker_device_id' => 'IMEI-123']);
        $this->assign($asset)->assertSessionHasNoErrors();
        foreach (range(1, 12) as $i) {
            $asset->fuelLogs()->create(['fuel_number' => 'FUEL-T-'.$i, 'fuel_date' => '2026-10-0'.(($i % 9) + 1), 'quantity' => 10, 'unit' => 'litre', 'rate' => 2, 'taxable_amount' => 20, 'total_amount' => 20, 'payment_type' => 'Cash', 'created_by' => $this->admin->id, 'project_id' => $this->projectA->id]);
        }
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $page = $this->actingAs($this->admin)->get(route('admin.equipment.show', $asset))->assertOk();
        $this->assertSame([], $writes, 'GET view performs no writes');
        $this->assertSame(10, $page->viewData('fuelLogs')->count());
        $this->assertSame(12, $page->viewData('fuelLogs')->total());
        $this->assertSame(2, $this->actingAs($this->admin)->get(route('admin.equipment.show', [$asset, 'page_fuel' => 2]))->assertOk()->viewData('fuelLogs')->count());
        $page->assertSee('Live GPS tracking is not connected to a telematics provider')->assertSee('IMEI-123')->assertSee('not polled')->assertDontSee('Last ping')->assertDontSee('Idle minutes');
        foreach (['latitude', 'longitude', 'speed_kmh'] as $fake) {
            $this->assertStringNotContainsString($fake, strtolower($page->getContent()));
        }
        $this->actingAs($this->user(['Equipment' => ['view']], suffix: 'noact'))->get(route('admin.equipment.show', $asset))->assertOk()->assertDontSee('id="activity"', false)->assertSee('Cost figures are shown to users with Financial Reports view');
        $this->actingAs($this->admin)->get(route('admin.equipment.show', $asset))->assertOk()->assertSee('id="activity"', false)->assertSee('Assigned equipment');
    }

    public function test_project_and_site_workspaces_show_assigned_equipment_with_permission(): void
    {
        $asset = $this->asset(['asset_code' => 'CRN-001', 'name' => 'Tower crane']);
        $this->assign($asset)->assertSessionHasNoErrors();
        $html = $this->actingAs($this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->projectA, 'equipment']))->assertOk()->json('html');
        $this->assertStringContainsString('CRN-001', $html);
        $this->assertStringContainsString('Tower Main Site', $html);
        $this->assertStringContainsString('View Equipment', $html);
        $this->assertStringContainsString('Live GPS tracking is not connected', $html);
        $this->actingAs($this->admin)->get(route('admin.master.projects.show', $this->projectA))->assertOk()->assertSee('Equipment');
        $empty = $this->actingAs($this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->projectB, 'equipment']))->assertOk()->json('html');
        $this->assertStringContainsString('No equipment is currently assigned to this project', $empty);
        $this->assertStringNotContainsString('CRN-001', $empty);
        $siteHtml = $this->actingAs($this->admin)->getJson(route('admin.master.sites.workspace.panel', [$this->siteA, 'equipment']))->assertOk()->json('html');
        $this->assertStringContainsString('CRN-001', $siteHtml);
        $this->assertStringContainsString('Saeed Driver', $siteHtml);
        $noEquipment = $this->user(['Projects' => ['view'], 'Sites' => ['view']], suffix: 'noeq');
        $this->actingAs($noEquipment)->getJson(route('admin.master.projects.workspace.panel', [$this->projectA, 'equipment']))->assertForbidden();
        $this->actingAs($noEquipment)->getJson(route('admin.master.sites.workspace.panel', [$this->siteA, 'equipment']))->assertForbidden();
        $this->actingAs($noEquipment)->get(route('admin.master.projects.show', $this->projectA))->assertOk()->assertDontSee('data-workspace-related="equipment"', false);
    }
}
