<div class="topbar">
    <div class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a> / {{ $slot }}
    </div>
    <div class="topbar-right">
        <span title="Search">🔎</span>
        <span title="Notifications">🔔</span>
        <span title="Settings">⚙️</span>
        @auth
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
