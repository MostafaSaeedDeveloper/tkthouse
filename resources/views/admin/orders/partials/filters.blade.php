{{-- Search + advanced filters shared by Orders and Deleted Orders. Expects: $action, $reset, $statusOptions, $paymentMethods, $events, $canFilterByEvent, $perPageOptions. --}}
@php
    $advancedKeys = ['reference', 'customer', 'status', 'payment_method', 'event_id', 'date_from', 'date_to', 'assignee'];
    $advancedActive = collect($advancedKeys)->filter(fn ($key) => filled(request($key)))->count();
    $anyActive = $advancedActive > 0 || filled(request('search')) || filled(request('group')) || (int) request('per_page') > 0;
    $idp = $idp ?? 'o';
@endphp
<form method="GET" action="{{ $action }}" class="a-ord-filters" role="search" data-a-loading-table>
    @foreach(['group', 'sort', 'dir', 'refunded', 'stats', 'refunded_from', 'refunded_to', 'refund_event_id'] as $keep)
        @if(filled(request($keep)))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
    @endforeach
    <div class="a-ord-filters__bar">
        <div class="a-ord-search">
            <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
            <label class="a-sr-only" for="{{ $idp }}Search">Search orders</label>
            <input type="search" id="{{ $idp }}Search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search order # or customer" autocomplete="off">
        </div>
        <button type="button" class="a-btn a-btn--outline" data-ord-filter-toggle aria-expanded="{{ $advancedActive ? 'true' : 'false' }}" aria-controls="{{ $idp }}Advanced">
            <i class="fa fa-sliders" aria-hidden="true"></i> Filters
            @if($advancedActive)<span class="a-ord-count-pill">{{ $advancedActive }}</span>@endif
        </button>
        <button type="submit" class="a-btn a-btn--dark">Search</button>
        @if($anyActive)<a class="a-btn a-btn--ghost" href="{{ $reset }}"><i class="fa fa-xmark" aria-hidden="true"></i> Clear filters</a>@endif
    </div>

    <div class="a-ord-filters__panel" id="{{ $idp }}Advanced" @unless($advancedActive) hidden @endunless>
        <div class="a-field"><label class="form-label" for="{{ $idp }}Ref">Order reference</label>
            <input type="text" id="{{ $idp }}Ref" name="reference" value="{{ request('reference') }}" class="form-control" placeholder="e.g. 1234567890"></div>
        <div class="a-field"><label class="form-label" for="{{ $idp }}Cust">Customer</label>
            <input type="text" id="{{ $idp }}Cust" name="customer" value="{{ request('customer') }}" class="form-control" placeholder="Name, email or phone"></div>
        @if($canFilterByEvent)
        <div class="a-field"><label class="form-label" for="{{ $idp }}Event">Event</label>
            <select name="event_id" id="{{ $idp }}Event" class="form-select">
                <option value="">All events</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->filterLabel() }}</option>
                @endforeach
            </select></div>
        @endif
        <div class="a-field"><label class="form-label" for="{{ $idp }}Status">Order status</label>
            <select name="status" id="{{ $idp }}Status" class="form-select">
                <option value="">All statuses</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
        @if(!empty($assignmentOn) && ($assignees ?? collect())->isNotEmpty() || (!empty($assignmentOn) && \App\Orders\OrderAccess::level(auth()->user()) === \App\Orders\OrderAccess::ALL))
        <div class="a-field"><label class="form-label" for="{{ $idp }}Assignee">Assigned to</label>
            <select name="assignee" id="{{ $idp }}Assignee" class="form-select">
                <option value="">Anyone</option>
                <option value="unassigned" @selected(request('assignee') === 'unassigned')>Unassigned</option>
                <option value="me" @selected(request('assignee') === 'me')>Me</option>
                @foreach(($assignees ?? collect()) as $a)<option value="{{ $a->id }}" @selected((string) request('assignee') === (string) $a->id)>{{ $a->name }}</option>@endforeach
            </select></div>
        @endif
        <div class="a-field"><label class="form-label" for="{{ $idp }}Pay">Payment method</label>
            <select name="payment_method" id="{{ $idp }}Pay" class="form-select">
                <option value="">All methods</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->code }}" @selected(request('payment_method') === $method->code)>{{ $method->checkout_label ?: $method->name }}</option>
                @endforeach
            </select></div>
        <div class="a-field"><label class="form-label" for="{{ $idp }}From">Created from</label>
            <input type="date" id="{{ $idp }}From" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="a-field"><label class="form-label" for="{{ $idp }}To">Created to</label>
            <input type="date" id="{{ $idp }}To" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="a-field"><label class="form-label" for="{{ $idp }}PerPage">Rows per page</label>
            <select name="per_page" id="{{ $idp }}PerPage" class="form-select">
                @foreach($perPageOptions as $n)
                    <option value="{{ $n }}" @selected((int) request('per_page', $perPageOptions[0]) === $n)>{{ $n }}</option>
                @endforeach
            </select></div>
        <div class="a-ord-filters__actions">
            <button type="submit" class="a-btn a-btn--primary">Apply filters</button>
            <a class="a-btn a-btn--ghost" href="{{ $reset }}">Clear</a>
        </div>
    </div>
</form>
