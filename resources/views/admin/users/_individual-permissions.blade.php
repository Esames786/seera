@php
    $overrides = $user->permissionOverrides()->get()->keyBy('permission_id');
    $groups = \App\Models\Permission::orderBy('module')->orderBy('id')->get()->groupBy('module');
    $inherited = $user->roles->filter(fn($role) => $user->hasEffectiveRole($role->id))->flatMap(fn($role) => $role->permissions->pluck('id'))->unique();
@endphp
<details class="card" id="individual-permissions" style="padding:16px;margin-block:16px">
    <summary style="cursor:pointer"><strong>{{ __('feedback.individual_permissions') }}</strong></summary>
    <p>{{ __('feedback.permission_help') }}</p>
    <form method="POST" action="{{ route('admin.users.permission-overrides.update', $user) }}">
        @csrf @method('PUT')
        <input type="hidden" name="revision" value="{{ \App\Http\Controllers\Admin\UserPermissionOverrideController::revision($user) }}"/>
        @foreach($groups as $module => $permissions)
            <details style="border-block-start:1px solid #dfe5ee;padding-block:10px">
                <summary>{{ __($module) }} ({{ $permissions->filter(fn($permission) => $overrides->has($permission->id))->count() }})</summary>
                <div class="form-grid three" style="padding-block:10px">
                    @foreach($permissions as $permission)
                        <div><label for="override-{{ $permission->id }}">{{ __(\App\Models\Permission::label($permission->action)) }}</label>
                            <select class="select" id="override-{{ $permission->id }}" name="settings[{{ $permission->id }}]">
                                @foreach(['inherit','allow','deny'] as $effect)<option value="{{ $effect }}" @selected(old('settings.'.$permission->id, $overrides->get($permission->id)?->effect ?? 'inherit') === $effect)>{{ __('feedback.'.$effect) }}</option>@endforeach
                            </select>
                            <span class="small">{{ __('feedback.role_default') }}: {{ $inherited->contains($permission->id) ? __('feedback.allow') : __('feedback.deny') }} · {{ __('feedback.effective') }}: {{ $user->hasPermission($permission->module, $permission->action) ? __('feedback.allow') : __('feedback.deny') }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
        <label for="override-reason">{{ __('feedback.override_reason') }} *</label>
        <textarea class="textarea" id="override-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>
        <div class="form-actions"><button class="btn primary" type="submit">{{ __('feedback.save_permissions') }}</button></div>
    </form>
</details>
