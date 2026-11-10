@extends('layouts.admin')

@section('title', __('notifications_ui.title'))
@section('breadcrumb', __('notifications_ui.title'))

@section('content')
    <x-admin.page-header :title="__('notifications_ui.title')" :description="__('notifications_ui.description')" />
    <div class="card" style="padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
            <p class="small">{{ __('notifications_ui.limit') }}</p>
            <form method="POST" action="{{ route('admin.notifications.read-all') }}" data-no-dirty-guard>
                @csrf
                <button type="submit" class="btn sm outline">{{ __('notifications_ui.mark_all') }}</button>
            </form>
        </div>
        @forelse ($notifications as $notice)
            @php $data = $notice->data; @endphp
            <div style="padding:14px 0;border-top:1px solid #e2e8f0;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px">
                <div>
                    <strong>{{ __('notifications_ui.'.($data['event'] ?? 'requested')) }}</strong>
                    <span class="small"> · {{ __($data['module'] ?? '') }}</span>
                    <div style="margin-top:4px">{{ __('notifications_ui.reference') }}: {{ $data['reference'] ?? '-' }}</div>
                    <div class="small">{{ $notice->created_at?->format('Y-m-d H:i') }} · {{ $notice->read_at ? __('notifications_ui.read') : __('notifications_ui.unread') }}</div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                    @if($notice->type === \App\Notifications\CustomerOverdueNotification::class)
                        <a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.show', $data['source_id']) }}">{{ __('notifications_ui.view_invoice') }}</a>
                    @elseif(\App\Http\Middleware\EnsureUserHasPermission::canViewAnyOf(auth()->user(), \App\Http\Middleware\EnsureUserHasPermission::ANY_OF['my-approvals.index']))
                        <a class="btn sm outline" href="{{ route('admin.my-approvals.index') }}">{{ __('notifications_ui.view_approvals') }}</a>
                    @endif
                    @if(! $notice->read_at)
                        <form method="POST" action="{{ route('admin.notifications.read', $notice->id) }}" data-no-dirty-guard>
                            @csrf
                            <button type="submit" class="btn sm primary">{{ __('notifications_ui.mark_read') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="table-empty">{{ __('notifications_ui.empty') }}</p>
        @endforelse
        {{ $notifications->links() }}
    </div>
@endsection
