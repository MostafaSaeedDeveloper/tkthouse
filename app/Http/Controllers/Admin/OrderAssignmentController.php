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
            'assignee_ids' => ['nullable', 'array', 'max:20'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->authorizeChange($actor, $order);
        $users = $this->resolveAgents($request, $data);
        $this->service->assignMany($order, $users, $actor, $data['reason'] ?? null);

        $names = collect($users)->pluck('name')->implode(', ');

        return back()->with('success', $users ? "Order #{$order->order_number} assigned to {$names}." : "Order #{$order->order_number} is now unassigned.");
    }

    public function bulk(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['integer'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array', 'max:20'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $users = $this->resolveAgents($request, $data);
        // The same agents go on every selected order, so a bad combination is refused once, up front, instead of skipping every order.
        $this->service->assertSamePaymentMethods(collect($users));
        $done = 0;
        $skipped = 0;

        foreach (Order::query()->visibleTo($actor)->whereIn('id', $data['order_ids'])->get() as $order) {
            try {
                $this->authorizeChange($actor, $order);
                $this->service->assignMany($order, $users, $actor, $data['reason'] ?? null);
                $done++;
            } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException) {
                $skipped++;
            }
        }

        return back()->with('success', "{$done} order(s) updated".($skipped ? ", {$skipped} skipped (not allowed)." : '.'));
    }

    /** assignee_ids[] = several agents; assignee_id = the old single-agent field. Order of the list is kept (first = primary for new assignments). @return list<User> */
    private function resolveAgents(Request $request, array $data): array
    {
        $ids = collect($request->has('assignee_ids') ? $data['assignee_ids'] ?? [] : (filled($data['assignee_id'] ?? null) ? [$data['assignee_id']] : []))->map(fn ($id) => (int) $id)->unique()->values();
        $found = User::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $found[$id])->all();
    }

    /** First assignment needs orders.assign; changing an existing assignee needs orders.reassign. */
    private function authorizeChange(User $actor, Order $order): void
    {
        abort_unless(OrderAccess::canAssign($actor, reassign: $order->assigned_user_id !== null), 403);
    }
}
