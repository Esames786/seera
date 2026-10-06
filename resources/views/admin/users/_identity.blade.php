@php $actor = auth()->user(); @endphp
    <h2>{{ $user->name }}</h2><p>{{ $user->email }} · {{ $user->employee_id ?: '-' }} · {{ $user->status }}</p>
    <p>{{ __('workspace.primary') }}: {{ $user->primaryRole()?->name ?: '-' }} · {{ __('workspace.mobile') }}: {{ $user->mobile_access ? __('workspace.yes') : __('workspace.no') }}</p>
    <p>{{ __('workspace.current_roles') }}: {{ $user->roles->filter(fn($role) => $user->hasEffectiveRole($role->id))->pluck('name')->join(', ') ?: '-' }}</p>
    <p>{{ __('workspace.temporary') }}: {{ $user->roles->filter(fn($role) => $role->pivot->is_temporary && $user->hasEffectiveRole($role->id))->pluck('name')->join(', ') ?: '-' }}</p>
    <x-admin.linked-identity :link="$linkedEmployee" :title="__('ui.linked_employee')"/>
    <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.users.index')) }}">{{ __('workspace.back_users') }}</a>
    @if(!$manage && $actor->hasPermission('Users','edit'))<a class="btn primary" href="{{ route('admin.users.edit', [$user, 'return_to' => \App\Support\SaveAction::returnTo()]) }}">{{ __('workspace.manage') }}</a>@endif
    @if($manage && $actor->hasPermission('Users','view'))<a class="btn outline" href="{{ route('admin.users.show',$user) }}">{{ __('workspace.view') }}</a>@endif
