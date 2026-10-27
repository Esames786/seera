@props(['title', 'description' => null])

<div class="page-header">
    <div>
        <h1 class="page-title">{{ __($title) }}</h1>
        @if ($description)
            <p class="page-desc">{{ __($description) }}</p>
        @endif
    </div>
    <div class="page-actions">{{ $slot }}</div>
</div>
