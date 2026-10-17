@php $provider = $provider ?? ($account?->provider); $masked = $account ? $account->maskedSecrets() : []; $k = $account?->id ?? 'new-'.$provider; @endphp
<div class="a-form-grid a-form-grid--2">
    <x-admin.field label="Account name" name="name" :for="'n-'.$k" required><input id="n-{{ $k }}" class="form-control" name="name" value="{{ old('name', $account?->name) }}" required maxlength="120"></x-admin.field>
    <x-admin.field label="Environment" name="environment" :for="'e-'.$k">
        <select id="e-{{ $k }}" class="form-select" name="environment">@foreach(['sandbox' => 'Sandbox', 'production' => 'Production'] as $v => $l)<option value="{{ $v }}" @selected(old('environment', $account?->environment ?? 'sandbox') === $v)>{{ $l }}</option>@endforeach</select>
    </x-admin.field>
    @foreach($def['fields'] as $f)
        @if(($f['type'] ?? null) === 'methods')
            @php $chosen = $account ? $account->supportedMethods() : []; @endphp
            <fieldset class="is-full" style="grid-column:1/-1"><legend class="form-label">{{ $f['label'] }}</legend>
                <div class="a-chips">
                    @forelse(($methodsByProvider[$provider] ?? collect()) as $m)
                        <label class="a-m-perm"><input type="checkbox" name="supported_methods[]" value="{{ $m->code }}" @checked(in_array($m->code, old('supported_methods', $chosen)))><span>{{ $m->name }} <code>{{ $m->code }}</code></span></label>
                    @empty
                        <span class="a-hint">No active payment methods exist for this provider yet.</span>
                    @endforelse
                </div>
            </fieldset>
            @continue
        @endif
        <x-admin.field :label="$f['label']" :for="'f-'.$k.'-'.$f['name']" :hint="$f['secret'] ? ($account && isset($masked[$f['name']]) ? 'Stored as '.$masked[$f['name']].'. Leave blank to keep it; type a new value to replace it.' : 'Stored encrypted; never shown again.') : null">
            @if($f['secret'])
                <input id="f-{{ $k }}-{{ $f['name'] }}" class="form-control" type="password" name="{{ $f['name'] }}" value="" autocomplete="new-password" placeholder="{{ $masked[$f['name']] ?? '' }}">
            @else
                <input id="f-{{ $k }}-{{ $f['name'] }}" class="form-control" name="{{ $f['name'] }}" value="{{ old($f['name'], $account?->config[$f['name']] ?? ($f['default'] ?? '')) }}">
            @endif
        </x-admin.field>
    @endforeach
</div>
