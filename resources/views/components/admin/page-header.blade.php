@props(['title', 'description' => null])

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($description)
            <p class="page-desc">{{ $description }}</p>
        @endif
    </div>
    <div class="page-actions">{{ $slot }}</div>
</div>
