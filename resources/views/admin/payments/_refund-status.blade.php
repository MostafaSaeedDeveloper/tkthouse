@php
    $manual = $refund->mode === 'manual';
    $needsReview = $refund->status === 'unknown' || ($refund->status === 'succeeded' && $refund->effects_status === 'needs_review');
    $label = match (true) {
        $refund->status === 'requested' && $refund->awaiting_approval => 'Awaiting approval',
        $refund->status === 'requested' => 'Refund requested',
        $refund->status === 'processing' => $manual ? 'Processing externally' : 'Processing',
        $refund->status === 'succeeded' && $needsReview => 'Confirmed — tickets need review',
        $refund->status === 'succeeded' => 'Refund confirmed',
        $refund->status === 'failed' => 'Refund failed',
        $refund->status === 'rejected' => 'Rejected',
        $refund->status === 'unknown' => 'Needs review — result unknown',
        default => $refund->status,
    };
    $variant = match (true) {
        $needsReview => 'warning',
        $refund->status === 'succeeded' => 'success',
        in_array($refund->status, ['failed', 'rejected'], true) => 'danger',
        $refund->status === 'processing' => 'info',
        default => 'warning',
    };
@endphp
<x-admin.badge :variant="$variant" dot>{{ $label }}</x-admin.badge>
