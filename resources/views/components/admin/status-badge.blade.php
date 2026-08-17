@props(['status'])

@php
    $key = strtolower(str_replace('_', ' ', (string) $status));

    $color = match ($key) {
        'active', 'enabled', 'success', 'yes', 'approved', 'completed',
        'present', 'valid', 'inside', 'paid',
        'posted', 'cleared', 'finalized', 'signed', 'stored' => 'green',
        'inactive', 'failed', 'locked', 'disabled', 'no', 'rejected',
        'absent', 'expired', 'outside', 'terminated' => 'red',
        'pending', 'draft', 'planning', 'reviewed', 'on hold',
        'late', 'expiring soon', 'processed', 'on leave', 'half day',
        'unpaid', 'partially paid', 'pending clearance' => 'yellow',
        'updated', 'info', 'mobile', 'manual', 'leave',
        'generated', 'submitted', 'output' => 'blue',
        'offline', 'input' => 'purple',
        'cancelled' => 'gray',
        default => 'gray',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$color]) }}>{{ ucfirst($key) }}</span>
