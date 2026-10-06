@php
$actor = auth()->user();
$write = $manage && $panel !== 'activity' && (!in_array($panel,['roles','temporary']) || $actor->hasPermission('Roles','process')) && ($panel !== 'employee' || $actor->hasPermission('HR','edit'));
@endphp
<div class="table-card" style="padding:16px">
<h2>{{ __('workspace.'.$panel) }} — {{ $user->name }}</h2>
<div data-panel-status role="status"></div><x-admin.workspace-previous/>
@if(in_array($panel,['roles','temporary']))
    <p class="note">{{ __('workspace.roles_help') }}</p>
    <table><thead><tr>@foreach(['role','parents','assignment','starts','expires','state'] as $label)<th>{{ __('workspace.'.$label) }}</th>@endforeach</tr></thead><tbody>
    @foreach($rows as $role)<tr><td>{{ $role->name }} @if($role->pivot->is_primary)({{ __('workspace.primary') }})@endif
        <details><summary>{{ __('workspace.permissions') }}</summary>@foreach($role->permissions as $permission)<div>{{ $permission->module }} — {{ $permission->action }}</div>@endforeach</details>
    </td><td>{{ collect([$role->parent?->name])->merge($role->additionalParents->pluck('name'))->filter()->join(', ') ?: '-' }}</td>
    <td>{{ __('workspace.'.($role->pivot->is_temporary ? 'temporary' : 'permanent')) }}</td><td>{{ $role->pivot->access_start_date ?: '-' }}</td><td>{{ $role->pivot->access_end_date ?: '-' }}</td><td>{{ __('workspace.'.\App\Support\Workspace\UserWorkspacePanels::state($user,$role)) }}</td></tr>@endforeach
    </tbody></table>
    @if($panel === 'temporary')<p class="note">{{ __('workspace.temporary_help') }}</p>@endif
@elseif($panel === 'employment')
    @foreach(['department','designation','branch'] as $field)<p>{{ __('workspace.'.$field) }}: {{ $user->$field?->name ?: '-' }}</p>@endforeach
    @foreach(['joining_date','contract_type','iqama_number','iqama_expiry_date','employee_classification'] as $field)<p>{{ __('workspace.'.$field) }}: {{ $user->$field ?: '-' }}</p>@endforeach
@elseif($panel === 'employee')
    <x-admin.linked-identity :link="$linkedEmployee" :title="__('ui.linked_employee')"/>
    @if($linkedEmployee['state'] === 'linked')<p>{{ $employee?->employee_code }} — {{ $employee?->name }} — {{ $employee?->email }}</p>@endif
    <p class="note">{{ __('workspace.link_help') }}</p>
@elseif($panel === 'scope')
    <p>{{ __('workspace.effective_scope') }}: {{ $user->effectiveAccessScope() }}</p>
    @foreach(['project','site','warehouse'] as $relation)<p>{{ __('workspace.'.$relation) }}: {{ $user->$relation?->name ?: '-' }}</p>@endforeach
    <p class="note">{{ __('workspace.scope_help') }}</p>
@elseif($panel === 'mobile')
    <p>{{ $user->mobile_access ? __('workspace.yes') : __('workspace.no') }}</p><p class="note">{{ __('workspace.mobile_help') }}</p>
@elseif($panel === 'security')
    <p>{{ __('workspace.status') }}: {{ $user->status }}</p><p>{{ __('workspace.last_login') }}: {{ $user->last_login_at ?: '-' }}</p>
    <p>{{ __('workspace.must_change') }}: {{ $user->must_change_password ? __('workspace.yes') : __('workspace.no') }}</p>
    <p class="note">{{ __('workspace.security_help') }}</p>
@elseif($panel === 'activity')
    <p class="note">{{ __('workspace.activity_help') }}</p>
    <table><thead><tr><th>{{ __('workspace.date') }}</th><th>{{ __('workspace.module') }}</th><th>{{ __('workspace.action') }}</th></tr></thead><tbody>
    @foreach($rows as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->module }}</td><td>{{ $log->action }}</td></tr>@endforeach</tbody></table>
