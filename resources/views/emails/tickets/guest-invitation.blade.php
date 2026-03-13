@php
    $guest = trim((string) ($guestName ?? 'Guest')) ?: 'Guest';
    $event = trim((string) ($eventName ?? 'the event')) ?: 'the event';
@endphp

Hello {{ $guest }},

You have been selected to attend {{ $event }}.

Your invitation ticket is attached to this email as a PDF document.

Please present the QR code at the entrance to access the event.
