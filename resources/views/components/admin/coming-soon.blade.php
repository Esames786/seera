@props(['module', 'phase' => null])

<div class="coming-soon">
    <div class="icon">🚧</div>
    <h2>{{ $module }} — Coming Soon</h2>
    <p>
        This module is planned for a later phase of the {{ config('app.name') }} Construction ERP rollout.
        {{ $phase ? 'Expected in '.$phase.'.' : 'The current release covers Phase 1 (Users & Roles) and Phase 2 (Dashboard & Master Setup).' }}
    </p>
    <a class="btn primary" href="{{ route('admin.dashboard') }}">Back to Dashboard</a>
</div>
