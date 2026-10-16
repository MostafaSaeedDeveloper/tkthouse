<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Orders\OrderAccess;
use App\Orders\OrderAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderAssignmentController extends Controller
{
    public function __construct(private readonly OrderAssignmentService $service)
    {
    }

    public function assign(Request $request, Order $order)
    {
        $actor = $request->user();
        abort_unless($order->isVisibleTo($actor), 404);

        $data = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->authorizeChange($actor, $order);
        $to = filled($data['assignee_id'] ?? null) ? User::query()->findOrFail((int) $data['assignee_id']) : null;
        $this->service->assign($order, $to, $actor, $data['reason'] ?? null);

        return back()->with('success', $to ? "Order #{$order->order_number} assigned to {$to->name}." : "Order #{$order->order_number} is now unassigned.");
    }

    public function bulk(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['integer'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $to = filled($data['assignee_id'] ?? null) ? User::query()->findOrFail((int) $data['assignee_id']) : null;
        $done = 0;
        $skipped = 0;

        foreach (Order::query()->visibleTo($actor)->whereIn('id', $data['order_ids'])->get() as $order) {
            try {
                $this->authorizeChange($actor, $order);
                $this->service->assign($order, $to, $actor, $data['reason'] ?? null);
                $done++;
            } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException) {
                $skipped++;
            }
        }

        return back()->with('success', "{$done} order(s) updated".($skipped ? ", {$skipped} skipped (not allowed)." : '.'));
    }

    /** First assignment needs orders.assign; changing an existing assignee needs orders.reassign. */
    private function authorizeChange(User $actor, Order $order): void
    {
        abort_unless(OrderAccess::canAssign($actor, reassign: $order->assigned_user_id !== null), 403);
    }
}
