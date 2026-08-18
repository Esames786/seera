@php
    $user = auth()->user();
    $groups = \App\Support\SidebarMenu::groups();
@endphp

<aside class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <a class="brand" href="{{ route('admin.dashboard') }}">
            <span class="logo-icon">S</span><span>{{ config('app.name') }} ERP</span>
        </a>
        <div class="small" style="margin-top:5px;color:#94a3b8">Admin Web Portal</div>
    </div>

    @if ($user)
        <div class="user">
            <div class="avatar">{{ $user->initials() }}</div>
            <div>
                <div style="color:#fff;font-weight:800">{{ $user->name }}</div>
                <div class="small" style="color:#94a3b8">{{ $user->primaryRole()?->name ?? 'User' }}</div>
            </div>
        </div>
    @endif

    <nav class="nav-groups">
        @foreach ($groups as $group)
            @php
                $hasActive = collect($group['items'])->contains('active', true);
                $groupBadge = collect($group['items'])->sum(fn ($item) => (int) ($item['badge'] ?? 0));
            @endphp

            <div class="nav-group {{ $hasActive ? 'open' : '' }}" data-group="{{ $group['key'] }}" @if($hasActive) data-has-active="1" @endif>
                <button type="button" class="nav-group-header" aria-expanded="{{ $hasActive ? 'true' : 'false' }}">
                    <span>{{ $group['label'] }}</span>
                    @if ($groupBadge > 0)
                        <span class="nav-badge">{{ $groupBadge }}</span>
                    @endif
                    <span class="chevron" aria-hidden="true">▸</span>
                </button>

                <div class="nav-group-body">
                    @foreach ($group['items'] as $item)
                        <a class="nav-item {{ $item['active'] ? 'active' : '' }} {{ $item['soon'] ? 'is-soon' : '' }}" href="{{ $item['url'] }}">
                            <span>{{ $item['icon'] }}</span>
                            <span>{{ $item['label'] }}</span>
                            @if ($item['badge'])
                                <span class="nav-badge">{{ $item['badge'] }}</span>
                            @elseif ($item['soon'])
                                <span class="soon">Soon</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div style="height:24px"></div>
</aside>

@once
    @push('scripts')
    <script>
        (function () {
            const sidebar = document.getElementById('admin-sidebar');
            if (!sidebar) return;

            const STORAGE_KEY = 'seera.sidebar.groups';

            function readState() {
                try {
                    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
                } catch (error) {
                    return {};
                }
            }

            function writeState(state) {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                } catch (error) {
                    // Storage unavailable (private mode) — collapsing still works for this page.
                }
            }

            const state = readState();

            sidebar.querySelectorAll('.nav-group').forEach(function (group) {
                const key = group.dataset.group;

                // The group holding the current page always wins over stored state.
                if (group.dataset.hasActive === '1') {
                    group.classList.add('open');
                } else if (Object.prototype.hasOwnProperty.call(state, key)) {
                    group.classList.toggle('open', state[key] === true);
                }

                const header = group.querySelector('.nav-group-header');
                header.setAttribute('aria-expanded', group.classList.contains('open') ? 'true' : 'false');

                header.addEventListener('click', function () {
                    const open = group.classList.toggle('open');
                    header.setAttribute('aria-expanded', open ? 'true' : 'false');

                    const next = readState();
                    next[key] = open;
                    writeState(next);
                });
            });
        })();
    </script>
    @endpush
@endonce
