@php
    $qrs = $event->qrStatus();
    $changedBy = $event->qr_visibility_changed_by ? \App\Models\User::query()->find($event->qr_visibility_changed_by) : null;
    $mode = old('qr_visibility_mode', $event->qr_visibility_mode ?: 'immediate');
    $lead = (int) $event->qr_reveal_minutes_before;
    [$amount, $unit] = $lead > 0 && $lead % 1440 === 0 ? [$lead / 1440, 'days'] : ($lead > 0 && $lead % 60 === 0 ? [$lead / 60, 'hours'] : [$lead ?: 24, $lead > 0 ? 'minutes' : 'hours']);
    $amount = old('qr_reveal_amount', $amount); $unit = old('qr_reveal_unit', $unit);
    $history = \Spatie\Activitylog\Models\Activity::query()->where('subject_type', \App\Models\Event::class)->where('subject_id', $event->id)
        ->where('description', 'like', 'Ticket QR%')->latest('id')->limit(5)->with('causer')->get();
    $stateLabel = ['visible' => ['Visible', 'success'], 'hidden' => ['Hidden', 'default'], 'scheduled' => ['Scheduled', 'warning'], 'overridden' => ['Manually overridden', 'info']][$qrs['state']];
@endphp
<x-admin.card id="ev-qr" title="Ticket QR Visibility" subtitle="Controls only when customers SEE their QR code. It does not open or close the gates (see Entry Availability). Tickets stay confirmed and valid either way; the QR is never e-mailed.">
    <div class="a-qr-status" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <x-admin.badge :variant="$stateLabel[1]" :dot="true">{{ $stateLabel[0] }}</x-admin.badge>
        <strong>QR codes are {{ $qrs['visible'] ? 'visible' : 'hidden' }} to ticket holders right now.</strong>
        @if($qrs['state'] === 'scheduled' && $qrs['reveals_at'])<span class="a-muted">They appear automatically on {{ $qrs['reveals_at']->format('D j M Y, g:i A') }}.</span>@endif
        @if($qrs['override'])<span class="a-muted">Manual override: {{ $qrs['override'] === 'show' ? 'shown' : 'hidden' }} (it wins over the setting below).</span>@endif
    </div>

    @php $manualOn = $qrs['override'] !== null; @endphp
    <div class="a-seg" role="tablist" aria-label="Visibility control" data-seg="qr">
        <button type="button" class="a-btn {{ $manualOn ? 'a-btn--outline' : 'a-btn--primary' }}" role="tab" data-seg-tab="auto" aria-selected="{{ $manualOn ? 'false' : 'true' }}"><i class="fa fa-clock"></i> Automatic</button>
        <button type="button" class="a-btn {{ $manualOn ? 'a-btn--primary' : 'a-btn--outline' }}" role="tab" data-seg-tab="manual" aria-selected="{{ $manualOn ? 'true' : 'false' }}"><i class="fa fa-hand"></i> Manual</button>
    </div>

    {{-- Automatic: the rule runs by itself --}}
    <div data-seg-panel="auto" @if($manualOn) hidden @endif style="margin-top:16px">
        <form method="POST" action="{{ route('admin.events.qr-visibility', $event) }}" data-a-loading>
            @csrf
            <p class="a-muted" style="margin-bottom:10px">The QR follows this rule on its own. @if($manualOn)Saving returns the event to automatic and ends the manual choice.@endif</p>
            <div style="display:grid;gap:10px;margin-bottom:12px">
                <label class="a-check"><input type="radio" name="qr_visibility_mode" value="immediate" @checked($mode === 'immediate')> <span><strong>Show Immediately</strong> — the QR appears as soon as the ticket is issued.</span></label>
                <label class="a-check"><input type="radio" name="qr_visibility_mode" value="scheduled" @checked($mode === 'scheduled')> <span><strong>Scheduled Reveal</strong> — the QR appears a set time before the event starts.</span></label>
                <div style="display:flex;gap:8px;align-items:center;margin-left:26px;flex-wrap:wrap">
                    <input type="number" name="qr_reveal_amount" class="form-control" style="width:110px" min="1" step="1" value="{{ $amount }}" aria-label="Reveal lead time">
                    <select name="qr_reveal_unit" class="form-control" style="width:130px" aria-label="Unit">
                        @foreach(['minutes', 'hours', 'days'] as $u)<option value="{{ $u }}" @selected($unit === $u)>{{ $u }}</option>@endforeach
                    </select>
                    <span class="a-muted">before the event starts{{ $event->startsAt() ? ' ('.$event->startsAt()->format('D j M, g:i A').')' : '' }}. Common: 24 hours, 12 hours.</span>
                </div>
                @error('qr_reveal_amount')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                @if($mode === 'manual')<label class="a-check"><input type="radio" name="qr_visibility_mode" value="manual" checked> <span><strong>Hidden until shown</strong> (older setting) — press Show QR under Manual.</span></label>@endif
            </div>
            <button type="submit" class="a-btn a-btn--primary">{{ $manualOn ? 'Switch to automatic' : 'Save QR visibility' }}</button>
        </form>
    </div>

    {{-- Manual: you decide right now, for every ticket of the event --}}
    <div data-seg-panel="manual" @if(! $manualOn) hidden @endif style="margin-top:16px">
        <p class="a-muted" style="margin-bottom:10px">You decide now, for every ticket of this event. It stays that way until you switch back to Automatic.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach([['show', 'Show QR', 'fa-eye', 'a-btn--primary'], ['hide', 'Hide QR', 'fa-eye-slash', 'a-btn--outline']] as [$val, $text, $icon, $cls])
                <form method="POST" action="{{ route('admin.events.qr-override', $event) }}" data-a-confirm="{{ $val === 'show' ? 'Show the QR code on ALL tickets of this event now?' : 'Hide the QR code from ALL tickets of this event now? Holders will see QR Code Locked until it is shown again. (Who may enter is controlled separately under Entry Availability.)' }}" data-a-confirm-title="{{ $text }}" data-a-confirm-ok="{{ $text }}">
                    @csrf<input type="hidden" name="override" value="{{ $val }}">
                    <button type="submit" class="a-btn {{ $cls }}" @disabled($qrs['override'] === $val)><i class="fa {{ $icon }}"></i> {{ $text }}{{ $qrs['override'] === $val ? ' (current)' : '' }}</button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="a-hint" style="margin-top:18px;border-top:1px solid var(--a-border,#e5e7eb);padding-top:12px">
        @if($event->qr_visibility_changed_at)
            Last changed by <strong>{{ $changedBy?->name ?? 'a former user' }}</strong> on {{ $event->qr_visibility_changed_at->format('d M Y, H:i') }}.
        @else
            Never changed (default: shown immediately).
        @endif
        @if($history->isNotEmpty())
            <ul style="margin:8px 0 0;padding-left:18px">
                @foreach($history as $h)<li>{{ $h->created_at->format('d M Y, H:i') }} — {{ $h->causer?->name ?? 'System' }}: {{ $h->description }}</li>@endforeach
            </ul>
        @endif
    </div>
</x-admin.card>

@once
<script>
document.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-seg-tab]'); if (!tab) return;
    var card = tab.closest('.a-card') || document, want = tab.getAttribute('data-seg-tab');
    card.querySelectorAll('[data-seg-tab]').forEach(function (b) { var on = b.getAttribute('data-seg-tab') === want; b.classList.toggle('a-btn--primary', on); b.classList.toggle('a-btn--outline', !on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    card.querySelectorAll('[data-seg-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-seg-panel') !== want; });
});
</script>
@endonce
