<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Orders\AgentReport;
use App\Orders\OrderAccess;
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

        $seesTeam = $level === OrderAccess::ALL && ($user->can('orders.view_agent_stats') || OrderAccess::isSuper($user));
        // Scoped users live on /my-report (nothing in the URL hints at other agents); the team view stays on /reports/agents.
        if ($seesTeam && $request->routeIs('admin.reports.mine')) {
            return redirect()->route('admin.reports.agents', $request->query());
        }
        if (! $seesTeam && $request->routeIs('admin.reports.agents')) {
            return redirect()->route('admin.reports.mine', $request->query());
        }

        if (! $seesTeam) {
            // Everyone else gets an event-level report of the orders they can see, with no hint of assignment.
            return view('admin.reports.agents', ['eventRows' => $report->forEvents($user, $from, $to), 'from' => $from, 'to' => $to, 'seesTeam' => false, 'showAccounts' => false, 'accountNames' => collect(), 'data' => null]);
        }

        $data = $report->build($user, $from, $to);

        $showAccounts = $user->can('settings.payment-accounts.view') || OrderAccess::isSuper($user);
        $accountNames = $showAccounts ? \App\Models\IntegrationAccount::query()->pluck('name', 'id') : collect();

        return view('admin.reports.agents', ['data' => $data, 'from' => $from, 'to' => $to, 'seesTeam' => $seesTeam, 'showAccounts' => $showAccounts, 'accountNames' => $accountNames]);
    }
}
