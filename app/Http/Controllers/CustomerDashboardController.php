<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::query()
            ->with(['items', 'customer', 'issuedTickets'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('customer', fn ($q) => $q->where('email', $user->email));
            })
            ->latest()
            ->paginate(10);

        return view('front.account.dashboard', compact('orders'));
    }
}
