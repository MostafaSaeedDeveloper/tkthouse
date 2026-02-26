@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>Checkout</h6>
    </div>
</div>

<section class="checkout-page py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $buyer = $buyer ?? ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address' => ''];
        @endphp

        @if(($mode ?? 'open') === 'event_locked')
            @php
                $units = collect($eventSelection['units'] ?? []);
                $requiresApproval = (bool) ($eventSelection['requires_approval'] ?? true);
            @endphp
            <form method="POST" action="{{ route('front.checkout.store') }}">
                @csrf
                <div class="row g-4">
                    <div class="col-md-4">
                        <h4 class="mb-3 text-warning">Buyer Information</h4>
                        <div class="mb-2"><input class="form-control" name="first_name" placeholder="First name" value="{{ old('first_name', $buyer['first_name']) }}" required></div>
                        <div class="mb-2"><input class="form-control" name="last_name" placeholder="Last name" value="{{ old('last_name', $buyer['last_name']) }}" required></div>
                        <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" value="{{ old('email', $buyer['email']) }}" required></div>
                        <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone', $buyer['phone']) }}"></div>
                        <div class="mb-2"><input class="form-control" name="address" placeholder="Address" value="{{ old('address', $buyer['address']) }}"></div>

                        <div class="card bg-dark border-secondary mt-4">
                            <div class="card-body">
                                <h5 class="text-warning mb-3">Order Review</h5>
                                <ul class="mb-2">
                                    @foreach($units as $unit)
                                        <li>{{ $unit['event_name'] }} - {{ $unit['ticket_name'] }} ({{ number_format($unit['ticket_price'],2) }})</li>
                                    @endforeach
                                </ul>
                                <strong>Total: {{ number_format($units->sum('ticket_price'), 2) }}</strong>
                                <hr>
                                <h6>Payment Method</h6>
                                @if($requiresApproval)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" checked disabled>
                                        <label class="form-check-label">Pending Review (default)</label>
                                    </div>
                                @else
                                    <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="visa" @checked(old('payment_method') === 'visa') required><label class="form-check-label">Visa</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="wallet" @checked(old('payment_method') === 'wallet') required><label class="form-check-label">Wallet</label></div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <h4 class="mb-3 text-warning">Attendee Details</h4>
                        @foreach($units as $index => $unit)
                            <div class="card bg-dark border-secondary mb-3">
                                <div class="card-body">
                                    <h5 class="text-warning mb-3">Ticket {{ $index + 1 }} ({{ $unit['ticket_name'] }})</h5>
                                    <div class="row g-2">
                                        <div class="col-md-6"><label class="form-label">Name</label><input type="text" class="form-control" name="attendees[{{ $index }}][name]" value="{{ old('attendees.'.$index.'.name') }}" required></div>
                                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" class="form-control" name="attendees[{{ $index }}][phone]" value="{{ old('attendees.'.$index.'.phone') }}" required></div>
                                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="attendees[{{ $index }}][email]" value="{{ old('attendees.'.$index.'.email') }}" required></div>
                                        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="attendees[{{ $index }}][gender]" required><option value="">Select</option><option value="male" @selected(old('attendees.'.$index.'.gender') === 'male')>Male</option><option value="female" @selected(old('attendees.'.$index.'.gender') === 'female')>Female</option></select></div>
                                        <div class="col-md-3"><label class="form-label">Social Profile</label><input type="text" class="form-control" name="attendees[{{ $index }}][social_profile]" value="{{ old('attendees.'.$index.'.social_profile') }}"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-warning w-100">{{ $requiresApproval ? 'Send Order' : 'Complete Order' }}</button>
                    </div>
                </div>
            </form>
        @else
            @php
                $eventTickets = $eventTickets ?? collect();
                $legacyTickets = $legacyTickets ?? collect();
                $oldItems = old('items', [[]]);
                if (!is_array($oldItems) || empty($oldItems)) $oldItems = [[]];
            @endphp
            <form method="POST" action="{{ route('front.checkout.store') }}" id="open-checkout-form">
                @csrf
                <div class="row g-4">
                    <div class="col-md-5">
                        <h4 class="mb-3 text-warning">Buyer Information</h4>
                        <div class="mb-2"><input class="form-control" name="first_name" placeholder="First name" value="{{ old('first_name', $buyer['first_name']) }}" required></div>
                        <div class="mb-2"><input class="form-control" name="last_name" placeholder="Last name" value="{{ old('last_name', $buyer['last_name']) }}" required></div>
                        <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" value="{{ old('email', $buyer['email']) }}" required></div>
                        <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone', $buyer['phone']) }}"></div>
                        <div class="mb-2"><input class="form-control" name="address" placeholder="Address" value="{{ old('address', $buyer['address']) }}"></div>

                        <div class="card bg-dark border-secondary mt-4">
                            <div class="card-body">
                                <h5 class="text-warning mb-3">Payment Method</h5>
                                <div id="pending-review-box">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" checked disabled>
                                        <label class="form-check-label">Pending Review (default)</label>
                                    </div>
                                </div>
                                <div id="pay-now-box" style="display:none;">
                                    <div class="form-check"><input class="form-check-input payment-method-input" type="radio" name="payment_method" value="visa"><label class="form-check-label">Visa</label></div>
                                    <div class="form-check"><input class="form-check-input payment-method-input" type="radio" name="payment_method" value="wallet"><label class="form-check-label">Wallet</label></div>
                                </div>
                                <div class="small text-muted mt-2" id="checkout-flow-note"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h4 class="mb-3 text-warning">Tickets & Order Review</h4>
                        <div id="ticket-rows">
                            @foreach($oldItems as $i => $item)
                                <div class="card bg-dark border-secondary mb-3 ticket-row" data-row>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-5"><label class="form-label">Ticket</label>
                                                <select class="form-select ticket-select" name="items[{{ $i }}][ticket_key]" required>
                                                    <option value="">Select ticket</option>
                                                    @if($eventTickets->isNotEmpty())
                                                        <optgroup label="Event Tickets">
                                                            @foreach($eventTickets as $ticket)
                                                                <option value="event:{{ $ticket->id }}" data-requires-approval="{{ $ticket->event?->requires_booking_approval ? '1' : '0' }}" @selected(($item['ticket_key'] ?? '') === 'event:'.$ticket->id)>{{ $ticket->event?->name ? $ticket->event->name.' - ' : '' }}{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    @if($legacyTickets->isNotEmpty())
                                                        <optgroup label="General Tickets">
                                                            @foreach($legacyTickets as $ticket)
                                                                <option value="legacy:{{ $ticket->id }}" data-requires-approval="0" @selected(($item['ticket_key'] ?? '') === 'legacy:'.$ticket->id)>{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="col-md-2"><label class="form-label">Qty</label><input type="number" min="1" class="form-control" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required></div>
                                            <div class="col-md-5"><label class="form-label">Holder Name</label><input type="text" class="form-control" name="items[{{ $i }}][holder_name]" value="{{ $item['holder_name'] ?? '' }}" required></div>
                                            <div class="col-md-6"><label class="form-label">Holder Email</label><input type="email" class="form-control" name="items[{{ $i }}][holder_email]" value="{{ $item['holder_email'] ?? '' }}" required></div>
                                            <div class="col-md-4"><label class="form-label">Holder Phone</label><input type="text" class="form-control" name="items[{{ $i }}][holder_phone]" value="{{ $item['holder_phone'] ?? '' }}"></div>
                                            <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100 remove-row">Remove</button></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-ticket-row" class="btn btn-outline-warning w-100 mb-3">+ Add Another Ticket</button>
                        <button type="submit" class="btn btn-warning w-100" id="submit-order-btn">Send Order</button>
                    </div>
                </div>
            </form>

            <template id="ticket-row-template">
                <div class="card bg-dark border-secondary mb-3 ticket-row" data-row>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-5"><label class="form-label">Ticket</label>
                                <select class="form-select ticket-select" data-name="ticket_key" required>
                                    <option value="">Select ticket</option>
                                    @if($eventTickets->isNotEmpty())
                                        <optgroup label="Event Tickets">
                                            @foreach($eventTickets as $ticket)
                                                <option value="event:{{ $ticket->id }}" data-requires-approval="{{ $ticket->event?->requires_booking_approval ? '1' : '0' }}">{{ $ticket->event?->name ? $ticket->event->name.' - ' : '' }}{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if($legacyTickets->isNotEmpty())
                                        <optgroup label="General Tickets">
                                            @foreach($legacyTickets as $ticket)
                                                <option value="legacy:{{ $ticket->id }}" data-requires-approval="0">{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2"><label class="form-label">Qty</label><input type="number" min="1" class="form-control" data-name="quantity" value="1" required></div>
                            <div class="col-md-5"><label class="form-label">Holder Name</label><input type="text" class="form-control" data-name="holder_name" required></div>
                            <div class="col-md-6"><label class="form-label">Holder Email</label><input type="email" class="form-control" data-name="holder_email" required></div>
                            <div class="col-md-4"><label class="form-label">Holder Phone</label><input type="text" class="form-control" data-name="holder_phone"></div>
                            <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100 remove-row">Remove</button></div>
                        </div>
                    </div>
                </div>
            </template>

            <script>
                (function () {
                    const rowsContainer = document.getElementById('ticket-rows');
                    const addBtn = document.getElementById('add-ticket-row');
                    const template = document.getElementById('ticket-row-template');
                    const payNowBox = document.getElementById('pay-now-box');
                    const pendingReviewBox = document.getElementById('pending-review-box');
                    const submitOrderBtn = document.getElementById('submit-order-btn');
                    const note = document.getElementById('checkout-flow-note');

                    const reindexRows = () => {
                        rowsContainer.querySelectorAll('[data-row]').forEach((row, index) => {
                            row.querySelectorAll('[data-name]').forEach((field) => {
                                field.name = `items[${index}][${field.dataset.name}]`;
                            });
                        });
                    };

                    const bindRemoveButtons = () => {
                        rowsContainer.querySelectorAll('.remove-row').forEach((btn) => {
                            btn.onclick = () => {
                                if (rowsContainer.querySelectorAll('[data-row]').length <= 1) return;
                                btn.closest('[data-row]').remove();
                                reindexRows();
                                updateCheckoutFlow();
                            };
                        });
                    };

                    const updateCheckoutFlow = () => {
                        const selectedOptions = [...rowsContainer.querySelectorAll('.ticket-select')]
                            .map((select) => select.options[select.selectedIndex])
                            .filter(Boolean);

                        const requiresApproval = selectedOptions.some((option) => option.dataset.requiresApproval === '1');
                        payNowBox.style.display = requiresApproval ? 'none' : 'block';
                        pendingReviewBox.style.display = requiresApproval ? 'block' : 'none';
                        document.querySelectorAll('.payment-method-input').forEach((input) => {
                            input.required = !requiresApproval;
                            if (requiresApproval) input.checked = false;
                        });
                        submitOrderBtn.textContent = requiresApproval ? 'Send Order' : 'Complete Order';
                        note.textContent = requiresApproval
                            ? 'This order includes at least one event that requires admin approval before payment.'
                            : 'This order can be paid immediately at checkout.';
                    };

                    addBtn.addEventListener('click', () => {
                        rowsContainer.appendChild(template.content.cloneNode(true));
                        reindexRows();
                        bindRemoveButtons();
                        rowsContainer.querySelectorAll('.ticket-select').forEach((select) => {
                            select.onchange = updateCheckoutFlow;
                        });
                        updateCheckoutFlow();
                    });

                    reindexRows();
                    bindRemoveButtons();
                    rowsContainer.querySelectorAll('.ticket-select').forEach((select) => {
                        select.onchange = updateCheckoutFlow;
                    });
                    updateCheckoutFlow();
                })();
            </script>
        @endif
    </div>
</section>
@endsection
