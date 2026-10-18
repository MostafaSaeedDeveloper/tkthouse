@php
    $user = auth()->user();
    $svc = app(\App\Services\Payments\Refunds\RefundService::class);
    $cap = $svc->capability($attempt);
    $bal = $svc->balance($attempt);
    $canCreate = $user->can('payments.refunds.create') && \App\Orders\OrderAccess::canManage($order, $user);
    $minorToDec = fn (int $m) => \App\Models\PaymentRefund::minorToDecimal($m);
    $uid = 'rf'.$attempt->id;
    $needsApproval = $svc->approvalRequired();
@endphp
@if($canCreate && $cap->mode !== 'none' && $bal['available'] > 0 && $attempt->status === 'paid')
    <button type="button" class="a-btn a-btn--danger" data-bs-toggle="modal" data-bs-target="#{{ $uid }}"><i class="fa fa-rotate-left"></i> {{ $cap->isApi() ? 'Refund' : 'Record manual refund' }}</button>
    <div class="modal fade" id="{{ $uid }}" tabindex="-1" aria-labelledby="{{ $uid }}t" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('admin.payments.refunds.store', $attempt) }}" data-a-loading>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <div class="modal-header"><h5 class="modal-title" id="{{ $uid }}t">{{ $cap->isApi() ? 'Refund payment' : 'Record manual / external refund' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <dl class="a-kv">
                        <div><dt>Payment</dt><dd>{{ $attempt->internal_reference ?: '#'.$attempt->id }} · {{ ucfirst($attempt->provider) }}</dd></div>
                        <div><dt>Captured amount</dt><dd>{{ $minorToDec($bal['captured']) }} {{ $attempt->currency }}</dd></div>
                        <div><dt>Already refunded / pending</dt><dd>{{ $minorToDec($bal['held']) }} {{ $attempt->currency }}</dd></div>
                        <div><dt>Available to refund</dt><dd><strong>{{ $minorToDec($bal['available']) }} {{ $attempt->currency }}</strong></dd></div>
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
                    <x-admin.field label="Amount (for partial)" name="amount" :for="$uid.'amount'" :hint="'Up to '.$minorToDec($bal['available']).' '.$attempt->currency"><input id="{{ $uid }}amount" class="form-control" name="amount" inputmode="decimal" placeholder="e.g. 50.00" value="{{ old('amount') }}"></x-admin.field>
                    <x-admin.field label="Reason (required)" name="reason" :for="$uid.'reason'"><input id="{{ $uid }}reason" class="form-control" name="reason" minlength="5" maxlength="300" required value="{{ old('reason') }}"></x-admin.field>
                    <div class="a-m-switch form-check form-switch"><input class="form-check-input" type="checkbox" id="{{ $uid }}ok" name="confirm" value="1" required><label class="form-check-label" for="{{ $uid }}ok"><strong>I confirm this refund</strong><span class="a-hint d-block">{{ $cap->isApi() ? 'The request is sent through the original merchant account. It is not complete until the provider confirms it. The order and tickets are not changed.' : 'Nothing is sent from here. Do the refund in the provider portal, then confirm it on the refund page. The order and tickets are not changed.' }}</span></label></div>
                    @error('refund')<div class="a-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer"><button type="button" class="a-btn a-btn--ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="a-btn a-btn--danger">{{ $needsApproval ? 'Submit for approval' : ($cap->isApi() ? 'Send refund' : 'Record refund request') }}</button></div>
            </form>
        </div></div>
    </div>
@elseif($user->can('payments.refunds.view') && $attempt->status === 'paid' && $cap->mode === 'none')
    <span class="a-hint">{{ $cap->note }}</span>
@endif
