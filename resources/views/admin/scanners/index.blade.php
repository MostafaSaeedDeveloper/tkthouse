@extends('admin.master')
@section('title', 'Scanners')
@section('content')
<x-admin.page-header title="Scanners" subtitle="Manage scanner users for the gate team." :crumbs="['Dashboard' => route('admin.dashboard'), 'Scanners']">
    <a href="{{ route('admin.scanners.export-history') }}" class="a-btn a-btn--outline"><i class="fa fa-file-csv"></i> Export Check-in History</a>
    <a href="{{ route('admin.scanners.create') }}" class="a-btn a-btn--primary"><i class="fa fa-plus"></i> Add Scanner</a>
</x-admin.page-header>
<x-admin.flash />

<x-admin.filters :action="route('admin.scanners.index')" :reset="route('admin.scanners.index')">
    <x-admin.search name="search" :value="$search" placeholder="Name, username or email" />
</x-admin.filters>

<x-admin.card flush title="Scanner users" :subtitle="$scannerUsers->total().' total'">
    @if($scannerUsers->isEmpty())
        <x-admin.empty icon="fa-qrcode" title="No scanner users found" text="Add a scanner user for your gate team.">
            <a href="{{ route('admin.scanners.create') }}" class="a-btn a-btn--primary">Add Scanner</a>
        </x-admin.empty>
    @else
        <x-admin.table stack caption="Scanner users">
            <thead><tr><th>Scanner</th><th>Email</th><th class="a-num">Scans</th><th class="a-num"><span class="a-sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach($scannerUsers as $scanner)
                <tr>
                    <td data-label="Scanner"><a class="a-table__main a-trunc" href="{{ route('admin.scanners.show', $scanner) }}">{{ $scanner->name }}</a><span class="a-table__sub a-trunc">{{ $scanner->username }}</span></td>
                    <td data-label="Email"><span class="a-trunc" title="{{ $scanner->email }}">{{ $scanner->email }}</span></td>
                    <td data-label="Scans" class="a-num"><b>{{ number_format($scanner->scans_count ?? 0) }}</b></td>
                    <td data-label=""><div class="a-table__actions">
                        <a href="{{ route('admin.scanners.show', $scanner) }}" class="a-btn a-btn--outline a-btn--sm">View</a>
                        <x-admin.dropdown label="" aria-label="More actions">
                            <form method="POST" action="{{ route('admin.scanners.destroy', $scanner) }}" data-a-confirm="Delete this scanner user?" data-a-confirm-title="Delete scanner" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-menu__item a-menu__item--danger" type="submit"><i class="fa fa-trash"></i> Delete</button></form>
                        </x-admin.dropdown>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
        <x-admin.pager :paginator="$scannerUsers" />
    @endif
</x-admin.card>
@endsection
