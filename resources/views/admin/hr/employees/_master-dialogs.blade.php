{{-- Render once with the full page: scripts/modals must not rely on AJAX HTML stacks. --}}
<x-admin.quick-create id="employee-master-shift" target-selector="[data-employee-master=shift]" :url="route('admin.hr.shifts.store')" title="New Shift" permission="HR" :submit="__('Save & select')" :render-trigger="false">
    <div><label for="ews-shift-name">{{ __('Shift Name') }} *</label><input id="ews-shift-name" name="name" class="input" maxlength="100" required/></div>
    <div><label for="ews-shift-code">{{ __('Shift Code') }} *</label><input id="ews-shift-code" name="code" class="input" maxlength="50" required/></div>
    <div><label for="ews-shift-start">{{ __('Start Time') }} *</label><input id="ews-shift-start" name="start_time" type="time" class="input" required/></div>
    <div><label for="ews-shift-end">{{ __('End Time') }} *</label><input id="ews-shift-end" name="end_time" type="time" class="input" required/></div>
    <div><label for="ews-shift-break">{{ __('Break (minutes)') }} *</label><input id="ews-shift-break" name="break_minutes" type="number" min="0" max="480" value="0" class="input" required/></div>
    <div><label for="ews-shift-grace">{{ __('Grace (minutes)') }} *</label><input id="ews-shift-grace" name="grace_minutes" type="number" min="0" max="120" value="0" class="input" required/></div>
    <div><label for="ews-shift-overtime">{{ __('Overtime After (minutes)') }} *</label><input id="ews-shift-overtime" name="overtime_after_minutes" type="number" min="0" max="1440" class="input" required/></div>
    <input type="hidden" name="status" value="active"/>
    <p class="full small">{{ __('Saving this master selects it here. Save the employee section separately to apply it.') }}</p>
</x-admin.quick-create>

<x-admin.quick-create id="employee-master-leave-type" target-selector="[data-employee-master=leave-type]" :url="route('admin.hr.leave-types.store')" title="New Leave Type" permission="HR" :submit="__('Save & select')" :render-trigger="false">
    <div><label for="ews-leave-name">{{ __('Leave Type') }} *</label><input id="ews-leave-name" name="name" class="input" maxlength="100" required/></div>
    <div><label for="ews-leave-code">{{ __('Code') }} *</label><input id="ews-leave-code" name="code" class="input" maxlength="50" required/></div>
    <div><label for="ews-leave-days">{{ __('Maximum days per year') }} *</label><input id="ews-leave-days" name="max_days_per_year" type="number" min="0" max="365" class="input" required/></div>
    <div><label for="ews-leave-paid">{{ __('Paid Leave') }} *</label><select id="ews-leave-paid" name="is_paid" class="select" required><option value="">{{ __('Select...') }}</option><option value="1">{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select></div>
    <p class="full small">{{ __('Saving this master selects it here. Save the employee section separately to apply it.') }}</p>
</x-admin.quick-create>

@if(auth()->user()->hasPermission('Roles', 'create'))
    @php $workspaceRoles = \App\Models\Role::orderBy('name')->get(); @endphp
    <x-admin.quick-create id="employee-master-role" target-selector="[data-employee-master=role]" :url="route('admin.roles.store')" title="New Role" permission="Roles" :submit="__('Save & select')" :render-trigger="false">
        <div><label for="ews-role-name">{{ __('Role Name') }} *</label><input id="ews-role-name" name="name" class="input" maxlength="255" required/></div>
        <div><label for="ews-role-code">{{ __('Code (optional)') }}</label><input id="ews-role-code" name="code" class="input" maxlength="100"/></div>
        <div><label for="ews-role-level">{{ __('Role Level') }} *</label><input id="ews-role-level" name="level" type="number" min="1" max="10" class="input" required/></div>
        <div><label for="ews-role-scope">{{ __('Access Scope') }} *</label><select id="ews-role-scope" name="access_scope" class="select" required><option value="">{{ __('Select...') }}</option>@foreach(['Company Level', 'Project Level', 'Site Level', 'Warehouse Level'] as $scope)<option value="{{ $scope }}">{{ __($scope) }}</option>@endforeach</select></div>
        <div class="full"><label for="ews-role-template">{{ __('Start with permissions of') }}</label><select id="ews-role-template" name="copy_permissions_from" class="select"><option value="">{{ __('No permissions yet') }}</option>@foreach($workspaceRoles as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></div>
        <input type="hidden" name="status" value="active"/>
        <p class="full small">{{ __('A new role has no permissions unless you explicitly choose a permission template. Account creation is a separate save.') }}</p>
    </x-admin.quick-create>
@endif
