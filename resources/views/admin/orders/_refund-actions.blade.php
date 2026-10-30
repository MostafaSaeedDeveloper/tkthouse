{{-- Refund request form. $order always; $attempt = the paid payment being refunded, or null for a legacy paid order (no payment attempt). --}}
@php
    $user = auth()->user();
    $svc = app(\App\Services\Payments\Refunds\RefundService::class);
    $attempt = $attempt ?? null;
    $cap = $attempt ? $svc->capability($attempt) : $svc->legacyCapability($order);
    $bal = $svc->balanceFor($attempt, $order);
    $canCreate = $user->can('payments.refunds.create') && \App\Orders\OrderAccess::canManage($order, $user);
    $minorToDec = fn (int $m) => \App\Models\PaymentRefund::minorToDecimal($m);
    $uid = 'rf'.($attempt?->id ?? 'o'.$order->id);
    $needsApproval = $svc->approvalRequired();
    $currency = $attempt?->currency ?: 'EGP';
    $choices = $svc->ticketChoices($order);
    $methodName = $paymentMethodLabel ?? ucwords(str_replace('_', ' ', (string) $order->payment_method));
    $provider = $attempt ? ucfirst((string) $attempt->provider) : 'None (legacy / offline payment)';
    $action = $attempt ? route('admin.payments.refunds.store', $attempt) : route('admin.payments.refunds.store-order', $order);
    $eligible = ($attempt ? $attempt->status === 'paid' : in_array($order->status, ['paid', 'partially_refunded'], true));
