@extends('admin.master')
@section('title', 'Database backups')
@section('content')
<x-admin.page-header title="Database backups" subtitle="Super Admin only. A backup contains all customers, orders and payment records: keep downloaded files private." :crumbs="['Dashboard' => route('admin.dashboard'), 'Database backups']">
    <form method="POST" action="{{ route('admin.backups.store') }}" data-a-loading>@csrf<button class="a-btn a-btn--primary" type="submit"><i class="fa fa-database"></i> Create backup now</button></form>
</x-admin.page-header>
<x-admin.flash />
@error('backup')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
<x-admin.card flush title="Backups" :subtitle="'The newest '.$keep.' backups are kept on the server; older ones are removed automatically.'">
    @if(! count($files))
        <x-admin.empty icon="fa-database" title="No backups yet" text="Press “Create backup now”, then download the file." />
    @else
        <x-admin.table stack caption="Database backups">
            <thead><tr><th>File</th><th>Created</th><th>Size</th><th></th></tr></thead>
            <tbody>
            @foreach($files as $f)
                <tr>
                    <td data-label="File" class="a-wrap"><strong>{{ $f['name'] }}</strong></td>
                    <td data-label="Created">{{ $f['created_at']->format('d M Y, H:i') }}</td>
                    <td data-label="Size">{{ number_format($f['size'] / 1048576, 2) }} MB</td>
                    <td data-label="Actions"><div class="a-btn-group">
                        <a class="a-btn a-btn--outline" href="{{ route('admin.backups.download', $f['name']) }}"><i class="fa fa-download"></i> Download</a>
                        <form method="POST" action="{{ route('admin.backups.destroy', $f['name']) }}" data-a-confirm="Delete this backup file from the server?" data-a-confirm-ok="Delete">@csrf @method('DELETE')<button class="a-btn a-btn--ghost" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </x-admin.table>
    @endif
</x-admin.card>
<x-admin.card title="Restoring">
    <p class="a-hint">Files are gzip-compressed SQL. To restore into an empty database: <code>gunzip -c file.sql.gz | mysql -u USER -p DATABASE</code>. Test a restore on a scratch database first, and never restore over production without a fresh backup.</p>
</x-admin.card>
@endsection
