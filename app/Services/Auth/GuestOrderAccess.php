<?php

namespace App\Services\Auth;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Who may see/pay an order. Either the signed-in owner, or the very browser session that placed it
 * (checkout without a login). The e-mail address typed at checkout is never enough on its own.
 */
class GuestOrderAccess
{
    private const KEY = 'checkout.my_orders';

    public static function grant(Order $order): void
    {
        $ids = array_slice(array_values(array_unique(array_merge((array) session(self::KEY, []), [$order->id]))), -20);
        session([self::KEY => $ids]);
    }

    public static function can(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user && (int) $order->user_id === (int) $user->id) {
            return true;
        }

        return in_array($order->id, (array) $request->session()->get(self::KEY, []), true);
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(1, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }
}
