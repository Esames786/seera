@extends('layouts.admin')
@section('title', __('mobile_attendance.title'))
@section('breadcrumb', 'HR & Payroll / Attendance / '.__('mobile_attendance.title'))
@push('styles')
<style>
@media(max-width:640px){
    .topbar{height:auto;min-height:64px;flex-wrap:wrap;gap:10px;padding:12px 20px}
    .topbar-right{flex-wrap:wrap;gap:10px;max-width:100%}
    .content{min-width:0}
}
.mobile-attendance{max-width:560px;margin:0 auto}
.mobile-attendance .card{padding:18px;margin-block-end:14px}
.mobile-attendance dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:15px}
.mobile-attendance dt{color:#64748b}
.mobile-attendance dd{margin:0;font-weight:700;word-break:break-word}
.mobile-attendance .big-btn{display:block;width:100%;min-height:64px;font-size:20px;font-weight:800;border-radius:14px}
.mobile-attendance .state{font-size:18px;font-weight:800;margin:0 0 6px}
.mobile-attendance [data-result]{display:grid;gap:6px;font-size:15px}
.mobile-attendance .ok{color:var(--green)}
.mobile-attendance .warn{color:var(--red)}
.mobile-attendance .muted{color:#64748b;font-size:13px}
</style>
@endpush
@section('content')
@php
    $stateLabel = __('mobile_attendance.state_'.$state);
    $stateClass = match ($state) { 'can_check_out' => 'ok', 'ineligible' => 'warn', default => '' };
@endphp
<div class="mobile-attendance" data-mobile-attendance
     data-state="{{ $state }}"
     data-locate-url="{{ $eligible ? route('admin.hr.attendance.mobile.locate') : '' }}"
     data-check-in-url="{{ $eligible ? route('admin.hr.attendance.mobile.check-in') : '' }}"
     data-check-out-url="{{ $eligible ? route('admin.hr.attendance.mobile.check-out') : '' }}"
     data-token="{{ csrf_token() }}"
     data-i18n="{{ json_encode([
        'getting_location' => __('mobile_attendance.getting_location'), 'location_ready' => __('mobile_attendance.location_ready'),
        'location_denied' => __('mobile_attendance.location_denied'), 'location_timeout' => __('mobile_attendance.location_timeout'),
        'location_unavailable_device' => __('mobile_attendance.location_unavailable_device'), 'location_unsupported' => __('mobile_attendance.location_unsupported'),
        'request_failed' => __('mobile_attendance.request_failed'), 'allowed_radius' => __('mobile_attendance.allowed_radius'),
        'your_accuracy' => __('mobile_attendance.your_accuracy'), 'distance' => __('mobile_attendance.distance'), 'position_result' => __('mobile_attendance.position_result'),
        'confirm_check_in' => __('mobile_attendance.confirm_check_in'), 'confirm_check_out' => __('mobile_attendance.confirm_check_out'),
        'check_in' => __('mobile_attendance.check_in'), 'check_out' => __('mobile_attendance.check_out'), 'cancel' => __('mobile_attendance.cancel'),
        'state_can_check_out' => __('mobile_attendance.state_can_check_out'), 'state_completed' => __('mobile_attendance.state_completed'),
        'late_by' => __('mobile_attendance.late_by', ['minutes' => ':minutes']), 'on_time' => __('mobile_attendance.on_time'),
        'checked_in_at' => __('mobile_attendance.checked_in_at', ['time' => ':time']), 'checked_out_at' => __('mobile_attendance.checked_out_at', ['time' => ':time']),
        'site' => __('mobile_attendance.site'),
     ]) }}">
    <x-admin.page-header :title="__('mobile_attendance.title')" :description="__('mobile_attendance.subtitle')"/>

    <div class="card">
        <dl>
            <dt>{{ __('mobile_attendance.employee') }}</dt><dd>{{ $employee ? $employee->employee_code.' — '.$employee->name : '-' }}</dd>
            <dt>{{ __('mobile_attendance.project') }}</dt><dd>{{ $employee?->project?->name ?? '-' }}</dd>
            <dt>{{ __('mobile_attendance.site') }}</dt><dd data-site-name>{{ $site?->name ?? '-' }}</dd>
            <dt>{{ __('mobile_attendance.shift') }}</dt><dd>{{ $shift ? $shift->name.' ('.substr((string) $shift->start_time, 0, 5).'–'.substr((string) $shift->end_time, 0, 5).')' : __('mobile_attendance.no_shift') }}</dd>
            <dt>{{ __('mobile_attendance.server_time') }}</dt><dd data-server-time>{{ $serverTime->format('Y-m-d H:i') }}</dd>
        </dl>
    </div>

    <div class="card" data-state-card>
        <p class="state {{ $stateClass }}" data-state-label>{{ $stateLabel }}</p>
        @if($today && $today->isGpsRecord())
            <div class="muted" data-today>
                {{ __('mobile_attendance.checked_in_at', ['time' => $today->check_in]) }} · {{ $statusLabel }}@if($today->check_in_distance_meters !== null) · {{ number_format($today->check_in_distance_meters) }} m @endif
                · {{ $today->late_minutes > 0 ? __('mobile_attendance.late_by', ['minutes' => $today->late_minutes]) : __('mobile_attendance.on_time') }}
                @if($today->check_out) · {{ __('mobile_attendance.checked_out_at', ['time' => $today->check_out]) }}@endif
            </div>
        @endif
        @if(! $eligible)
            <p class="warn" role="alert">{{ $reason }}</p>
        @endif
    </div>

    @if($eligible && in_array($state, ['can_check_in', 'can_check_out'], true))
        <div class="card">
            <div role="status" aria-live="polite" data-status class="muted"></div>
            <div data-result hidden>
                <div><strong>{{ __('mobile_attendance.site') }}:</strong> <span data-result-site>{{ $site->name }}</span></div>
                <div><strong>{{ __('mobile_attendance.allowed_radius') }}:</strong> <span data-result-radius>{{ $site->geofence_enabled ? $site->geofence_radius.' m' : __('mobile_attendance.not_enforced') }}</span></div>
                <div><strong>{{ __('mobile_attendance.your_accuracy') }}:</strong> <span data-result-accuracy>-</span></div>
                <div><strong>{{ __('mobile_attendance.distance') }}:</strong> <span data-result-distance>-</span></div>
                <div><strong>{{ __('mobile_attendance.position_result') }}:</strong> <span data-result-status>-</span></div>
            </div>
            <div class="alert" role="alert" data-error hidden></div>
            <div style="display:grid;gap:10px;margin-top:12px">
                <button type="button" class="btn primary big-btn" data-action="locate">{{ $state === 'can_check_in' ? __('mobile_attendance.check_in') : __('mobile_attendance.check_out') }}</button>
                <button type="button" class="btn primary big-btn" data-action="confirm" hidden>{{ $state === 'can_check_in' ? __('mobile_attendance.confirm_check_in') : __('mobile_attendance.confirm_check_out') }}</button>
                <button type="button" class="btn outline big-btn" data-action="cancel" hidden>{{ __('mobile_attendance.cancel') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <p class="muted">{{ __('mobile_attendance.privacy') }}</p>
        <p class="muted">{{ __('mobile_attendance.manual_note') }}</p>
    </div>
</div>
@endsection
