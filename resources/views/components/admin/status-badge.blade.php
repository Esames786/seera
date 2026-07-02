@props(['status'])

@php
    $color = match (strtolower((string) $status)) {
        'active', 'enabled', 'success', 'yes', 'approved', 'completed' => 'green',
        'inactive', 'failed', 'locked', 'disabled', 'no', 'rejected' => 'red',
        'pending', 'draft', 'planning', 'reviewed', 'on hold' => 'yellow',
        'updated', 'info' => 'blue',
        default => 'gray',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$color]) }}>{{ ucfirst($status) }}</span>
