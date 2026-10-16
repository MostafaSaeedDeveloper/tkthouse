@php $masked = $account ? $account->maskedSecrets() : []; $k = $account?->id ?? 'new-'.$provider; @endphp
<div class="a-form-grid a-form-grid--2">
    <x-admin.field label="Account name" name="name" :for="'n-'.$k" required><input id="n-{{ $k }}" class="form-control" name="name" value="{{ old('name', $account?->name) }}" required maxlength="120"></x-admin.field>
    <x-admin.field label="Environment" name="environment" :for="'e-'.$k">
        <select id="e-{{ $k }}" class="form-select" name="environment">@foreach(['sandbox' => 'Sandbox', 'production' => 'Production'] as $v => $l)<option value="{{ $v }}" @selected(old('environment', $account?->environment ?? 'sandbox') === $v)>{{ $l }}</option>@endforeach</select>
    </x-admin.field>
    @foreach($def['fields'] as $f)
        <x-admin.field :label="$f['label']" :for="'f-'.$k.'-'.$f['name']" :hint="$f['secret'] ? ($account && isset($masked[$f['name']]) ? 'Stored as '.$masked[$f['name']].'. Leave blank to keep it; type a new value to replace it.' : 'Stored encrypted; never shown again.') : null">
            @if($f['secret'])
                <input id="f-{{ $k }}-{{ $f['name'] }}" class="form-control" type="password" name="{{ $f['name'] }}" value="" autocomplete="new-password" placeholder="{{ $masked[$f['name']] ?? '' }}">
            @else
                <input id="f-{{ $k }}-{{ $f['name'] }}" class="form-control" name="{{ $f['name'] }}" value="{{ old($f['name'], $account?->config[$f['name']] ?? ($f['default'] ?? '')) }}">
            @endif
        </x-admin.field>
    @endforeach
</div>
