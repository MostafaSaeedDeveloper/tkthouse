@php
    $svc = app(\App\Services\Tickets\QrReleaseService::class);
    $sum = $svc->summary($event);
    $live = $event->qrReleaseIsLive();
    $prev = $svc->preview($event);
    $sb = app(\App\Services\Tickets\QrReleaseSandbox::class)->state($event);
    $by = $event->qr_release_activated_by ? \App\Models\User::query()->find($event->qr_release_activated_by) : null;
    $failed = $sum['failed'] > 0 ? \App\Models\IssuedTicket::query()->where('event_id', $event->id)->where('qr_notice_status', 'failed')->orderBy('id')->limit(10)->get(['holder_email', 'qr_notice_error']) : collect();
    $history = \Spatie\Activitylog\Models\Activity::query()->where('subject_type', \App\Models\Event::class)->where('subject_id', $event->id)->where('description', 'like', 'QR release e-mails%')->latest('id')->limit(5)->with('causer')->get();
    $stBadge = ['not_eligible' => 'default', 'pending' => 'warning', 'sent' => 'success', 'failed' => 'danger'];
@endphp
<x-admin.card id="ev-release" title="QR Release e-mails" subtitle="One e-mail with the QR code(s) when a ticket's QR opens for the first time. Never on Hide, never twice for the same ticket, never to tickets bought after the QR opened.">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <x-admin.badge :variant="$live ? 'success' : 'warning'" :dot="true">{{ $live ? 'LIVE' : 'TEST MODE' }}</x-admin.badge>
        <strong>{{ $live ? 'Customers are e-mailed when their QR opens.' : 'No e-mail is sent to customers (the default: nothing changes until you go Live).' }}</strong>
        @if($live && $event->qr_release_activated_at)<span class="a-muted">Live since {{ $event->qr_release_activated_at->format('d M Y, H:i') }}@if($by) by {{ $by->name }}@endif.</span>@endif
    </div>

    {{-- Summary --}}
    <div class="a-qr-sum" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px">
        @foreach([['Total tickets', $sum['total'], 'All tickets issued for this event'], ['Unlocked from the start', $sum['unlocked_from_start'], 'Booking e-mail only, no release e-mail'], ['Waiting for the QR to open', $sum['waiting'], 'Locked when issued; not eligible yet'], ['Eligible (were locked, now open)', $sum['eligible'], 'Pending + sent + failed'], ['Notified (Sent)', $sum['sent'], 'Never e-mailed again'], ['Pending', $sum['pending'], 'Queued, not sent yet'], ['Failed', $sum['failed'], 'Can be retried'], ['Skipped at go-live', $sum['skipped'], 'Deliberately not notified']] as [$l, $n, $h])
            <div style="border:1px solid var(--a-line,#e5e7eb);border-radius:10px;padding:10px 12px"><div class="a-muted" style="font-size:12px">{{ $l }}</div><div style="font-size:22px;font-weight:800">{{ number_format($n) }}</div><div class="a-muted" style="font-size:11px">{{ $h }}</div></div>
        @endforeach
    </div>

    @if($live)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <form method="POST" action="{{ route('admin.events.qr-release.retry', $event) }}" @if($sum['failed'] === 0) onsubmit="return false" @endif>@csrf<button class="a-btn a-btn--primary" type="submit" @disabled($sum['failed'] === 0)><i class="fa fa-rotate-right"></i> Retry failed notifications ({{ $sum['failed'] }})</button></form>
            <form method="POST" action="{{ route('admin.events.qr-release.pause', $event) }}" data-a-confirm="Back to Test mode? Pending e-mails stay queued but nothing is sent to customers until you go Live again (you will see a preview first)." data-a-confirm-title="Back to Test mode" data-a-confirm-ok="Back to Test">@csrf<button class="a-btn a-btn--outline" type="submit">Back to Test mode</button></form>
        </div>
        @if($failed->isNotEmpty())
            <div class="a-hint" style="margin-bottom:12px"><strong>Failed:</strong>
                <ul style="margin:6px 0 0;padding-left:18px">@foreach($failed as $f)<li>{{ $f->holder_email ?: '(no e-mail)' }} — {{ $f->qr_notice_error }}</li>@endforeach @if($sum['failed'] > $failed->count())<li>… and {{ $sum['failed'] - $failed->count() }} more</li>@endif</ul>
                <p class="a-muted" style="margin:6px 0 0">A failure marked "delivery unknown" may already have reached the customer; retry only after checking.</p>
            </div>
        @endif
    @else
        {{-- TEST MODE: sandbox --}}
        <div class="a-form-section__head"><h3 class="a-form-section__title">Test sandbox (sample tickets only)</h3></div>
        <p class="a-muted" style="margin-bottom:10px">Try Show / Hide on sample tickets and see who would be e-mailed. Real tickets, the real QR and the gate are never touched, and e-mails go only to the test address below, marked <strong>TEST</strong>, with QR codes that do not open the gate.</p>
        <form method="POST" action="{{ route('admin.events.qr-release.sandbox', $event) }}" id="sbx-form">
            @csrf
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
                <div class="a-field" style="min-width:260px;flex:1"><label class="form-label" for="sbx-email">Test e-mail address</label><input type="email" id="sbx-email" name="test_email" class="form-control" value="{{ old('test_email', auth()->user()->email) }}" placeholder="you@yourcompany.com"></div>
                <label class="a-check"><input type="checkbox" name="simulate_failure" value="1"> <span>Simulate a failure on the next send</span></label>
            </div>
            @error('test_email')<div class="a-error" role="alert" style="margin-bottom:8px">{{ $message }}</div>@enderror
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                <button class="a-btn a-btn--primary" name="action" value="show" type="submit"><i class="fa fa-eye"></i> Show QR (test)</button>
                <button class="a-btn a-btn--outline" name="action" value="hide" type="submit"><i class="fa fa-eye-slash"></i> Hide QR (test)</button>
                <button class="a-btn a-btn--outline" name="action" value="add-late" type="submit">+ Ticket bought now</button>
                <button class="a-btn a-btn--outline" name="action" value="retry" type="submit">Retry failed (test)</button>
                <button class="a-btn a-btn--outline" name="action" value="sample" type="submit"><i class="fa fa-paper-plane"></i> Send sample e-mail</button>
                <button class="a-btn a-btn--ghost" name="action" value="reset" type="submit">Reset sandbox</button>
            </div>
        </form>
        <div class="a-hint" style="margin-bottom:6px">Sample QR is currently <strong>{{ $sb['visible'] ? 'visible' : 'hidden' }}</strong>.</div>
        <div style="overflow-x:auto;margin-bottom:10px"><table class="a-table" style="width:100%;font-size:13px"><thead><tr><th>Sample ticket</th><th>Recipient</th><th>Locked at issue</th><th>Notification</th></tr></thead><tbody>
            @foreach($sb['tickets'] as $t)<tr><td>{{ $t['holder'] }} · {{ $t['type'] }}</td><td>{{ $t['email'] }}</td><td>{{ $t['locked_at_issue'] ? 'yes' : 'no (bought after open)' }}</td><td><x-admin.badge :variant="$stBadge[$t['status']]">{{ str_replace('_', ' ', ucfirst($t['status'])) }}</x-admin.badge>@if($t['error']) <span class="a-muted">{{ $t['error'] }}</span>@endif</td></tr>@endforeach
        </tbody></table></div>
        @if(! empty($sb['log']))<ul class="a-muted" style="margin:0 0 14px;padding-left:18px;font-size:12px">@foreach(array_reverse($sb['log']) as $line)<li>{{ $line }}</li>@endforeach</ul>@endif

        {{-- Go live --}}
        <div class="a-form-section__head" style="margin-top:8px"><h3 class="a-form-section__title">Go Live</h3></div>
        <div style="border:1px solid var(--a-line,#e5e7eb);border-radius:10px;padding:12px 14px;margin-bottom:10px">
            <strong>Preview — what going Live would act on right now</strong>
            <p style="margin:6px 0 0">
                @if($prev['visible'])
                    The QR is <strong>visible now</strong>. <strong>{{ number_format($prev['tickets_now']) }}</strong> eligible ticket{{ $prev['tickets_now'] === 1 ? '' : 's' }} for <strong>{{ number_format($prev['recipients_now']) }}</strong> recipient{{ $prev['recipients_now'] === 1 ? '' : 's' }} (one e-mail each) would be e-mailed if you choose "Notify".
                @else
                    The QR is <strong>hidden now</strong>: nothing would be sent at activation. <strong>{{ number_format($prev['tickets_waiting']) }}</strong> ticket{{ $prev['tickets_waiting'] === 1 ? '' : 's' }} ({{ number_format($prev['recipients_waiting']) }} recipient{{ $prev['recipients_waiting'] === 1 ? '' : 's' }}) will be e-mailed when the QR opens.
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('admin.events.qr-release.activate', $event) }}" data-a-confirm="Go LIVE? Real customers will be e-mailed according to your choice." data-a-confirm-title="Go Live" data-a-confirm-ok="Go Live">
            @csrf
            @if($prev['visible'] && $prev['tickets_now'] > 0)
                <div style="display:grid;gap:8px;margin-bottom:10px">
                    <label class="a-check"><input type="radio" name="choice" value="skip" checked> <span><strong>Skip them</strong> — do not e-mail the {{ number_format($prev['tickets_now']) }} already-eligible ticket(s); only tickets that unlock from now on are notified.</span></label>
                    <label class="a-check"><input type="radio" name="choice" value="notify"> <span><strong>Notify them now</strong> — e-mail the {{ number_format($prev['recipients_now']) }} recipient(s) above, in the background, in batches.</span></label>
                </div>
            @else
                <input type="hidden" name="choice" value="notify">
            @endif
            <label class="a-check" style="margin-bottom:10px"><input type="checkbox" name="confirm_live" value="1"> <span>I understand that real customers may be e-mailed.</span></label>
            @error('confirm_live')<div class="a-error" role="alert" style="margin-bottom:8px">{{ $message }}</div>@enderror
            <button class="a-btn a-btn--primary" type="submit"><i class="fa fa-bolt"></i> Go Live</button>
        </form>
    @endif

    @if($history->isNotEmpty())
        <div class="a-hint" style="margin-top:16px;border-top:1px solid var(--a-border,#e5e7eb);padding-top:10px"><ul style="margin:0;padding-left:18px">@foreach($history as $h)<li>{{ $h->created_at->format('d M Y, H:i') }} — {{ $h->causer?->name ?? 'System' }}: {{ $h->description }}</li>@endforeach</ul></div>
    @endif
</x-admin.card>
