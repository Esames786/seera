@extends('layouts.admin')
@section('title', __('offline_sync.title'))
@section('breadcrumb', __('offline_sync.title'))
@push('styles')
<style>
@media(max-width:640px){
    .topbar{height:auto;min-height:64px;flex-wrap:wrap;gap:10px;padding:12px 20px}
    .topbar-right{flex-wrap:wrap;gap:10px;max-width:100%}
    .content{min-width:0}
}
.mobile-sync{max-width:720px;margin:0 auto}
.mobile-sync .card{padding:18px;margin-block-end:14px}
.mobile-sync .muted{color:#64748b;font-size:13px}
.mobile-sync table{width:100%;border-collapse:collapse;font-size:13px}
.mobile-sync th,.mobile-sync td{text-align:start;padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
</style>
@endpush
@section('content')
<div class="mobile-sync" data-offline-sync-page data-offline-page="sync">
    <x-admin.page-header :title="__('offline_sync.title')" :description="__('offline_sync.subtitle')">
        @if($canAttendance)<a class="btn outline" href="{{ route('admin.hr.attendance.mobile') }}">{{ __('mobile_attendance.title') }}</a>@endif
        @if($canExpenses)<a class="btn outline" href="{{ route(auth()->user()->mobile_access ? 'admin.site-expenses.mobile' : 'admin.site-expenses.create') }}">{{ __('offline_sync.add_site_expense') }}</a>@endif
    </x-admin.page-header>

    <div class="card" data-pending-sync>
        <p class="muted">{{ __('offline_sync.reference_missing') }}</p>
    </div>

    <div class="card">
        <h3>{{ __('offline_sync.history') }}</h3>
        @if($receipts->isEmpty())
            <p class="muted">{{ __('offline_sync.no_items') }}</p>
        @else
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>{{ __('offline_sync.captured_at') }}</th><th>{{ __('offline_sync.type') }}</th><th>{{ __('workspace.status') }}</th><th>{{ __('offline_sync.received_at') }}</th><th>{{ __('offline_sync.last_error') }}</th></tr></thead>
                <tbody>
                @foreach($receipts as $receipt)
                    <tr>
                        <td>{{ $receipt->captured_at_client?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>{{ $receipt->operationLabel() }}</td>
                        <td><span class="badge {{ match($receipt->status) { 'synced' => 'green', 'needs_attention' => 'red', 'failed' => 'gray', default => 'yellow' } }}">{{ $receipt->statusLabel() }}</span>
                            @if($receipt->isSynced() && $receipt->business_record_type === 'site_expense' && $receipt->business_record_id)
                                · <a href="{{ route('admin.site-expenses.show', $receipt->business_record_id) }}">{{ $receipt->response['record']['reference'] ?? '#'.$receipt->business_record_id }}</a>
                            @elseif($receipt->isSynced() && $receipt->response['record']['reference'] ?? null)
                                · {{ $receipt->response['record']['reference'] }} {{ $receipt->response['record']['time'] ?? '' }}
                            @endif
                        </td>
                        <td>{{ $receipt->first_received_at?->format('Y-m-d H:i') }}@if($receipt->completed_at && $receipt->captured_at_client) <span class="muted">({{ __('offline_sync.sync_delay') }} {{ (int) $receipt->captured_at_client->diffInMinutes($receipt->completed_at) }} min)</span>@endif</td>
                        <td>{{ $receipt->error_message ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    <div class="card">
        <p class="muted">{{ __('offline_sync.privacy') }}</p>
        <p class="muted">{{ __('offline_sync.limits') }}</p>
    </div>
</div>
@endsection
