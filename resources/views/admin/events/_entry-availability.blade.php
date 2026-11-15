@php
    $es = $event->entryStatus();
    $by = $event->entry_changed_by ? \App\Models\User::query()->find($event->entry_changed_by) : null;
    $mode = old('entry_mode', $event->entry_mode ?: 'always');
    $lead = (int) $event->entry_opens_minutes_before;
    [$amount, $unit] = $lead > 0 && $lead % 1440 === 0 ? [$lead / 1440, 'days'] : ($lead > 0 && $lead % 60 === 0 ? [$lead / 60, 'hours'] : [$lead ?: 1, $lead > 0 ? 'minutes' : 'hours']);
    $amount = old('entry_opens_amount', $amount); $unit = old('entry_opens_unit', $unit);
    $history = \Spatie\Activitylog\Models\Activity::query()->where('subject_type', \App\Models\Event::class)->where('subject_id', $event->id)
        ->where('description', 'like', 'Entry %')->latest('id')->limit(5)->with('causer')->get();
    $label = ['open' => ['Open', 'success'], 'closed' => ['Closed', 'default'], 'scheduled' => ['Scheduled', 'warning'], 'overridden' => ['Manually overridden', 'info']][$es['state']];
@endphp
<x-admin.card id="ev-entry" title="Entry Availability" subtitle="Controls when the scanner admits ticket holders (gates open). Independent of QR Visibility: customers can see their QR earlier than the gates open. Canceled, refunded and already-used tickets are never admitted.">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <x-admin.badge :variant="$label[1]" :dot="true">{{ $label[0] }}</x-admin.badge>
        <strong>Gates are {{ $es['open'] ? 'open' : 'closed' }} right now.</strong>
        @if($es['state'] === 'scheduled' && $es['opens_at'])<span class="a-muted">They open automatically on {{ $es['opens_at']->format('D j M Y, g:i A') }}.</span>@endif
        @if($es['override'])<span class="a-muted">Manual override: {{ $es['override'] === 'open' ? 'opened' : 'closed' }} (wins over the setting below).</span>@endif
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
        @foreach([['open', 'Open gates', 'fa-door-open', 'a-btn--primary', 'Open the gates for ALL tickets of this event now?'], ['close', 'Close gates', 'fa-door-closed', 'a-btn--outline', 'Close the gates now? The scanner will refuse every ticket of this event until you open them again.']] as [$val, $text, $icon, $cls, $ask])
            <form method="POST" action="{{ route('admin.events.entry-override', $event) }}" data-a-confirm="{{ $ask }}" data-a-confirm-title="{{ $text }}" data-a-confirm-ok="{{ $text }}">
                @csrf<input type="hidden" name="override" value="{{ $val }}">
                <button type="submit" class="a-btn {{ $cls }}" @disabled($es['override'] === $val)><i class="fa {{ $icon }}"></i> {{ $text }}</button>
            </form>
        @endforeach
        @if($es['override'])
            <form method="POST" action="{{ route('admin.events.entry-override', $event) }}">@csrf<input type="hidden" name="override" value="auto"><button type="submit" class="a-btn a-btn--ghost"><i class="fa fa-rotate-left"></i> Back to automatic</button></form>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.events.entry-availability', $event) }}" data-a-loading>
        @csrf
        <div class="a-form-section__head"><h3 class="a-form-section__title">When do the gates open?</h3></div>
        <div style="display:grid;gap:10px;margin-bottom:12px">
            <label class="a-check"><input type="radio" name="entry_mode" value="always" @checked($mode === 'always')> <span><strong>Always open</strong> — no entry restriction (the default).</span></label>
            <label class="a-check"><input type="radio" name="entry_mode" value="scheduled" @checked($mode === 'scheduled')> <span><strong>Scheduled</strong> — the gates open a set time before the event starts.</span></label>
            <div style="display:flex;gap:8px;align-items:center;margin-left:26px;flex-wrap:wrap">
                <input type="number" name="entry_opens_amount" class="form-control" style="width:110px" min="1" step="1" value="{{ $amount }}" aria-label="Gates open lead time">
                <select name="entry_opens_unit" class="form-control" style="width:130px" aria-label="Unit">@foreach(['minutes','hours','days'] as $u)<option value="{{ $u }}" @selected($unit === $u)>{{ $u }}</option>@endforeach</select>
                <span class="a-muted">before the event starts{{ $event->startsAt() ? ' ('.$event->startsAt()->format('D j M, g:i A').')' : '' }}.</span>
            </div>
            @error('entry_opens_amount')<div class="a-error" role="alert">{{ $message }}</div>@enderror
            <label class="a-check"><input type="radio" name="entry_mode" value="manual" @checked($mode === 'manual')> <span><strong>Manual</strong> — closed until you press <em>Open gates</em>.</span></label>
        </div>
        <button type="submit" class="a-btn a-btn--primary">Save entry availability</button>
    </form>

    <div class="a-hint" style="margin-top:18px;border-top:1px solid var(--a-border,#e5e7eb);padding-top:12px">
        @if($event->entry_changed_at)Last changed by <strong>{{ $by?->name ?? 'a former user' }}</strong> on {{ $event->entry_changed_at->format('d M Y, H:i') }}.@else Never changed (default: always open).@endif
        @if($history->isNotEmpty())<ul style="margin:8px 0 0;padding-left:18px">@foreach($history as $h)<li>{{ $h->created_at->format('d M Y, H:i') }} — {{ $h->causer?->name ?? 'System' }}: {{ $h->description }}</li>@endforeach</ul>@endif
    </div>
</x-admin.card>
