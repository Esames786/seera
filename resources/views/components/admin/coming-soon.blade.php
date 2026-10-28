@props(['module', 'phase' => null])

<div class="coming-soon">
    <div class="icon" aria-hidden="true">🚧</div>
    <h2>{{ __($module) }} — {{ __('ui.coming_soon_title') }}</h2>
    <p>{{ __('ui.coming_soon_message') }}</p>
    @if ($phase)
        <p>{{ __('ui.coming_soon_phase', ['phase' => $phase]) }}</p>
    @endif
    <a class="btn primary" href="{{ route('admin.dashboard') }}">{{ __('ui.back_to_dashboard') }}</a>
</div>
