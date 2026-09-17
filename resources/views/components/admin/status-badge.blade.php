@props(['status'])

@php
    $key = strtolower(str_replace('_', ' ', (string) $status));

    $color = match ($key) {
        'active', 'enabled', 'success', 'yes', 'approved', 'completed',
        'present', 'valid', 'inside', 'paid', 'won', 'interested',
        'posted', 'cleared', 'finalized', 'signed', 'stored' => 'green',
        'inactive', 'failed', 'locked', 'disabled', 'no', 'rejected',
        'absent', 'expired', 'outside', 'terminated', 'lost', 'not interested' => 'red',
        'pending', 'draft', 'planning', 'reviewed', 'on hold',
        'late', 'expiring soon', 'processed', 'on leave', 'half day',
        'unpaid', 'partially paid', 'pending clearance', 'follow up', 'quotation requested' => 'yellow',
        'updated', 'info', 'mobile', 'manual', 'leave', 'new', 'assigned',
        'generated', 'submitted', 'output' => 'blue',
        'offline', 'input', 'visited' => 'purple',
        'cancelled' => 'gray',
        default => 'gray',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$color]) }}>{{ ucfirst($key) }}</span>
