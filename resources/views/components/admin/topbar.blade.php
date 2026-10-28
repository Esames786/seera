<div class="topbar">
    <div class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a> / {{ $slot }}
    </div>
    <div class="topbar-right">
        @auth
            {{-- My Approvals is the existing permission-scoped actionable queue.
                 Do not display decorative search/settings/notification controls as working features. --}}
            @if (\App\Http\Middleware\EnsureUserHasPermission::canViewAnyOf(auth()->user(), \App\Http\Middleware\EnsureUserHasPermission::ANY_OF['my-approvals.index']))
                <a class="btn sm outline" href="{{ route('admin.my-approvals.index') }}" aria-label="{{ __('My Approvals') }}" title="{{ __('ui.approvals_shortcut_help') }}">✅ {{ __('My Approvals') }}</a>
            @endif
            <a class="btn sm outline" href="{{ route('user-guide') }}" target="_blank" rel="noopener" title="{{ __('Current System User Guide') }}">{{ __('User Guide') }}</a>
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
