@extends('front.layout.master')

@section('content')
<div class="sub-banner">
    <div class="container">
        <h6>Checkout</h6>
    </div>
</div>

<section class="checkout-page py-5" style="background:#090909;color:#fff;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(($mode ?? 'open') === 'event_locked')
            @php
                $units = collect($eventSelection['units'] ?? []);
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <h4 class="mb-3 text-warning">Customer Info</h4>
                        <div class="mb-2"><input class="form-control" name="first_name" placeholder="First name" value="{{ old('first_name') }}" required></div>
                        <div class="mb-2"><input class="form-control" name="last_name" placeholder="Last name" value="{{ old('last_name') }}" required></div>
                        <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required></div>
                        <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone') }}"></div>
                        <div class="mb-2"><input class="form-control" name="address" placeholder="Address" value="{{ old('address') }}"></div>
                    </div>

                    <div class="col-md-8">
                        <h4 class="mb-3 text-warning">Attendee Tickets (from event selection)</h4>

                        @foreach($units as $index => $unit)
                            <div class="card bg-dark border-secondary mb-3">
                                <div class="card-body">
                                    <h5 class="text-warning mb-3">
                                        Ticket {{ $index + 1 }} ({{ $unit['ticket_name'] }})
                                        <span class="text-light" style="font-size:14px;">- {{ number_format($unit['ticket_price'],2) }}</span>
                                    </h5>

                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" name="attendees[{{ $index }}][name]" value="{{ old('attendees.'.$index.'.name') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="text" class="form-control" name="attendees[{{ $index }}][phone]" value="{{ old('attendees.'.$index.'.phone') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="attendees[{{ $index }}][email]" value="{{ old('attendees.'.$index.'.email') }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Gender</label>
                                            <select class="form-select" name="attendees[{{ $index }}][gender]" required>
                                                <option value="">Select</option>
                                                <option value="male" @selected(old('attendees.'.$index.'.gender') === 'male')>Male</option>
                                                <option value="female" @selected(old('attendees.'.$index.'.gender') === 'female')>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Social Profile (Optional)</label>
                                            <input type="text" class="form-control" name="attendees[{{ $index }}][social_profile]" value="{{ old('attendees.'.$index.'.social_profile') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-warning w-100">Place Order</button>
                    </div>
                </div>
            </form>
        @else
            @php
                $eventTickets = $eventTickets ?? collect();
                $legacyTickets = $legacyTickets ?? collect();
                $oldItems = old('items', [[]]);
                if (! is_array($oldItems) || empty($oldItems)) {
                    $oldItems = [[]];
                }
            @endphp

            <form method="POST" action="{{ route('front.checkout.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-5">
                        <h4 class="mb-3 text-warning">Customer Info</h4>
                        <div class="mb-2"><input class="form-control" name="first_name" placeholder="First name" value="{{ old('first_name') }}" required></div>
                        <div class="mb-2"><input class="form-control" name="last_name" placeholder="Last name" value="{{ old('last_name') }}" required></div>
                        <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required></div>
                        <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone" value="{{ old('phone') }}"></div>
                        <div class="mb-2"><input class="form-control" name="address" placeholder="Address" value="{{ old('address') }}"></div>
                    </div>

                    <div class="col-md-7">
                        <h4 class="mb-3 text-warning">Tickets</h4>

                        <div id="ticket-rows">
                            @foreach($oldItems as $i => $item)
                                <div class="card bg-dark border-secondary mb-3 ticket-row" data-row>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label">Ticket</label>
                                                <select name="items[{{ $i }}][ticket_key]" class="form-select" required>
                                                    <option value="">Select ticket</option>

                                                    @if($eventTickets->isNotEmpty())
                                                        <optgroup label="Event Tickets">
                                                            @foreach($eventTickets as $ticket)
                                                                <option value="event:{{ $ticket->id }}" @selected(($item['ticket_key'] ?? null) === 'event:'.$ticket->id)>
                                                                    {{ $ticket->event?->name ? $ticket->event->name.' - ' : '' }}{{ $ticket->name }} - {{ number_format($ticket->price,2) }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif

                                                    @if($legacyTickets->isNotEmpty())
                                                        <optgroup label="General Tickets">
                                                            @foreach($legacyTickets as $ticket)
                                                                <option value="legacy:{{ $ticket->id }}" @selected(($item['ticket_key'] ?? null) === 'legacy:'.$ticket->id)>
                                                                    {{ $ticket->name }} - {{ number_format($ticket->price,2) }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Qty</label>
                                                <input type="number" min="1" class="form-control" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Holder Name</label>
                                                <input type="text" class="form-control" name="items[{{ $i }}][holder_name]" value="{{ $item['holder_name'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Holder Email</label>
                                                <input type="email" class="form-control" name="items[{{ $i }}][holder_email]" value="{{ $item['holder_email'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Holder Phone</label>
                                                <input type="text" class="form-control" name="items[{{ $i }}][holder_phone]" value="{{ $item['holder_phone'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-outline-danger w-100 remove-row">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" id="add-ticket-row" class="btn btn-outline-warning w-100 mb-3">+ Add Another Ticket</button>
                        <button type="submit" class="btn btn-warning w-100">Place Order</button>
                    </div>
                </div>
            </form>

            <template id="ticket-row-template">
                <div class="card bg-dark border-secondary mb-3 ticket-row" data-row>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">Ticket</label>
                                <select class="form-select" data-name="ticket_key" required>
                                    <option value="">Select ticket</option>

                                    @if($eventTickets->isNotEmpty())
                                        <optgroup label="Event Tickets">
                                            @foreach($eventTickets as $ticket)
                                                <option value="event:{{ $ticket->id }}">
                                                    {{ $ticket->event?->name ? $ticket->event->name.' - ' : '' }}{{ $ticket->name }} - {{ number_format($ticket->price,2) }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif

                                    @if($legacyTickets->isNotEmpty())
                                        <optgroup label="General Tickets">
                                            @foreach($legacyTickets as $ticket)
                                                <option value="legacy:{{ $ticket->id }}">{{ $ticket->name }} - {{ number_format($ticket->price,2) }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Qty</label>
                                <input type="number" min="1" class="form-control" data-name="quantity" value="1" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Holder Name</label>
                                <input type="text" class="form-control" data-name="holder_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Holder Email</label>
                                <input type="email" class="form-control" data-name="holder_email" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Holder Phone</label>
                                <input type="text" class="form-control" data-name="holder_phone">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger w-100 remove-row">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <script>
            (function () {
                var rowsContainer = document.getElementById('ticket-rows');
                var addBtn = document.getElementById('add-ticket-row');
                var template = document.getElementById('ticket-row-template');

                if (!rowsContainer || !addBtn || !template) {
                    return;
                }

                function reindexRows() {
                    var rows = rowsContainer.querySelectorAll('[data-row]');
                    rows.forEach(function (row, index) {
                        row.querySelectorAll('[data-name]').forEach(function (field) {
                            field.setAttribute('name', 'items[' + index + '][' + field.getAttribute('data-name') + ']');
                        });
                    });
                }

                function bindRemoveButtons() {
                    rowsContainer.querySelectorAll('.remove-row').forEach(function (btn) {
                        btn.onclick = function () {
                            var rows = rowsContainer.querySelectorAll('[data-row]');
                            if (rows.length <= 1) {
                                return;
                            }

                            btn.closest('[data-row]').remove();
                            reindexRows();
                        };
                    });
                }

                addBtn.addEventListener('click', function () {
                    var fragment = template.content.cloneNode(true);
                    rowsContainer.appendChild(fragment);
                    reindexRows();
                    bindRemoveButtons();
                });

                reindexRows();
                bindRemoveButtons();
            })();
            </script>
        @endif
    </div>
</section>
@endsection