@endphp
@if($canCreate && $cap->mode !== 'none' && $bal['available'] > 0 && $eligible)
    <button type="button" class="a-btn a-btn--danger" data-bs-toggle="modal" data-bs-target="#{{ $uid }}"><i class="fa fa-rotate-left"></i> Refund</button>
    <div class="modal fade" id="{{ $uid }}" tabindex="-1" aria-labelledby="{{ $uid }}t" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ $action }}" data-a-loading id="{{ $uid }}form" data-provider="{{ str_starts_with((string) $order->payment_method, 'fawaterak') ? 'fawaterak' : 'other' }}" style="display:flex;flex-direction:column;min-height:0;overflow:hidden;max-height:calc(100vh - 3.5rem)">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <div class="modal-header"><h5 class="modal-title" id="{{ $uid }}t">{{ $cap->isApi() ? 'Refund payment' : 'Record manual / external refund' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <dl class="a-kv">
                        <div><dt>Order</dt><dd>#{{ \App\Support\OrderPresenter::number($order) }}</dd></div>
                        <div><dt>Payment method</dt><dd>{{ $methodName ?: '—' }}</dd></div>
                        <div><dt>Provider</dt><dd>{{ $provider }}@if($attempt) · {{ $attempt->internal_reference ?: '#'.$attempt->id }}@endif</dd></div>
                        <div><dt>Original paid amount</dt><dd>{{ $minorToDec($bal['captured']) }} {{ $currency }}</dd></div>
                        <div><dt>Previously refunded / pending</dt><dd>{{ $minorToDec($bal['held']) }} {{ $currency }}</dd></div>
                        <div><dt>Remaining refundable</dt><dd><strong>{{ $minorToDec($bal['available']) }} {{ $currency }}</strong></dd></div>
                    </dl>
                    @if($bal['in_flight'])<div class="a-alert a-alert--warning" role="status">Another refund for this payment is still being processed; this request will be refused until it is resolved.</div>@endif
                    <x-admin.field label="Refund type" name="type" :for="$uid.'type'">
                        <select id="{{ $uid }}type" class="form-select" name="type" data-refund-type>
                            @if($bal['held'] === 0)<option value="full">Full refund ({{ $minorToDec($bal['captured']) }})</option>@endif
                            <option value="partial" @selected($bal['held'] > 0)>Partial refund</option>
                            @if($cap->canVoid && $bal['held'] === 0)<option value="void">Void (card, before settlement)</option>@endif
                        </select>
                    </x-admin.field>

                    @if($choices->isNotEmpty())
                    <fieldset class="a-field" data-refund-tickets>
                        <legend class="form-label">Tickets covered by this refund</legend>
                        <div class="a-hint" data-full-note>A full refund cancels <strong>all</strong> valid tickets of the order once it is confirmed.</div>
                        <div data-partial-only>
                            @foreach($choices as $c)
                                <label class="a-m-switch" style="margin-top:8px;{{ $c->selectable ? '' : 'opacity:.6' }}">
                                    <input class="form-check-input" type="checkbox" name="tickets[]" value="{{ $c->ticket->id }}" data-share="{{ $c->share_minor }}" @disabled(! $c->selectable) @checked(in_array($c->ticket->id, (array) old('tickets', [])))>
                                    <span><strong>#{{ $c->ticket->ticket_number }}</strong> · {{ $c->ticket->ticketTypeLabel() ?: $c->ticket->name }}@if($c->ticket->holder_name) · {{ $c->ticket->holder_name }}@endif
                                        <span class="a-hint d-block">{{ $minorToDec($c->share_minor) }} {{ $currency }}{{ $c->ticket->status === 'checked_in' ? ' · already checked in' : '' }}{{ $c->note ? ' · '.$c->note : '' }}</span></span>
                                </label>
                            @endforeach
                            <label class="a-m-switch" style="margin-top:8px"><input class="form-check-input" type="checkbox" name="no_tickets" value="1" @checked(old('no_tickets'))><span><strong>Refund money only — do not cancel any ticket</strong><span class="a-hint d-block">Use for fee or goodwill refunds. The tickets stay valid.</span></span></label>
                        </div>
                    </fieldset>
                    @endif

                    <div data-partial-only>
                    <x-admin.field label="Amount to refund" name="amount" :for="$uid.'amount'" :hint="'Up to '.$minorToDec($bal['available']).' '.$currency.'. Left empty, it defaults to the value of the selected tickets.'"><input id="{{ $uid }}amount" class="form-control" name="amount" inputmode="decimal" placeholder="e.g. 50.00" value="{{ old('amount') }}"></x-admin.field>
                    </div>
                    <x-admin.field label="Refund reason (required)" name="reason" :for="$uid.'reason'" hint="Shown to the scanner and in the customer's e-mail. Keep it short; no provider names, links or private details."><input id="{{ $uid }}reason" class="form-control" name="reason" minlength="5" maxlength="300" required value="{{ old('reason') }}"></x-admin.field>
                    @if(! $cap->isApi())
                    @unless($attempt)<x-admin.field label="Original Fawaterak invoice / transaction ID (legacy order)" name="original_reference" :for="$uid.'orig'" hint="Copy it from the Fawaterak dashboard. Used to open and match the transaction; nothing is guessed."><input id="{{ $uid }}orig" class="form-control" name="original_reference" maxlength="120" value="{{ old('original_reference') }}"></x-admin.field>@endunless
                    @endif
                    <div class="a-m-switch form-check form-switch"><input class="form-check-input" type="checkbox" id="{{ $uid }}ok" name="confirm" value="1" required><label class="form-check-label" for="{{ $uid }}ok"><strong>I confirm this refund request</strong><span class="a-hint d-block">{{ $cap->isApi() ? 'The request is sent through the original merchant account. It is not complete until the provider confirms it.' : 'Nothing is sent from here. Do the refund outside the system, then confirm it on the refund page.' }} Tickets and the order status change only after the refund is confirmed as completed.</span></label></div>
                    <div class="a-error" role="alert" data-refund-error hidden></div>
                    @error('refund')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer"><button type="button" class="a-btn a-btn--ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="a-btn a-btn--danger">{{ $needsApproval ? 'Submit for approval' : ($cap->isApi() ? 'Send refund' : 'Record refund request') }}</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade" id="{{ $uid }}wait" tabindex="-1" aria-labelledby="{{ $uid }}waitT" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="{{ $uid }}waitT">Waiting for <span data-w="provider">the provider</span></h5></div>
            <div class="modal-body" style="display:grid;gap:12px">
                <div class="a-alert a-alert--info" role="status"><strong>Refund <span data-w="ref"></span> was recorded: <span data-w="amount"></span>.</strong>
                    <div class="a-hint">Carry it out in <span data-w="provider2">the provider</span> now. Nothing is completed yet: tickets stay valid and the order stays unchanged until you confirm the refund.</div></div>
                <div data-w="tabnote" class="a-hint" hidden>The transaction page was opened in a new tab. If it did not open, use the button below.</div>
                <div data-w="idbox" hidden><span class="a-hint d-block">Original invoice / transaction ID</span><strong data-w="invoice"></strong></div>
            </div>
            <div class="modal-footer" style="flex-wrap:wrap;gap:8px">
                <button type="button" class="a-btn a-btn--outline" data-w="copy" hidden><i class="fa fa-copy"></i> Copy ID</button>
                <a class="a-btn a-btn--outline" data-w="reopen" target="_blank" rel="noopener noreferrer" hidden>Open in <span data-w="provider3">the provider</span></a>
                <a class="a-btn a-btn--primary" data-w="go">Open refund page</a>
            </div>
        </div></div>
    </div>
    @push('scripts')
    <script>
    (function () {
        var form = document.getElementById(@json($uid.'form')); if (!form) return;
        var type = form.querySelector('[data-refund-type]'), amount = form.querySelector('[name="amount"]');
        function sync() {
            var partial = type.value === 'partial';
            form.querySelectorAll('[data-partial-only]').forEach(function (el) { el.hidden = !partial; });
            var note = form.querySelector('[data-full-note]'); if (note) note.hidden = partial;
            if (amount) amount.disabled = !partial;
        }
        function total() {
            var sum = 0; form.querySelectorAll('input[name="tickets[]"]:checked').forEach(function (c) { sum += parseInt(c.dataset.share || '0', 10); });
            if (sum > 0 && amount && type.value === 'partial') { amount.value = (Math.floor(sum / 100) + '.' + ('0' + (sum % 100)).slice(-2)); }
        }
        form.querySelectorAll('input[name="tickets[]"]').forEach(function (c) { c.addEventListener('change', total); });
        type.addEventListener('change', sync); sync();
        var wait = document.getElementById(@json($uid.'wait')), errBox = form.querySelector('[data-refund-error]');
        function w(k) { return wait.querySelector('[data-w="' + k + '"]'); }
        function showError(msg) { errBox.textContent = msg; errBox.hidden = false; errBox.scrollIntoView({block: 'nearest'}); }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var btn = form.querySelector('button[type="submit"]'); errBox.hidden = true;
            // the provider tab must be opened inside this click, otherwise the browser blocks it as a pop-up
            var tab = form.dataset.provider === 'fawaterak' ? window.open('', '_blank') : null;
            if (btn) btn.disabled = true;
            var done = function () { if (btn) btn.disabled = false; };
            var fail = function (msg) { if (tab) tab.close(); showError(msg); done(); };
            fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
                .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return {status: r.status, j: j}; }); })
                .then(function (x) {
                    var j = x.j;
                    if (x.status === 401 || x.status === 419) return fail('Your session expired. Reload the page and sign in again.');
                    if (!j.ok) return fail(j.message || 'The refund could not be recorded. Nothing was changed.');
                    if (!j.waiting) { if (tab) tab.close(); location.href = j.redirect; return; }
                    var url = j.dashboard_url;
                    if (tab && url) { try { tab.opener = null; } catch (_) {} tab.location.href = url; } else if (tab) { tab.close(); }
                    if (url) fetch(j.opened_url, {method: 'POST', headers: {'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value, 'Accept': 'application/json'}, credentials: 'same-origin'}).catch(function () {});
                    ['provider', 'provider2', 'provider3'].forEach(function (k) { w(k).textContent = j.provider; });
                    w('ref').textContent = j.refund; w('amount').textContent = j.amount + ' ' + j.currency;
                    w('go').href = j.redirect;
                    var id = j.invoice_id || j.reference_number;
                    w('idbox').hidden = !id; w('invoice').textContent = id || '';
                    var copy = w('copy'); copy.hidden = !id; copy.onclick = function () { (navigator.clipboard ? navigator.clipboard.writeText(id) : Promise.reject()).then(function () { copy.textContent = 'Copied'; }).catch(function () { window.prompt('Copy the id:', id); }); };
                    var re = w('reopen'); re.hidden = !url; if (url) re.href = url;
                    w('tabnote').hidden = !url;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById(@json($uid))).hide();
                    bootstrap.Modal.getOrCreateInstance(wait).show();
                })
                .catch(function () { fail('Could not reach the server. Check your connection, then check the Refunds list before trying again.'); });
        });
        @if($errors->has('refund') && old('idempotency_key'))
        if (window.bootstrap) { new bootstrap.Modal(document.getElementById(@json($uid))).show(); }
        @endif
    })();
    </script>
    @endpush
