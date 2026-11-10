<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Notifications\ApprovalActionNotification;
use App\Services\Notifications\NotificationAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    public function test_personal_deny_hides_existing_notification_without_changing_shared_role(): void
    {
        $notice = $this->notice();
        $this->actor->permissionOverrides()->create(['permission_id' => Permission::where('module', 'Purchase Requests')->where('action', 'view')->firstOrFail()->id, 'effect' => 'deny', 'reason' => 'Restricted assignment']);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('PRIVATE-NOTICE-A');
        $this->post(route('admin.notifications.read', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->assertTrue($this->other->fresh()->hasPermission('Purchase Requests', 'view'));
    }

    use RefreshDatabase;

    private User $actor;
    private User $other;
    private Role $role;
    private PurchaseRequest $document;

    protected function setUp(): void
    {
        parent::setUp();
        $project = Project::create(['code' => 'NOTICE-A', 'name' => 'Notice project', 'status' => 'active']);
        $this->role = Role::create(['name' => 'Notice viewer', 'code' => 'NOTICE_VIEWER', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $this->role->permissions()->attach(Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => 'view']));
        $this->actor = User::factory()->create(['status' => 'active', 'language' => 'English', 'project_id' => $project->id]);
        $this->other = User::factory()->create(['status' => 'active', 'language' => 'English', 'project_id' => $project->id]);
        foreach ([$this->actor, $this->other] as $user) $user->roles()->attach($this->role, ['is_primary' => true]);
        $this->document = PurchaseRequest::create(['pr_number' => 'PRIVATE-NOTICE-A', 'requested_by' => $this->actor->id, 'request_date' => today(), 'project_id' => $project->id, 'priority' => 'normal', 'status' => 'draft']);
    }

    private function notice(?User $user = null, array $data = [], int $age = 0): DatabaseNotification
    {
        return ($user ?? $this->actor)->notifications()->create(['id' => (string) Str::uuid(), 'type' => ApprovalActionNotification::class,
            'data' => $data + ['event' => 'requested', 'module' => 'Purchase Requests', 'reference' => $this->document->pr_number,
                'source_type' => 'purchase_request', 'source_id' => $this->document->id, 'approval_instance_id' => 1],
            'created_at' => now()->subSeconds($age), 'updated_at' => now()->subSeconds($age)]);
    }

    public function test_own_notification_reads_are_idempotent_and_foreign_ids_are_not_accessible(): void
    {
        $mine = $this->notice(); $foreign = $this->notice($this->other, ['reference' => 'FOREIGN-PRIVATE']);
        $this->actingAs($this->actor)->get(route('admin.notifications.index'))->assertOk()->assertSee('PRIVATE-NOTICE-A')->assertDontSee('FOREIGN-PRIVATE');
        $this->post(route('admin.notifications.read', $foreign->id))->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $this->post(route('admin.notifications.read', $mine->id))->assertRedirect(route('admin.notifications.index'));
        $readAt = $mine->fresh()->read_at;
        $this->post(route('admin.notifications.read', $mine->id))->assertRedirect();
        $this->assertEquals($readAt, $mine->fresh()->read_at);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_permission_or_role_removal_hides_notices_and_refuses_direct_read(): void
    {
        $notice = $this->notice();
        $this->role->permissions()->detach();
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('PRIVATE-NOTICE-A');
        $this->post(route('admin.notifications.read', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->role->permissions()->attach(Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => 'view']));
        $this->role->update(['status' => 'inactive']);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('PRIVATE-NOTICE-A');
    }

    public function test_project_and_site_changes_hide_old_references_in_bell_and_inbox(): void
    {
        $notice = $this->notice();
        $foreign = Project::create(['code' => 'NOTICE-B', 'name' => 'Other project', 'status' => 'active']);
        $this->role->update(['access_scope' => 'Project Level']);
        $this->actor->update(['project_id' => $foreign->id]);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('PRIVATE-NOTICE-A');
        $this->post(route('admin.notifications.read', $notice->id))->assertNotFound();
        $siteA = Site::create(['code' => 'NS-A', 'name' => 'Site A', 'project_id' => $this->document->project_id, 'status' => 'active']);
        $siteB = Site::create(['code' => 'NS-B', 'name' => 'Site B', 'project_id' => $this->document->project_id, 'status' => 'active']);
        $this->document->update(['site_id' => $siteA->id]);
        $this->actor->update(['project_id' => $this->document->project_id, 'site_id' => $siteB->id]);
        $this->role->update(['access_scope' => 'Site Level']);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertDontSee('PRIVATE-NOTICE-A');
        $this->actor->update(['site_id' => $siteA->id]);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertSee('PRIVATE-NOTICE-A');
    }

    public function test_mark_all_only_changes_currently_accessible_notifications_of_the_actor(): void
    {
        $mine = $this->notice(); $foreign = $this->notice($this->other);
        $hidden = $this->notice(data: ['source_id' => 999999, 'reference' => 'DELETED-NOTICE']);
        $this->actingAs($this->actor)->post(route('admin.notifications.read-all'))->assertRedirect(route('admin.notifications.index'));
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNull($hidden->fresh()->read_at);
        $this->post(route('admin.notifications.read-all'))->assertRedirect();
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_unread_count_is_not_truncated_at_one_hundred(): void
    {
        for ($i = 0; $i < 101; $i++) $this->notice(age: $i);
        $this->assertSame(101, app(NotificationAccess::class)->unreadCount($this->actor));
        $this->actingAs($this->actor)->post(route('admin.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, app(NotificationAccess::class)->unreadCount($this->actor->fresh()));
    }

    public function test_recent_bell_can_find_an_accessible_notice_behind_one_hundred_hidden_rows(): void
    {
        $visible = $this->notice(age: 200);
        for ($i = 0; $i < 100; $i++) $this->notice(data: ['source_id' => 999999], age: $i);
        $this->assertSame([$visible->id], app(NotificationAccess::class)->recent($this->actor)->pluck('id')->all());
    }

    public function test_access_service_rejects_other_recipients_even_if_document_is_visible(): void
    {
        $foreign = $this->notice($this->other);
        $this->assertFalse(app(NotificationAccess::class)->visible($foreign, $this->actor));
    }

    public function test_malformed_and_deleted_sources_are_hidden_and_references_are_escaped(): void
    {
        foreach ([['source_type' => 'unknown'], ['event' => 'unknown'], ['module' => 'Other'], ['source_id' => 'not-an-id'], ['source_id' => 999999]] as $data) {
            $notice = $this->notice(data: $data + ['reference' => 'MALFORMED-SECRET']);
            $this->assertFalse(app(NotificationAccess::class)->visible($notice, $this->actor));
        }
        $this->notice(data: ['reference' => '<script>private()</script>']);
        $this->actingAs($this->actor)->get(route('admin.notifications.index'))->assertOk()->assertDontSee('MALFORMED-SECRET')
            ->assertSee('&lt;script&gt;private()&lt;/script&gt;', false)->assertDontSee('<script>private()</script>', false);
    }

    public function test_pagination_and_both_locales_keep_the_same_notices_and_read_actions(): void
    {
        for ($i = 0; $i < 21; $i++) $this->notice(data: ['reference' => 'PAGED-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], age: $i);
        $this->actingAs($this->actor)->get(route('admin.notifications.index'))->assertOk()->assertSee('Mark all visible as read')->assertSee('PAGED-00')->assertDontSee('PAGED-20');
        $this->get(route('admin.notifications.index', ['page' => 2]))->assertOk()->assertSee('PAGED-20');
        $this->actor->update(['language' => 'Arabic']);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertOk()->assertSee('الإشعارات')->assertSee('تحديد كمقروء')->assertSee('PAGED-00');
    }

    public function test_guest_inactive_and_password_change_restricted_accounts_cannot_use_notification_routes(): void
    {
        $notice = $this->notice();
        $this->get(route('admin.notifications.index'))->assertRedirect(route('login'));
        $this->post(route('admin.notifications.read', $notice->id))->assertRedirect(route('login'));
        $this->post(route('admin.notifications.read-all'))->assertRedirect(route('login'));
        $this->actor->update(['status' => 'inactive']);
        $this->actingAs($this->actor)->get(route('admin.notifications.index'))->assertRedirect(route('login'));
        $this->actor->update(['status' => 'active', 'must_change_password' => true]);
        $this->actingAs($this->actor->fresh())->get(route('admin.notifications.index'))->assertRedirect(route('admin.password.change'));
    }
}
