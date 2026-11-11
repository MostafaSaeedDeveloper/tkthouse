<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Orders\AgentReport;
use App\Orders\OrderAccess;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class AgentReportController extends Controller
{
    public function index(Request $request, AgentReport $report)
    {
        $user = $request->user();
        abort_unless(OrderAccess::enabled(), 404);
        $level = OrderAccess::level($user);
        abort_if($level === OrderAccess::NONE, 403);

        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->string('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->string('to'))->endOfDay() : now()->endOfDay();

        // Opens on the Active events; event_id=all or one event steps back. Only events that have orders this viewer may see are offered.
        $eventOptions = Event::query()
            ->whereIn('id', OrderItem::query()->select('event_id')->whereIn('order_id', Order::query()->visibleTo($user)->select('orders.id')))
            ->orderBy('name')->get(['id', 'name', 'status']);
        $choice = (string) $request->input('event_id', '');
        $eventIds = null;
        $eventScopeIsDefault = false;
        if (ctype_digit($choice) && $eventOptions->contains('id', (int) $choice)) {
            $eventIds = [(int) $choice];
        } elseif ($choice !== 'all') {
            $active = $eventOptions->where('status', 'active')->pluck('id')->all();
            if ($active) {
                $eventIds = $active;
                $eventScopeIsDefault = true;
            }
        }
        $scope = ['eventOptions' => $eventOptions, 'eventChoice' => $choice === 'all' ? 'all' : ($eventIds && ! $eventScopeIsDefault ? (string) $eventIds[0] : ''), 'eventScopeIsDefault' => $eventScopeIsDefault];

        $seesTeam = $level === OrderAccess::ALL && ($user->can('orders.view_agent_stats') || OrderAccess::isSuper($user));
        if (! $seesTeam) {
            // Everyone else gets an event-level report of the orders they can see, with no hint of assignment.
            return view('admin.reports.agents', ['eventRows' => $report->forEvents($user, $from, $to, $eventIds), 'from' => $from, 'to' => $to, 'seesTeam' => false, 'showAccounts' => false, 'accountNames' => collect(), 'data' => null] + $scope);
        }

        $data = $report->build($user, $from, $to, $eventIds);

        $showAccounts = $user->can('settings.payment-accounts.view') || OrderAccess::isSuper($user);
        $accountNames = $showAccounts ? \App\Models\IntegrationAccount::query()->pluck('name', 'id') : collect();

        return view('admin.reports.agents', ['data' => $data, 'from' => $from, 'to' => $to, 'seesTeam' => $seesTeam, 'showAccounts' => $showAccounts, 'accountNames' => $accountNames] + $scope);
    }
}
