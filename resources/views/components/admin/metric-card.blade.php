@props(['color' => 'blue', 'value', 'label'])

<div class="card metric {{ $color }}">
    <div class="value">{{ $value }}</div>
    <div class="label">{{ $label }}</div>
</div>
