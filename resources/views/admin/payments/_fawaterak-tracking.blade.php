{{-- Fawaterak external refund tracking. Everything shown comes from the database; opening Fawaterak or this modal never completes anything. --}}
@php
    $t = $tracking;
    $approved = ! $refund->awaiting_approval;
    $hasId = filled($t['invoice_id']) || filled($t['reference_number']);
    $copyId = $t['invoice_id'] ?: $t['reference_number'];
    $stateClass = ['completed' => 'success', 'failed' => 'danger', 'needs_review' => 'warning', 'notification_received' => 'warning'][$t['state']] ?? 'info';
@endphp
<x-admin.card title="Fawaterak refund tracking" subtitle="You carry out the refund in the Fawaterak dashboard; TKT House tracks it and updates the order and tickets only after it is confirmed.">
    @if(! $approved)
        <p class="a-hint">Available once the refund is approved{{ $approvalRequired ? ' by a second person' : ' or released' }}.</p>
    @elseif($refund->isFinal())
        <p class="a-hint">{{ $t['label'] }}.</p>
        <button type="button" class="a-btn a-btn--outline" data-bs-toggle="modal" data-bs-target="#fwTrack">View tracking</button>
    @else
        <div class="a-btn-group" style="flex-wrap:wrap;gap:8px">
            <button type="button" class="a-btn a-btn--primary" id="fwOpen" data-bs-toggle="modal" data-bs-target="#fwTrack" @disabled(! $canCreate && ! $canApprove)><i class="fa fa-arrow-up-right-from-square"></i> Open Transaction in Fawaterak</button>
        </div>
        <p class="a-hint" style="margin-top:8px">Status: <strong id="fwStateInline">{{ $t['label'] }}</strong></p>
    @endif
</x-admin.card>

<div class="modal fade" id="fwTrack" tabindex="-1" aria-labelledby="fwTrackT" aria-hidden="true" data-snapshot-url="{{ route('admin.payments.refunds.tracking', $refund) }}" data-opened-url="{{ route('admin.payments.refunds.opened', $refund) }}" data-dashboard="{{ $t['dashboard_url'] }}" data-final="{{ $t['final'] ? 1 : 0 }}">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="fwTrackT">Refund {{ $refund->internal_reference }} · order #{{ $t['order'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="a-alert a-alert--{{ $stateClass }}" role="status" id="fwStateBox" data-fw="state-box" style="display:block"><strong data-fw="label">{{ $t['label'] }}</strong>
            <div class="a-hint" data-fw="completed-note">{{ $t['completed'] ? 'The refund was confirmed with a provider reference. Tickets were canceled and the order updated.' : 'Opening Fawaterak does not mean the money was returned. This page shows success only after the refund is confirmed.' }}</div></div>
        <ol class="a-timeline" data-fw="steps">@foreach($t['steps'] as $k => $l)<li data-step="{{ $k }}" @if($k === $t['state']) style="font-weight:700" @endif>{{ $l }}</li>@endforeach</ol>
        <dl class="a-kv">
            <div><dt>Order</dt><dd>#{{ $t['order'] }}</dd></div>
            <div><dt>Refund reference</dt><dd>{{ $t['refund'] }}</dd></div>
            <div><dt>Original Fawaterak invoice ID</dt><dd><span data-fw="invoice">{{ $t['invoice_id'] ?: '—' }}</span></dd></div>
            @if($t['reference_number'])<div><dt>Fawaterak reference number</dt><dd>{{ $t['reference_number'] }}</dd></div>@endif
            <div><dt>Amount</dt><dd>{{ $t['amount'] }} {{ $t['currency'] }} · {{ $t['type'] }}</dd></div>
            <div><dt>Tickets</dt><dd>{{ $t['tickets'] ? implode(', ', $t['tickets']) : ($t['type'] === 'Full refund' ? 'All valid tickets (on confirmation)' : 'None (money only)') }}</dd></div>
            <div><dt>Reason</dt><dd>{{ $t['reason'] }}</dd></div>
            <div><dt>Last provider event</dt><dd data-fw="event">{{ $t['provider_event_at'] ? \Illuminate\Support\Carbon::parse($t['provider_event_at'])->format('d M Y, H:i') : 'None received' }}</dd></div>
            <div><dt>Last verification</dt><dd data-fw="verified">{{ $t['last_verified_at'] ? \Illuminate\Support\Carbon::parse($t['last_verified_at'])->format('d M Y, H:i') : 'Never — Fawaterak has no refund status API, a person verifies it' }}</dd></div>
        </dl>
        @if(! $hasId && ! $refund->isFinal())
            <div class="a-alert a-alert--warning" role="alert">TKT House holds no original Fawaterak invoice / transaction id for this payment (legacy order). Find it in the Fawaterak dashboard and record it; nothing is guessed.
                @if($canCreate || $canApprove)
                <form method="POST" action="{{ route('admin.payments.refunds.original-reference', $refund) }}" style="display:grid;gap:6px;margin-top:8px">@csrf
                    <input class="form-control" name="original_reference" required maxlength="120" placeholder="Original Fawaterak invoice / transaction id" aria-label="Original Fawaterak id">
                    <button class="a-btn a-btn--outline" type="submit">Record original id</button></form>
                @endif
            </div>
        @endif
        @if(! $t['dashboard_url'] && ! $refund->isFinal())<p class="a-hint">The Fawaterak dashboard link is not configured (Fawaterak documents no direct invoice link). Open your Fawaterak merchant dashboard yourself, search for the invoice id above, and use the copy button.</p>@endif
        @if($t['state'] === 'notification_received')<div class="a-alert a-alert--warning" role="alert">Fawaterak sent an <strong>unsigned</strong> approval notification. It is not proof that funds were returned: check the refund in the Fawaterak dashboard, then record its reference and the actual date below the page (Confirm completed).</div>@endif
    </div>
    <div class="modal-footer" style="flex-wrap:wrap;gap:8px">
        @if($copyId)<button type="button" class="a-btn a-btn--outline" data-fw="copy" data-copy="{{ $copyId }}"><i class="fa fa-copy"></i> Copy invoice / transaction ID</button>@endif
        @if($t['dashboard_url'] && ! $refund->isFinal())<a class="a-btn a-btn--outline" href="{{ $t['dashboard_url'] }}" target="_blank" rel="noopener noreferrer" data-fw="reopen"><i class="fa fa-arrow-up-right-from-square"></i> {{ $t['direct_link'] ? 'Open this transaction in Fawaterak' : 'Open Fawaterak dashboard' }}</a>@endif
        <button type="button" class="a-btn a-btn--outline" data-fw="refresh"><i class="fa fa-rotate"></i> Check status</button>
        @if(in_array($refund->status, ['requested', 'processing'], true) && ! $refund->submitted_by && ($canCreate || $canApprove) && ! $refund->awaiting_approval)
            <form method="POST" action="{{ route('admin.payments.refunds.submitted', $refund) }}">@csrf<button class="a-btn a-btn--outline" type="submit">I submitted it in Fawaterak</button></form>
        @endif
        <button type="button" class="a-btn a-btn--primary" data-bs-dismiss="modal">Close (tracking continues)</button>
    </div>
  </div></div>
