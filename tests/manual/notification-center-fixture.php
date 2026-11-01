<?php

// Disposable local fixture only. This file must never be served as a web endpoint.
require __DIR__.'/../../vendor/autoload.php';
$root = realpath($argv[1] ?? '');
if (PHP_SAPI !== 'cli' || ! $root || ! preg_match('/^seera-notifications-qa-[a-f0-9]{32}$/', basename($root))) {
    throw new RuntimeException('Expected a uniquely named disposable notification fixture.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($root.'/uat.sqlite')
    || config('zatca.production_enabled')) throw new RuntimeException('Unsafe fixture environment');

if (isset($argv[2])) {
    $facts = json_decode(file_get_contents($root.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    $actor = App\Models\User::findOrFail($facts['users']['first']['id']);
    $role = $actor->roles()->firstOrFail();
    $permission = App\Models\Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => 'view']);
    if ($argv[2] === 'revoke') {
        $role->permissions()->detach($permission->id);
    } elseif ($argv[2] === 'scope') {
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $outside = App\Models\Project::create(['code' => 'NQA-OUT', 'name' => 'Other notification project', 'status' => 'active']);
        $actor->update(['project_id' => $outside->id]);
    } else throw new RuntimeException('Unsupported fixture operation');
    echo "Synthetic fixture access changed.\n";
    exit;
}

$project = App\Models\Project::create(['code' => 'NQA-A', 'name' => 'Notification project', 'status' => 'active']);
$users = [];
foreach (['requester' => ['view', 'create', 'edit'], 'first' => ['view', 'approve', 'reject'], 'second' => ['view', 'approve', 'reject']] as $name => $actions) {
    $role = App\Models\Role::create(['name' => 'Notification QA '.$name, 'code' => 'NQA_'.strtoupper($name), 'level' => 4, 'access_scope' => 'Project Level', 'status' => 'active']);
    foreach ($actions as $action) $role->permissions()->attach(App\Models\Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => $action]));
    $user = App\Models\User::create(['name' => 'Notification QA '.$name, 'email' => 'nqa.'.$name.'@example.test', 'password' => Illuminate\Support\Facades\Hash::make('password'), 'status' => 'active', 'language' => 'English', 'project_id' => $project->id, 'must_change_password' => false, 'password_changed_at' => now()]);
    $user->roles()->attach($role, ['is_primary' => true]); $users[$name] = $user->fresh();
}
$pr = App\Models\PurchaseRequest::create(['pr_number' => 'NQA-PR', 'request_date' => today(), 'requested_by' => $users['requester']->id, 'project_id' => $project->id, 'priority' => 'normal', 'status' => 'draft', 'estimated_total' => 20]);
$pr->forceFill(['approval_mode' => 'runtime'])->save();
$pr->lines()->create(['item_id' => App\Models\Item::firstOrFail()->id, 'quantity' => 2, 'estimated_unit_cost' => 10, 'estimated_total' => 20]);
$workflow = App\Models\ApprovalWorkflow::create(['name' => 'Notification QA approvals', 'module' => 'Purchase Request', 'trigger_action' => 'Request Created', 'scope' => 'All Projects', 'auto_posting' => 'No Auto Posting', 'status' => 'active']);
foreach (['first', 'second'] as $i => $name) $workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $users[$name]->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
$instance = app(App\Services\Approvals\ApprovalRuntimeService::class)->start(app(App\Services\Approvals\PurchaseRequestApprovalSubject::class), $pr->id, $workflow->id, $users['requester']);
for ($i = 0; $i < 12; $i++) {
    $users['first']->notify(new App\Notifications\ApprovalActionNotification('requested', 'Purchase Requests', $i === 11 ? str_repeat('LONG-REFERENCE-', 18) : 'NQA-HISTORY-'.$i, 'purchase_request', $pr->id, $instance->id));
}
$users['requester']->notify(new App\Notifications\ApprovalActionNotification('requested', 'Purchase Requests', 'NQA-FOREIGN-ID', 'purchase_request', $pr->id, $instance->id));
$foreign = $users['requester']->notifications()->latest()->first()->id;
$data = ['users' => array_map(fn ($u) => ['id' => $u->id, 'email' => $u->email], $users), 'project_id' => $project->id,
    'document_id' => $pr->id, 'workflow_id' => $workflow->id, 'instance_id' => $instance->id, 'steps' => $instance->steps->pluck('id')->all(), 'foreign_notification_id' => $foreign];
file_put_contents($root.'/fixture.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Notification fixture ready; synthetic data only.\n";
