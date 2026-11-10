<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Support\LinkedIdentityNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPermissionOverrideController extends Controller
{
    public static function revision(User $user): string
    {
        return hash('sha256', json_encode($user->permissionOverrides()->orderBy('permission_id')->get(['permission_id', 'effect', 'reason'])->toArray(), JSON_THROW_ON_ERROR));
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($actor->isSuperAdmin() && app(LinkedIdentityNavigation::class)->canUser($actor, $user, 'edit'), 403);
        $data = $request->validate([
            'settings' => ['required', 'array', 'max:750'], 'settings.*' => ['required', 'in:inherit,allow,deny'],
            'reason' => ['required', 'string', 'max:1000'], 'revision' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $ids = array_keys($data['settings']);
        if (count(array_filter($ids, fn ($id) => ctype_digit((string) $id))) !== count($ids)
            || Permission::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['settings' => __('feedback.invalid_permission')]);
        }
        DB::transaction(function () use ($request, $actor, $user, $data) {
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $currentActor = $actor->fresh();
            abort_unless($currentActor->isSuperAdmin() && app(LinkedIdentityNavigation::class)->canUser($currentActor, $target, 'edit'), 403);
            if (! hash_equals(self::revision($target), $data['revision'])) {
                throw ValidationException::withMessages(['settings' => __('feedback.permissions_changed')]);
            }
            $existingRows = $target->permissionOverrides()->orderBy('permission_id')->get()->keyBy('permission_id');
            $before = $existingRows->map(fn ($row) => $row->only(['permission_id', 'effect', 'reason']))->values()->all();
            foreach ($data['settings'] as $permissionId => $effect) {
                if ($effect === 'inherit') {
                    if ($existingRows->has($permissionId)) {
                        $target->permissionOverrides()->where('permission_id', $permissionId)->delete();
                    }
                } else {
                    $existing = $existingRows->get($permissionId);
                    if (! $existing || $existing->effect !== $effect) {
                        $target->permissionOverrides()->updateOrCreate(['permission_id' => $permissionId], ['effect' => $effect, 'reason' => $data['reason'], 'changed_by' => $actor->id]);
                    }
                }
            }
            $after = $target->permissionOverrides()->orderBy('permission_id')->get(['permission_id', 'effect', 'reason'])->toArray();
            if ($before !== $after) {
                ActivityLog::record($request, 'Users', 'Updated individual permissions', '[User #'.$target->id.'] '.$data['reason'])
                    ->update(['old_value' => json_encode($before, JSON_THROW_ON_ERROR), 'new_value' => json_encode($after, JSON_THROW_ON_ERROR)]);
            }
            if ($actor->id === $target->id) {
                $actor->forgetPermissionOverrides();
            }
        });

        return redirect()->to(route('admin.users.edit', $user).'#individual-permissions')->with('status', __('feedback.permissions_saved'));
    }
}
