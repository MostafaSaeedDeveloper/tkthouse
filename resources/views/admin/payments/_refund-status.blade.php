@php
    $label = ['requested' => $refund->awaiting_approval ? 'Awaiting approval' : 'Requested', 'processing' => 'Processing', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'rejected' => 'Rejected', 'unknown' => 'Unknown — needs review'][$refund->status] ?? $refund->status;
    $variant = ['requested' => 'warning', 'processing' => 'info', 'succeeded' => 'success', 'failed' => 'danger', 'rejected' => 'danger', 'unknown' => 'warning'][$refund->status] ?? 'default';
@endphp
<x-admin.badge :variant="$variant" dot>{{ $label }}</x-admin.badge>
