<div class="topbar">
    <div class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Home</a> / {{ $slot }}
    </div>
    <div class="topbar-right">
        <span title="Search">🔎</span>
        <span title="Notifications">🔔</span>
        <span title="Settings">⚙️</span>
        @auth
            <div class="avatar" title="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn sm outline">Logout</button>
            </form>
        @endauth
    </div>
</div>
