{{-- Status pill for the statuses that already exist in the system (orders, tickets, events, payment attempts). It maps; it never invents. --}}
@props(['status', 'kind' => 'order'])
@php
    $s = (string) $status;
    $map = [
        'order' => [
            'paid' => ['Paid', 'success'], 'pending_payment' => ['Awaiting payment', 'warning'], 'pending_approval' => ['Awaiting approval', 'warning'],
            'pending' => ['Pending', 'warning'], 'on_hold' => ['On hold', 'warning'], 'canceled' => ['Canceled', 'danger'], 'cancelled' => ['Canceled', 'danger'],
            'rejected' => ['Rejected', 'danger'], 'expired' => ['Expired', 'danger'], 'failed' => ['Failed', 'danger'], 'refunded' => ['Refunded', 'info'],
            'partially_refunded' => ['Partially refunded', 'info'],
        ],
        'payment' => [
            'paid' => ['Paid', 'success'], 'initiated' => ['Processing', 'warning'], 'failed' => ['Failed', 'danger'], 'expired' => ['Expired', 'default'],
            'refunded' => ['Refunded', 'info'],
        ],
        'ticket' => [
            'not_checked_in' => ['Not checked in', 'success'], 'checked_in' => ['Checked in', 'info'], 'canceled' => ['Canceled', 'danger'],
            'active' => ['Active', 'success'], 'sold_out' => ['Sold out', 'danger'], 'hidden' => ['Hidden', 'default'],
        ],
        'event' => [
            'active' => ['Active', 'success'], 'sold_out' => ['Sold out', 'danger'], 'draft' => ['Draft', 'default'], 'inactive' => ['Inactive', 'default'],
            'canceled' => ['Canceled', 'danger'], 'ended' => ['Ended', 'default'], 'archived' => ['Archived', 'info'],
        ],
    ];
    [$label, $variant] = $map[$kind][$s] ?? [ucfirst(str_replace('_', ' ', $s)), 'default'];
@endphp
<span {{ $attributes->class(['a-badge', 'a-badge--'.$variant => $variant !== 'default', 'a-badge--dot']) }}>{{ $label }}</span>
