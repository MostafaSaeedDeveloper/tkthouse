<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerDashboardController extends Controller
{
    public function profile(Request $request)
    {
        return view('front.account.profile', ['user' => $request->user()]);
    }

    public function orders(Request $request)
    {
        $user = $request->user();

        $ordersQuery = $this->ordersQuery($user);

        $orderStats = [
            'total_orders' => (clone $ordersQuery)->count(),
            'paid_orders' => (clone $ordersQuery)->where('status', 'paid')->count(),
            'pending_orders' => (clone $ordersQuery)->whereIn('status', ['pending', 'pending_payment'])->count(),
            'total_spent' => (float) ((clone $ordersQuery)->where('status', 'paid')->sum('total_amount')),
        ];

        $orders = $ordersQuery
            ->with(['items', 'customer', 'issuedTickets'])
            ->latest()
            ->paginate(10);

        $paymentMethodLabels = PaymentMethod::query()
            ->select(['code', 'checkout_label', 'name'])
            ->get()
            ->mapWithKeys(fn ($method) => [
                (string) $method->code => trim((string) ($method->checkout_label ?: $method->name)),
            ]);

        return view('front.account.orders', compact('user', 'orders', 'paymentMethodLabels', 'orderStats'));
    }

    public function tickets(Request $request)
    {
        $user = $request->user();

        $ticketsQuery = $this->ticketsQuery($user);

        $ticketStats = [
            'total_tickets' => (clone $ticketsQuery)->count(),
            'assigned_holders' => (clone $ticketsQuery)->whereNotNull('holder_name')->count(),
            'delivered' => (clone $ticketsQuery)->whereNotNull('sent_at')->count(),
            'total_value' => (float) ((clone $ticketsQuery)->sum('ticket_price')),
        ];

        $tickets = $ticketsQuery
            ->with(['order'])
            ->latest()
            ->paginate(10);

        return view('front.account.tickets', compact('user', 'tickets', 'ticketStats'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profile_image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $validated['profile_image'] = $request->file('profile_image')->store('profile-images', 'public');
        }

        $user->update($validated);

        return redirect()->route('front.account.profile')->with('success', 'Profile updated successfully.');
    }

    private function ordersQuery($user)
    {
        return Order::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('customer', fn ($q) => $q->where('email', $user->email));
        });
    }

    private function ticketsQuery($user)
    {
        return IssuedTicket::query()->whereHas('order', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('customer', fn ($q) => $q->where('email', $user->email));
        });
    }
}
