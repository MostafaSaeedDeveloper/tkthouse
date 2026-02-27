<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalOrders = Order::count();
        $totalRevenue = (float) Order::sum('total_amount');
        $pendingOrders = Order::whereIn('status', ['pending', 'pending_approval', 'pending_payment'])->count();
        $totalCustomers = Customer::count();
        $totalEvents = Event::where('status', 'active')->count();

        $recentOrders = Order::with(['customer', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $topEvents = OrderItem::query()
            ->select(['ticket_name', 'line_total'])
            ->get()
            ->groupBy(function (OrderItem $item) {
                return str_contains($item->ticket_name, ' - ')
                    ? trim((string) strstr($item->ticket_name, ' - ', true))
                    : $item->ticket_name;
            })
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'orders_count' => $items->count(),
                    'revenue' => (float) $items->sum('line_total'),
                ];
            })
            ->sortByDesc('revenue')
            ->take(4)
            ->values();

        $start = now()->startOfMonth()->subMonths(6);
        $ordersWindow = Order::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'total_amount']);

        $labels = [];
        $revenueData = [];
        $ordersData = [];

        for ($i = 6; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('M');

            $monthOrders = $ordersWindow->filter(fn ($order) => Carbon::parse($order->created_at)->format('Y-m') === $month->format('Y-m'));
            $ordersData[] = $monthOrders->count();
            $revenueData[] = round((float) $monthOrders->sum('total_amount'), 2);
        }

        return view('admin.index', compact(
            'totalOrders',
            'totalRevenue',
            'pendingOrders',
            'totalCustomers',
            'totalEvents',
            'recentOrders',
            'topEvents',
            'labels',
            'revenueData',
            'ordersData'
        ));
    }
}
