@extends('layouts.admin')
@section('title', __('feedback.my_leave'))
@section('breadcrumb', __('feedback.my_leave'))
@section('content')
    <x-admin.page-header :title="__('feedback.my_leave')" :description="$employee->employee_code.' — '.$employee->name">
        <a class="btn outline" href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
    </x-admin.page-header>
    <div class="card" style="padding:16px;margin-block:16px">
        <p>{{ __('feedback.balance_rule') }}</p>
        <form method="GET" class="form-grid three">
            <div><label for="leave_year">{{ __('feedback.leave_year') }}</label><input class="input" id="leave_year" name="year" type="number" min="2000" max="2100" value="{{ $balance['year'] }}"/></div>
            <button class="btn outline">{{ __('Filter') }}</button>
        </form>
        <p>{{ __('Annual Leave Entitlement') }}: {{ $balance['entitlement'] }} · {{ __('workspace.hr_used') }}: {{ $balance['used'] }} · {{ __('workspace.hr_pending') }}: {{ $balance['pending'] }} · {{ __('workspace.hr_remaining') }}: <strong>{{ $balance['remaining'] }}</strong></p>
    </div>
    <form method="POST" action="{{ route('admin.my-leave.store') }}" enctype="multipart/form-data">
        @csrf
        <x-admin.form-section :title="__('feedback.apply_leave')" columns="3">
            <div><label for="leave_type_id">{{ __('Leave Type') }} *</label><select id="leave_type_id" name="leave_type_id" class="select" required><option value="">{{ __('Select...') }}</option>@foreach($leaveTypes as $type)<option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ __($type->name) }}</option>@endforeach</select></div>
            <div><label for="start_date">{{ __('Start Date') }} *</label><input class="input" id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" required/></div>
            <div><label for="end_date">{{ __('End Date') }} *</label><input class="input" id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" required/></div>
            <div><label for="reason">{{ __('Reason') }}</label><textarea class="textarea" id="reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea></div>
            <div><label for="attachment">{{ __('Attachment') }}</label><input class="input" id="attachment" name="attachment" type="file" accept="application/pdf,image/jpeg,image/png,image/webp"/></div>
            <p class="small">{{ __('feedback.leave_pending_help') }}</p>
        </x-admin.form-section>
        <div class="form-actions"><button class="btn primary" type="submit">{{ __('feedback.apply_leave') }}</button></div>
    </form>
    <x-admin.data-table :title="__('feedback.my_leave')">
        <thead><tr><th>{{ __('Leave Type') }}</th><th>{{ __('Start Date') }}</th><th>{{ __('End Date') }}</th><th>{{ __('Total Days') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Attachment') }}</th></tr></thead>
        <tbody>@forelse($leaves as $leave)<tr><td>{{ __($leave->leaveType?->name ?? '-') }}</td><td>{{ $leave->start_date->toDateString() }}</td><td>{{ $leave->end_date->toDateString() }}</td><td>{{ $leave->total_days }}</td><td>{{ __($leave->status) }}</td><td style="overflow-wrap:anywhere">{{ $leave->reason }}</td><td>@if($leave->attachment_path)<a href="{{ route('admin.my-leave.attachment', $leave->id) }}">{{ __('Download') }}</a>@endif</td></tr>@empty<tr><td colspan="7">{{ __('No records yet.') }}</td></tr>@endforelse</tbody>
    </x-admin.data-table>
    {{ $leaves->links() }}
@endsection