</div>
@push('scripts')
<script>
(function () {
    var m = document.getElementById('fwTrack'); if (!m) return;
    var timer = null, csrf = document.querySelector('meta[name="csrf-token"]');
    function q(k) { return m.querySelector('[data-fw="' + k + '"]'); }
    function fmt(v) { if (!v) return null; var d = new Date(v); return isNaN(d) ? v : d.toLocaleString(); }
    function paint(s) {
        q('label').textContent = s.label;
        q('completed-note').textContent = s.completed ? 'The refund was confirmed with a provider reference. Tickets were canceled and the order updated.' : 'Opening Fawaterak does not mean the money was returned. This page shows success only after the refund is confirmed.';
        var box = q('state-box'); box.className = 'a-alert a-alert--' + ({completed: 'success', failed: 'danger', needs_review: 'warning', notification_received: 'warning'}[s.state] || 'info');
        m.querySelectorAll('[data-step]').forEach(function (li) { li.style.fontWeight = li.dataset.step === s.state ? '700' : ''; });
        q('event').textContent = fmt(s.provider_event_at) || 'None received';
        if (s.last_verified_at) q('verified').textContent = fmt(s.last_verified_at);
        var inline = document.getElementById('fwStateInline'); if (inline) inline.textContent = s.label;
        if (s.final) { stop(); if (m.dataset.final !== '1') { m.dataset.final = '1'; } }
        if (s.completed && !window.__fwReloaded) { window.__fwReloaded = true; setTimeout(function () { location.reload(); }, 1500); }
    }
    function check() { return fetch(m.dataset.snapshotUrl, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'}).then(function (r) { if (r.status === 401 || r.status === 419 || r.redirected) { stop(); q('label').textContent = 'Your session expired. Sign in again to see the latest status — tracking continues on the server.'; return null; } if (r.status === 403 || r.status === 404) { stop(); q('label').textContent = 'You no longer have access to this refund.'; return null; } return r.ok ? r.json() : null; }).then(function (s) { if (s) paint(s); }).catch(function () {}); }
    function start() { stop(); if (m.dataset.final !== '1') timer = setInterval(check, 5000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    m.addEventListener('shown.bs.modal', function () { check(); start(); });
    m.addEventListener('hidden.bs.modal', stop);
    q('refresh').addEventListener('click', check);
    var copy = q('copy'); if (copy) copy.addEventListener('click', function () { (navigator.clipboard ? navigator.clipboard.writeText(copy.dataset.copy) : Promise.reject()).then(function () { copy.textContent = 'Copied'; }).catch(function () { window.prompt('Copy the id:', copy.dataset.copy); }); });
    var open = document.getElementById('fwOpen');
    if (open) open.addEventListener('click', function () {
        // audit only; the refund is not touched. The provider tab opens only when an https dashboard address is configured.
        if (m.dataset.dashboard) window.open(m.dataset.dashboard, '_blank', 'noopener,noreferrer');
        fetch(m.dataset.openedUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf ? csrf.content : '{{ csrf_token() }}', 'Accept': 'application/json'}, credentials: 'same-origin'}).catch(function () {});
    });
})();
</script>
@endpush
