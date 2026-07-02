@extends('layouts.admin')

@section('title', 'Assign Users to Role')
@section('breadcrumb', 'Administration / Roles / Assign Users')

@section('content')
    <x-admin.page-header title="Assign Users to Role" description="Assign or remove users from a role with department filter and temporary access"/>

    <form method="GET" class="toolbar">
        <div class="toolbar-left">
            <select class="select" style="width:240px" name="role" onchange="this.form.submit()">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selectedRole && $selectedRole->id === $role->id)>Selected Role: {{ $role->name }}</option>
                @endforeach
            </select>
            <select class="select" style="width:180px" name="department">
                <option value="">All Departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="toolbar-right">
            <button type="submit" class="btn outline">Apply Filter</button>
        </div>
    </form>

    @if ($selectedRole)
        <form method="POST" action="{{ route('admin.roles.assign-users.save') }}" id="assign-users-form">
            @csrf
            <input type="hidden" name="role_id" value="{{ $selectedRole->id }}"/>

            <div class="dual-list">
                <div class="user-list" id="available-list">
                    <h3>Available Users <span class="badge green"><span data-count>{{ $availableUsers->count() }}</span> Total</span></h3>
                    <input class="input js-user-search" type="search" placeholder="Search user..."/>
                    <div style="height:12px"></div>
                    @foreach ($availableUsers as $user)
                        <label class="user-row" data-name="{{ strtolower($user->name) }}">
                            <input type="checkbox" class="checkbox js-pick"/>
                            <input type="hidden" name="user_ids[]" value="{{ $user->id }}" disabled/>
                            <div class="avatar">{{ $user->initials() }}</div>
                            <div>
                                <b>{{ $user->name }}</b>
                                <div class="small">{{ $user->designation?->name ?? 'No designation' }} • {{ $user->site?->name ?? $user->project?->name ?? 'Unassigned' }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="center-actions">
                    <button class="btn primary" type="button" id="btn-assign" title="Assign checked users">&rarr;</button>
                    <button class="btn outline" type="button" id="btn-remove" title="Remove checked users">&larr;</button>
                    <button class="btn sm" type="button" id="btn-assign-all">Add All</button>
                    <button class="btn sm danger" type="button" id="btn-remove-all">Remove All</button>
                </div>

                <div class="user-list" id="assigned-list">
                    <h3>Assigned to Role <span class="badge yellow"><span data-count>{{ $assignedUsers->count() }}</span> Members</span></h3>
                    <input class="input js-user-search" type="search" placeholder="Search assigned..."/>
                    <div style="height:12px"></div>
                    @foreach ($assignedUsers as $user)
                        <label class="user-row" data-name="{{ strtolower($user->name) }}">
                            <input type="checkbox" class="checkbox js-pick"/>
                            <input type="hidden" name="user_ids[]" value="{{ $user->id }}"/>
                            <div class="avatar">{{ $user->initials() }}</div>
                            <div>
                                <b>{{ $user->name }}</b>
                                <div class="small">{{ $user->designation?->name ?? $selectedRole->name }} • {{ $user->site?->name ?? $user->project?->name ?? 'Unassigned' }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="height:16px"></div>

            @php $pivotSample = $assignedUsers->first()?->pivot; @endphp
            <x-admin.form-section title="Temporary Access Option" columns="4">
                <div>
                    <label for="temporary_access">Temporary Access</label>
                    <select id="temporary_access" name="temporary_access" class="select">
                        <option value="0" @selected(!old('temporary_access', $pivotSample?->is_temporary))>No</option>
                        <option value="1" @selected(old('temporary_access', $pivotSample?->is_temporary))>Yes</option>
                    </select>
                </div>
                <div><label for="access_start_date">Access Start Date</label><input id="access_start_date" name="access_start_date" class="input" type="date" value="{{ old('access_start_date', $pivotSample?->access_start_date) }}"/></div>
                <div><label for="access_end_date">Access End Date</label><input id="access_end_date" name="access_end_date" class="input" type="date" value="{{ old('access_end_date', $pivotSample?->access_end_date) }}"/></div>
                <div>
                    <label>Applies To</label>
                    <input class="input" value="All users in the assigned list" readonly/>
                </div>
                <div class="full"><label for="reason">Reason</label><textarea id="reason" name="reason" class="textarea" placeholder="Example: temporary project coverage for Riyadh Tower">{{ old('reason', $pivotSample?->reason) }}</textarea></div>
            </x-admin.form-section>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.roles.index') }}">Cancel</a>
                <button type="submit" class="btn primary">Save Changes</button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const available = document.getElementById('available-list');
        const assigned = document.getElementById('assigned-list');
        if (!available || !assigned) return;

        function move(row, target) {
            row.querySelector('.js-pick').checked = false;
            row.querySelector('input[type="hidden"]').disabled = (target === available);
            target.appendChild(row);
        }

        function moveChecked(from, to, all) {
            from.querySelectorAll('.user-row').forEach(function (row) {
                if (row.style.display === 'none') return;
                if (all || row.querySelector('.js-pick').checked) move(row, to);
            });
            refreshCounts();
        }

        function refreshCounts() {
            available.querySelector('[data-count]').textContent = available.querySelectorAll('.user-row').length;
            assigned.querySelector('[data-count]').textContent = assigned.querySelectorAll('.user-row').length;
        }

        document.getElementById('btn-assign').addEventListener('click', () => moveChecked(available, assigned, false));
        document.getElementById('btn-remove').addEventListener('click', () => moveChecked(assigned, available, false));
        document.getElementById('btn-assign-all').addEventListener('click', () => moveChecked(available, assigned, true));
        document.getElementById('btn-remove-all').addEventListener('click', () => moveChecked(assigned, available, true));

        document.querySelectorAll('.js-user-search').forEach(function (input) {
            input.addEventListener('input', function () {
                const term = input.value.toLowerCase();
                input.closest('.user-list').querySelectorAll('.user-row').forEach(function (row) {
                    row.style.display = row.dataset.name.includes(term) ? '' : 'none';
                });
            });
        });
    })();
</script>
@endpush
