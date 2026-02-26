# TKT House

Laravel-based ticketing platform with:
- Front events listing + checkout
- Admin dashboard (events, orders, tickets, reports, settings)
- Customer dashboard (orders, tickets, wallet, referral)
- API check-in endpoints for gate scanners

## Setup
1. `cp .env.example .env`
2. Configure DB + mail settings.
3. `composer install`
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan serve`

## Default roles
`super_admin`, `admin`, `organizer`, `cashier`, `support`, `customer`.

## Services placeholders
- **WhatsAppService** is currently a stub that logs outgoing messages.
- **TicketsPdfService** writes generated ticket payload under `storage/app/tickets/{event_id}`.
- **PaymentService** currently includes mark-as-paid flow and hooks into ticket generation + delivery.

## API
- `POST /api/checkin/verify`
- `POST /api/checkin/confirm`

(Protected via authenticated middleware in current scaffold; can be switched to Sanctum token auth for gate devices.)
