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

        $data = $report->build($user, $from, $to);
        $seesTeam = $level === OrderAccess::ALL && ($user->can('orders.view_agent_stats') || OrderAccess::isSuper($user));
        if (! $seesTeam) {
            // An agent (or a view_all user without agent-stats) only ever gets their own row.
            $data['rows'] = $data['rows']->where('user_id', $user->id)->values();
            $data['unassigned'] = null;
            $data['unattributed'] = null;
        }

        $showAccounts = $user->can('settings.payment-accounts.view') || OrderAccess::isSuper($user);
        $accountNames = $showAccounts ? \App\Models\IntegrationAccount::query()->pluck('name', 'id') : collect();

        return view('admin.reports.agents', ['data' => $data, 'from' => $from, 'to' => $to, 'seesTeam' => $seesTeam, 'showAccounts' => $showAccounts, 'accountNames' => $accountNames]);
    }
}