@endif
@if($rows) @include('admin.workspace-pagination', ['rows'=>$rows]) @endif
@if($write)
<form method="POST" action="{{ route('admin.users.workspace.save',[$user,$panel]) }}" data-related-save>
    @csrf<div data-related-errors class="alert danger" hidden></div>
    @if(in_array($panel,['roles','temporary']))
        <label>{{ __('workspace.role') }}</label><select class="select" name="role_id" required><option value="">—</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
        <label>{{ __('workspace.action') }}</label><select class="select" name="operation">
        @foreach($panel === 'roles' ? ['permanent','primary','remove'] : ['temporary','end'] as $operation)<option value="{{ $operation }}">{{ __('workspace.op_'.$operation) }}</option>@endforeach</select>
        @if($panel === 'temporary')<label>{{ __('workspace.starts') }}</label><input class="input" type="date" name="access_start_date"><label>{{ __('workspace.expires') }}</label><input class="input" type="date" name="access_end_date">@endif
    @elseif($panel === 'employment')
        @if($actor->hasPermission('HR','view') && $actor->hasPermission('HR','edit'))
        <label>{{ __('workspace.employee_classification') }}</label><select class="select" name="employee_classification"><option value="">—</option>@foreach(\App\Models\Employee::CLASSIFICATIONS as $classification)<option @selected($user->employee_classification === $classification)>{{ $classification }}</option>@endforeach</select>
        @endif
        @foreach(['department'=>'departments','designation'=>'designations','branch'=>'branches'] as $field=>$collection)
        <label>{{ __('workspace.'.$field) }}</label><select name="{{ $field }}_id" class="select"><option value="">—</option>@foreach($$collection as $item)<option value="{{ $item->id }}" @selected($user->{$field.'_id'} == $item->id)>{{ $item->name }}</option>@endforeach</select>
        @endforeach
        @foreach(['joining_date'=>'date','contract_type'=>'text','iqama_number'=>'text','iqama_expiry_date'=>'date'] as $field=>$type)
        <label>{{ __('workspace.'.$field) }}</label><input class="input" type="{{ $type }}" name="{{ $field }}" value="{{ $type === 'date' ? $user->$field?->format('Y-m-d') : $user->$field }}">
        @endforeach
    @elseif($panel === 'employee')
        <label>{{ __('ui.employee_search') }}</label>
        <input id="employee-search" class="input" autocomplete="off" data-employee-search="{{ route('admin.users.employee-search',['target_user'=>$user->id]) }}" data-empty="{{ __('ui.employee_search_empty') }}" data-error="{{ __('ui.employee_search_error') }}" data-confirm="{{ __('workspace.link_help') }}" data-selected="{{ __('ui.employee_selected') }}" data-cleared="{{ __('ui.employee_cleared') }}" data-view-linked="{{ __('ui.view_linked_record') }}" data-edit-linked="{{ __('ui.edit_linked_record') }}">
        <input type="hidden" name="source_employee_id" value="{{ $employee?->id }}">
        <div id="employee-search-results" aria-live="polite"></div>
        <button type="button" class="btn outline" data-clear-employee>{{ __('ui.clear_employee') }}</button>
        <label>{{ __('workspace.name') }}</label><input class="input" name="name" readonly value="{{ $employee?->name }}">
        <label>{{ __('workspace.email') }}</label><input class="input" name="email" readonly value="{{ $employee?->email }}">
        <select class="select" name="operation"><option value="link">{{ __('workspace.link') }}</option><option value="unlink">{{ __('workspace.unlink') }}</option></select>
    @elseif($panel === 'scope')
        @foreach(['project'=>'projects','site'=>'sites','warehouse'=>'warehouses'] as $field=>$collection)
        <label>{{ __('workspace.'.$field) }}</label><select class="select" name="{{ $field }}_id"><option value="">{{ __('workspace.unassigned') }}</option>@foreach($$collection as $item)<option value="{{ $item->id }}" @selected($user->{$field.'_id'} == $item->id)>{{ $item->code }} — {{ $item->name }}</option>@endforeach</select>
        @endforeach
    @elseif($panel === 'mobile')
        <select class="select" name="mobile_access"><option value="0" @selected(!$user->mobile_access)>{{ __('workspace.no') }}</option><option value="1" @selected($user->mobile_access)>{{ __('workspace.yes') }}</option></select>
    @elseif($panel === 'security')
        <label>{{ __('workspace.status') }}</label><select class="select" name="status">@foreach(['active','inactive','locked','pending'] as $status)<option value="{{ $status }}" @selected($user->status === $status)>{{ __('workspace.'.$status) }}</option>@endforeach</select>
        <label>{{ __('workspace.password') }}</label><input type="password" class="input" name="password" autocomplete="new-password">
        <label>{{ __('workspace.confirm_password') }}</label><input type="password" class="input" name="password_confirmation" autocomplete="new-password">
    @endif
    <x-admin.form-actions :cancel="route('admin.users.index')"/>
</form>
@endif
</div>
