@extends('admin.master')

@section('content')
<div class="content">
    @include('admin.partials.flash')
    <div class="block block-rounded">
        <div class="block-header block-header-default">
            <h3 class="block-title">Ticket QR Scanner</h3>
        </div>
        <div class="block-content">
            <form method="POST" action="{{ route('admin.tickets.scanner.lookup') }}" class="row g-2 mb-4">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">Scan QR or Enter Ticket Number</label>
                    <input type="text" class="form-control" id="scanner-code" name="code" value="{{ old('code', $lastCode ?? '') }}" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Find Ticket</button>
                </div>
            </form>

            <div id="reader" style="max-width:420px"></div>

            @isset($ticket)
                <hr>
                <h5>Ticket Details</h5>
                <p><strong>Ticket #:</strong> {{ $ticket->ticket_number }}</p>
                <p><strong>Name:</strong> {{ $ticket->name }}</p>
                <p><strong>Holder:</strong> {{ $ticket->holder_name }}</p>
                <p><strong>Email:</strong> {{ $ticket->holder_email }}</p>
                <p><strong>Phone:</strong> {{ $ticket->holder_phone }}</p>
                <p><strong>Order #:</strong> {{ $ticket->order?->order_number ?? '-' }}</p>
                <p><strong>Status:</strong> {{ str($ticket->status)->replace('_', ' ')->title() }}</p>

                <form method="POST" action="{{ route('admin.tickets.scanner.status', $ticket) }}" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <button name="status" value="checked_in" class="btn btn-success" type="submit">Check In</button>
                    <button name="status" value="not_checked_in" class="btn btn-warning" type="submit">Check Out</button>
                    <button name="status" value="canceled" class="btn btn-danger" type="submit">Cancel</button>
                    <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-alt-info">Open Ticket</a>
                </form>
            @endisset
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(() => {
    const input = document.getElementById('scanner-code');
    if (!window.Html5Qrcode || !document.getElementById('reader')) {
        return;
    }

    const html5QrCode = new Html5Qrcode('reader');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 8, qrbox: 220 },
        (decodedText) => {
            input.value = decodedText;
        },
        () => {}
    ).catch(() => {});
})();
</script>
@endpush
