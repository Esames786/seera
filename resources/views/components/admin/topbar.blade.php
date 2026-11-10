<div class="topbar">
    <div class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a> / {{ $slot }}
    </div>
    <div class="topbar-right">
        @auth
            @php
                $notificationAccess = app(\App\Services\Notifications\NotificationAccess::class);
                $recentNotifications = $notificationAccess->recent(auth()->user());
                $unreadCount = $notificationAccess->unreadCount(auth()->user());
            @endphp
            <details style="position:relative" data-notification-center>
                <summary class="btn sm outline" style="cursor:pointer;list-style:none" aria-label="{{ __('notifications_ui.bell') }}">
                    🔔 {{ __('notifications_ui.bell') }}@if($unreadCount > 0) <strong>{{ $unreadCount }}</strong>@endif
                </summary>
                <div data-notification-menu style="position:absolute;z-index:80;inset-inline-end:0;top:100%;width:min(340px,calc(100vw - 32px));max-height:70vh;overflow:auto;overflow-wrap:anywhere;background:#fff;border:1px solid #cbd5e1;box-shadow:0 8px 24px #0002;padding:12px;border-radius:9px">
                    @forelse($recentNotifications as $notice)
                        <div style="padding:8px 4px;border-bottom:1px solid #e2e8f0">
                            <div><strong>{{ __('notifications_ui.'.($notice->data['event'] ?? 'requested')) }}</strong></div>
                            <small>{{ $notice->data['reference'] ?? '-' }} · {{ $notice->read_at ? __('notifications_ui.read') : __('notifications_ui.unread') }}</small>
                        </div>
                    @empty
                        <p class="small">{{ __('notifications_ui.recent_empty') }}</p>
                    @endforelse
                    <a class="btn sm outline" href="{{ route('admin.notifications.index') }}">{{ __('notifications_ui.all') }}</a>
                </div>
            </details>
            @once
                @push('scripts')
                    <script>
                        (function () {
                            const center = document.querySelector('[data-notification-center]');
                            const menu = center.querySelector('[data-notification-menu]');
                            function fitMenu() {
                                if (!center.open) return;
                                menu.style.transform = '';
                                const rect = menu.getBoundingClientRect();
                                const width = document.documentElement.clientWidth;
                                const shift = Math.max(12 - rect.left, Math.min(0, width - 12 - rect.right));
                                menu.style.transform = 'translateX(' + shift + 'px)';
                            }
                            center.addEventListener('toggle', fitMenu);
                            window.addEventListener('resize', fitMenu);
                        })();
                    </script>
                @endpush
            @endonce

            @if (\App\Http\Middleware\EnsureUserHasPermission::canViewAnyOf(auth()->user(), \App\Http\Middleware\EnsureUserHasPermission::ANY_OF['my-approvals.index']))
                <a class="btn sm outline" href="{{ route('admin.my-approvals.index') }}" aria-label="{{ __('My Approvals') }}" title="{{ __('ui.approvals_shortcut_help') }}">✅ {{ __('My Approvals') }}</a>
            @endif
            <a class="btn sm outline" href="{{ route('user-guide') }}" target="_blank" rel="noopener" title="{{ __('Current System User Guide') }}">{{ __('User Guide') }}</a>
            <a class="btn sm outline" href="{{ route('user-guide.page', 'CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html') }}" target="_blank" rel="noopener" data-client-feedback-guide>{{ __('Client Issues & Fixes') }}</a>
            <form method="POST" action="{{ route('admin.locale.update') }}" class="locale-switch" data-no-dirty-guard>
                @csrf
                <label for="interface-locale" class="sr-only">{{ __('ui.language') }}</label>
                <select id="interface-locale" name="locale" class="select" style="width:auto">
                    <option value="en" @selected(app()->getLocale() === 'en')>English</option>
                    <option value="ar" @selected(app()->getLocale() === 'ar')>العربية</option>
                </select>
                <button type="submit" class="btn sm outline">{{ __('ui.change_language') }}</button>
            </form>
            <div class="avatar" title="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn sm outline">{{ __('Logout') }}</button>
            </form>
        @endauth
    </div>
</div>