@elseif($eligible && ($user->can('payments.refunds.view') || $user->can('payments.refunds.create')))
    {{-- The button is never silently missing: when a refund cannot be started, it is shown disabled and says why. --}}
    @php
        $open = \App\Models\PaymentRefund::query()->where('order_id', $order->id)->whereIn('status', \App\Models\PaymentRefund::HOLDING)
            ->when($attempt, fn ($q) => $q->where('payment_attempt_id', $attempt->id), fn ($q) => $q->whereNull('payment_attempt_id'))->latest('id')->first();
        $why = match (true) {
            ! $user->can('payments.refunds.create') => 'You do not have the refund permission (payments.refunds.create). Ask an administrator to grant it to your role.',
            ! \App\Orders\OrderAccess::canManage($order, $user) => 'You can only refund orders that are assigned to you and that you may manage.',
            $cap->mode === 'none' => (string) $cap->note,
            $bal['captured'] <= 0 => 'No paid amount is recorded on this payment, so there is nothing to refund.',
            $open !== null => 'A refund of '.$minorToDec($open->amount_minor).' '.$currency.' is already '.($open->status === 'succeeded' ? 'completed' : 'open ('.str_replace('_', ' ', $open->status).')').' for this payment — '.$open->internal_reference.'. Open it from the Refunds section below.',
            default => 'Nothing is left to refund on this payment.',
        };
    @endphp
    <button type="button" class="a-btn a-btn--danger" data-bs-toggle="modal" data-bs-target="#{{ $uid }}x"><i class="fa fa-rotate-left"></i> Refund</button>
    <div class="modal fade" id="{{ $uid }}x" tabindex="-1" aria-labelledby="{{ $uid }}xt" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="{{ $uid }}xt">Refund is not available</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="a-alert a-alert--warning" role="alert">{{ $why }}</div></div>
            <div class="modal-footer">
                @if($open && $user->can('payments.refunds.view'))<a class="a-btn a-btn--primary" href="{{ route('admin.payments.refunds.show', $open) }}">Open refund {{ $open->internal_reference }}</a>@endif
                <button type="button" class="a-btn a-btn--outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div></div>
    </div>
@endif
