@extends('admin.master')
@section('title', 'Scanners')
@push('styles')<link rel="stylesheet" href="{{ asset('admin-v2/misc.css') }}?v={{ filemtime(public_path('admin-v2/misc.css')) }}">@endpush
@section('content')
<x-admin.page-header title="Scanners" subtitle="Manage scanner users for the gate team." :crumbs="['Dashboard' => route('admin.dashboard'), 'Scanners']">
    <a href="{{ route('admin.scanners.export-history') }}" class="a-btn a-btn--outline"><i class="fa fa-file-csv"></i> Export Check-in History</a>
    <a href="{{ route('admin.scanners.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Scanner</a>
</x-admin.page-header>
<x-admin.flash />

<x-admin.filters :action="route('admin.scanners.index')" :reset="route('admin.scanners.index')">
    <div class="a-field"><label class="form-label" for="scanner-search">Search</label>
        <input class="form-control" id="scanner-search" type="text" name="search" value="{{ $search }}" placeholder="Search scanner users..."></div>
</x-admin.filters>

<x-admin.card flush>
    @if($scannerUsers->isEmpty())
        <x-admin.empty icon="fa-qrcode" title="No scanner users found." text="Add a scanner user for your gate team.">
            <a href="{{ route('admin.scanners.create') }}" class="a-btn a-btn--primary">Add Scanner</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Scanner users">
            <thead><tr><th>Name</th><th>Username</th><th>Email</th><th class="a-num">Scan Count</th><th><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($scannerUsers as $scanner)
                <tr>
                    <td data-label="Name"><span class="a-table__main">{{ $scanner->name }}</span></td>
                    <td data-label="Username"><a class="a-table__main" href="{{ route('admin.scanners.show', $scanner) }}">{{ $scanner->username }}</a></td>
                    <td data-label="Email">{{ $scanner->email }}</td>
                    <td data-label="Scan Count" class="a-num"><x-admin.badge variant="info">{{ number_format($scanner->scans_count ?? 0) }}</x-admin.badge></td>
                    <td data-label="Actions"><div class="a-table__actions">
                        <a href="{{ route('admin.scanners.show', $scanner) }}" class="a-btn a-btn--outline a-btn--sm">View</a>
                        <form method="POST" action="{{ route('admin.scanners.destroy', $scanner) }}" data-a-confirm="Delete this scanner user?" data-a-confirm-title="Delete scanner" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$scannerUsers" />
    @endif
</x-admin.card>
@endsection
