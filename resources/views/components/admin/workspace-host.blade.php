@props(['closeUrl'])
{{-- Host for lazily loaded related panels (shared workspace kit). --}}
<div data-workspace-related-host
    data-close-url="{{ $closeUrl }}"
    data-token="{{ csrf_token() }}"
    data-loading="{{ __('Loading...') }}"
    data-error="{{ __('The request failed. Your unsaved input is still here. Try again.') }}"
    data-refresh-error="{{ __('Saved, but the refreshed list could not be loaded. Retry loading; do not submit again.') }}"
    data-retry="{{ __('Retry loading') }}"
    data-confirm="{{ __('Confirm this action? It is separate from saving the profile.') }}"
    data-reason="{{ __('Reason') }}"
    data-saved="{{ __('Saved successfully. This section is up to date.') }}"
    {{ $attributes }}></div>
