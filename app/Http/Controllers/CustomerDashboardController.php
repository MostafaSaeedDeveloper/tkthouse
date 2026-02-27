<?php

namespace App\Http\Controllers;

use App\Models\IssuedTicket;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $ordersCount = $this->ordersQuery($user)->count();
        $ticketsCount = $this->ticketsQuery($user)->count();
        $latestOrders = $this->ordersQuery($user)->latest()->limit(5)->get();
        $latestTickets = $this->ticketsQuery($user)->with('order')->latest()->limit(5)->get();

        return view('front.account.dashboard', compact('user', 'ordersCount', 'ticketsCount', 'latestOrders', 'latestTickets'));
    }

    public function profile(Request $request)
    {
        return view('front.account.profile', ['user' => $request->user()]);
    }

    public function orders(Request $request)
    {
        $user = $request->user();

        $orders = $this->ordersQuery($user)
            ->with(['items', 'customer', 'issuedTickets'])
            ->latest()
            ->paginate(10);

        return view('front.account.orders', compact('user', 'orders'));
    }

    public function tickets(Request $request)
    {
        $user = $request->user();

        $tickets = $this->ticketsQuery($user)
            ->with(['order'])
            ->latest()
            ->paginate(10);

        return view('front.account.tickets', compact('user', 'tickets'));
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
