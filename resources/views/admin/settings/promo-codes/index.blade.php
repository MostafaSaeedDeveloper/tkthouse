@extends('layouts.backend')

@section('title', 'Promo Codes')

@section('content')
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Promo Codes</h1>
            <a href="{{ route('admin.settings.edit') }}" class="fs-sm">← Back to General Settings</a>
        </div>
        <a href="{{ route('admin.promo-codes.create') }}" class="btn btn-primary">Add Promo Code</a>
    </div>

    <div class="block block-rounded">
        <div class="block-content table-responsive">
            <table class="table table-striped table-vcenter">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promoCodes as $promoCode)
                        <tr>
                            <td class="fw-semibold">{{ $promoCode->code }}</td>
                            <td>
                                @if($promoCode->discount_type === 'percent')
                                    {{ rtrim(rtrim(number_format((float) $promoCode->discount_value, 2), '0'), '.') }}%
                                @else
                                    {{ number_format((float) $promoCode->discount_value, 2) }} EGP
                                @endif
                            </td>
                            <td>{{ $promoCode->used_count }} / {{ $promoCode->usage_limit ?? '∞' }}</td>
                            <td>
                                <span class="badge {{ $promoCode->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td>{{ $promoCode->starts_at?->format('Y-m-d H:i') ?? 'Any' }} → {{ $promoCode->ends_at?->format('Y-m-d H:i') ?? 'Open' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.promo-codes.edit', $promoCode) }}" class="btn btn-sm btn-alt-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.promo-codes.destroy', $promoCode) }}" class="d-inline" onsubmit="return confirm('Delete this promo code?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-alt-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">No promo codes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $promoCodes->links() }}</div>
</div>
@endsection
