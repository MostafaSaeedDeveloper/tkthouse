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
            <form method="POST" action="{{ $action }}" data-a-loading id="{{ $uid }}form">
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
                    @if($cap->note)<div class="a-alert a-alert--warning" role="status">{{ $cap->note }}</div>@endif
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
                    <x-admin.field label="Refund reason (required)" name="reason" :for="$uid.'reason'" hint="Shown to scanner staff on a refunded ticket AND in the customer's refund e-mail, so keep it short and factual. Never put provider names, links, ids or private details here (they are replaced by a generic sentence in the e-mail). Use Internal notes for anything private."><input id="{{ $uid }}reason" class="form-control" name="reason" minlength="5" maxlength="300" required value="{{ old('reason') }}"></x-admin.field>
                    <x-admin.field label="Internal notes" name="internal_notes" :for="$uid.'notes'" hint="Staff only. Never shown to scanners or customers."><textarea id="{{ $uid }}notes" class="form-control" name="internal_notes" rows="2" maxlength="2000">{{ old('internal_notes') }}</textarea></x-admin.field>
                    @if(! $cap->isApi())
                    <x-admin.field label="Refund method" name="external_method" :for="$uid.'em'"><select id="{{ $uid }}em" class="form-select" name="external_method">@foreach(\App\Models\PaymentRefund::EXTERNAL_METHODS as $k => $label)<option value="{{ $k }}" @selected(old('external_method', $attempt ? 'provider_portal' : 'cash') === $k)>{{ $label }}</option>@endforeach</select></x-admin.field>
                    @unless($attempt)<x-admin.field label="Original Fawaterak invoice / transaction ID (legacy order)" name="original_reference" :for="$uid.'orig'" hint="Copy it from the Fawaterak dashboard. Used to open and match the transaction; nothing is guessed."><input id="{{ $uid }}orig" class="form-control" name="original_reference" maxlength="120" value="{{ old('original_reference') }}"></x-admin.field>@endunless
                    <x-admin.field label="Provider / external reference (if already known)" name="provider_reference" :for="$uid.'ref'" hint="You can add or correct it when you confirm the refund."><input id="{{ $uid }}ref" class="form-control" name="provider_reference" maxlength="120" value="{{ old('provider_reference') }}"></x-admin.field>
                    <x-admin.field label="Actual refund date (if already refunded)" name="refunded_at" :for="$uid.'rd'" hint="Required when you confirm. Not in the future."><input id="{{ $uid }}rd" class="form-control" type="date" name="refunded_at" max="{{ now()->toDateString() }}" value="{{ old('refunded_at') }}"></x-admin.field>
                    @endif
                    <div class="a-m-switch form-check form-switch"><input class="form-check-input" type="checkbox" id="{{ $uid }}ok" name="confirm" value="1" required><label class="form-check-label" for="{{ $uid }}ok"><strong>I confirm this refund request</strong><span class="a-hint d-block">{{ $cap->isApi() ? 'The request is sent through the original merchant account. It is not complete until the provider confirms it.' : 'Nothing is sent from here. Do the refund outside the system, then confirm it on the refund page.' }} Tickets and the order status change only after the refund is confirmed as completed.</span></label></div>
                    @error('refund')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer"><button type="button" class="a-btn a-btn--ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="a-btn a-btn--danger">{{ $needsApproval ? 'Submit for approval' : ($cap->isApi() ? 'Send refund' : 'Record refund request') }}</button></div>
            </form>
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
        @if($errors->has('refund') && old('idempotency_key'))
        if (window.bootstrap) { new bootstrap.Modal(document.getElementById(@json($uid))).show(); }
        @endif
    })();
    </script>
    @endpush
@elseif($user->can('payments.refunds.view') && $eligible && $cap->mode === 'none')
    <span class="a-hint">{{ $cap->note }}</span>
@endif
