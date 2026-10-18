@extends('admin.master')
@section('title', 'Import CSV')
@section('content')
<x-admin.page-header title="Import a transactions export" :subtitle="$name.' → '.$account->name.' ('.ucfirst($account->provider).')'" :crumbs="['Dashboard' => route('admin.dashboard'), 'Payments', 'Transactions', 'Imported' => route('admin.payments.imported'), 'Import CSV']" />
<x-admin.flash />
@error('file')<x-admin.alert type="danger">{{ $message }}</x-admin.alert>@enderror
<x-admin.alert type="info">{{ $info['rows'] }} data row(s) found. Nothing has been saved yet. Imported rows never create orders and are marked <strong>unverified</strong>; re-importing the same file only updates the same rows.</x-admin.alert>
<form method="POST" action="{{ route('admin.payments.imported.csv.import') }}" data-a-loading>
    @csrf
    <input type="hidden" name="account_id" value="{{ $account->id }}"><input type="hidden" name="token" value="{{ $token }}">
    <x-admin.card title="Match the columns" subtitle="Provider exports differ, so choose which column holds what. Transaction ID and Amount are required.">
        <div class="a-form-grid a-form-grid--2">
            @foreach($fields as $key => [$label, $required])
                <div class="a-field"><label class="form-label" for="map-{{ $key }}">{{ $label }}@if($required) <span class="a-req">*</span>@endif</label>
                    <select class="form-select" id="map-{{ $key }}" name="map[{{ $key }}]" @required($required)>
                        <option value="">{{ $required ? '— choose a column —' : '— not in the file —' }}</option>
                        @foreach($info['headers'] as $i => $h)<option value="{{ $i }}" @selected(($mapping[$key] ?? null) === $i)>{{ $h !== '' ? $h : 'Column '.($i + 1) }}</option>@endforeach
                    </select></div>
            @endforeach
            <div class="a-field"><label class="form-label" for="unit">Amounts are in</label><select class="form-select" id="unit" name="amount_unit"><option value="major">Pounds (e.g. 150.50)</option><option value="minor">Piasters / cents (e.g. 15050)</option></select></div>
        </div>
    </x-admin.card>
    <x-admin.card title="Preview (first {{ count($info['preview']) }} rows)" flush style="margin-top:12px">
        <x-admin.table compact caption="CSV preview"><thead><tr>@foreach($info['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>@foreach($info['preview'] as $r)<tr>@foreach($info['headers'] as $i => $h)<td>{{ $r[$i] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></x-admin.table>
    </x-admin.card>
    <div class="a-form-actions" style="margin-top:12px"><a class="a-btn a-btn--outline" href="{{ route('admin.payments.imported') }}">Cancel</a><button class="a-btn a-btn--primary" type="submit">Import {{ $info['rows'] }} rows</button></div>
</form>
@endsection
