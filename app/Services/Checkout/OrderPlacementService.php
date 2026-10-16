<?php

namespace App\Services\Checkout;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Creates the order for a validated checkout. Everything that affects money is recomputed here. */
class OrderPlacementService
{
    public function __construct(
        private readonly CheckoutSelection $selection,
        private readonly CheckoutPricing $pricing,
    ) {
    }

    /**
     * @param Collection<int, array<string, mixed>> $units units stored in the session (only ticket ids/counts are trusted)
     * @param array{first_name: string, last_name: string, email: string, phone?: ?string} $buyer
     * @param array<int, array<string, string>> $attendees one entry per unit, same order
     * @param float|null $expectedTotal total the customer saw on the review step; a different current total aborts the order
     */
    public function place(User $user, Event $event, Collection $units, array $buyer, array $attendees, ?string $promoCode, ?string $methodCode, ?float $expectedTotal = null, ?int $affiliateUserId = null): Order
    {
        return DB::transaction(function () use ($user, $event, $units, $buyer, $attendees, $promoCode, $methodCode, $expectedTotal, $affiliateUserId) {
            // Every rule is checked again against current data, and prices are the current ones.
            $fresh = $this->selection->revalidate($event, $units);
            $freshUnits = collect($fresh['units']);

            if ($freshUnits->count() !== count($attendees)) {
                throw ValidationException::withMessages(['attendees' => 'Please fill attendee data for all selected tickets.']);
            }

            $quote = $this->pricing->quote($event, $freshUnits, $promoCode, lockPromo: true);

            if ($expectedTotal !== null && abs($quote->total - $expectedTotal) > 0.009) {
                throw ValidationException::withMessages(['price_changed' => 'The price changed while you were checking out. Please review your order again.']);
            }

            $quote->promo?->increment('used_count');

            $requiresApproval = (bool) $event->requires_booking_approval;
            $customer = $this->customerFor($user, $buyer);

            $order = $this->createOrder($user, $customer, $quote, $requiresApproval, $methodCode, $promoCode, $affiliateUserId);
            app(\App\Orders\OrderAssignmentService::class)->assignOnCreate($order); // never throws; unassigned on any problem

            foreach ($freshUnits->values() as $index => $unit) {
                $attendee = $attendees[$index];

                $order->items()->create([
                    'event_id' => $event->id,
                    'ticket_id' => null,
                    'ticket_name' => $unit['event_name'].' - '.$unit['ticket_name'],
                    'ticket_price' => $unit['ticket_price'],
                    'quantity' => 1,
                    'line_total' => (float) $unit['ticket_price'],
                    'holder_name' => trim((string) $attendee['name']),
                    'holder_email' => trim((string) $attendee['email']),
                    'holder_phone' => trim((string) $attendee['phone']),
                    'holder_gender' => (string) $attendee['gender'],
                    'holder_social_profile' => trim((string) ($attendee['social_profile'] ?? '')) ?: null,
                ]);
            }

            activity('orders')
                ->performedOn($order)
                ->causedBy($user)
                ->withProperties([
                    'to_status' => $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'subtotal' => $quote->subtotal,
                    'discount' => $quote->discount,
                    'fees_total' => $quote->feesTotal,
                ])
                ->log('Order submitted');

            return $order;
        });
    }

    private function createOrder(User $user, Customer $customer, Quote $quote, bool $requiresApproval, ?string $methodCode, ?string $promoCode, ?int $affiliateUserId): Order
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Order::create([
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'affiliate_user_id' => $affiliateUserId,
                    'promo_code_id' => $quote->promo?->id,
                    'promo_code' => $quote->promo?->code,
                    'discount_amount' => $quote->discount,
                    'subtotal_amount' => $quote->subtotal,
                    'order_number' => $this->orderNumber(),
                    'status' => $requiresApproval ? 'pending_approval' : 'pending_payment',
                    'requires_approval' => $requiresApproval,
                    'payment_method' => $requiresApproval ? 'pending_review' : (string) $methodCode,
                    'payment_link_token' => $requiresApproval ? null : Str::random(40),
                    'payment_timeout_started_at' => $requiresApproval ? null : now(),
                    'payment_timeout_minutes' => $requiresApproval ? null : SystemSettings::pendingPaymentTimeoutMinutes(),
                    'total_amount' => $quote->total,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Order number collision under concurrency: draw another one.
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate an order number.');
    }

    /** yymmdd + 4 random digits, as before. */
    private function orderNumber(): string
    {
        do {
            $candidate = now()->format('ymd').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * The customer record is keyed by email. Only the account owner's own record is updated from the form;
     * for any other email an existing record is reused untouched, so one user can't rewrite another's contact details.
     */
    private function customerFor(User $user, array $buyer): Customer
    {
        $values = [
            'first_name' => $buyer['first_name'],
            'last_name' => $buyer['last_name'],
            'phone' => $buyer['phone'] ?? null,
        ];

        if (strcasecmp((string) $user->email, (string) $buyer['email']) === 0) {
            return Customer::updateOrCreate(['email' => $buyer['email']], $values);
        }

        return Customer::firstOrCreate(['email' => $buyer['email']], $values);
    }
}
