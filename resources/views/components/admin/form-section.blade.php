@props(['title', 'columns' => null])

<div class="form-section">
    <div class="section-title">{{ $title }}</div>
    <div class="section-body {{ $columns ? 'form-grid '.($columns === '2' ? '' : ($columns === '3' ? 'three' : 'four')) : '' }}">
        {{ $slot }}
    </div>
</div>
