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

        // An order placed before the mailbox was proven belongs to the buying browser only, even for the account's own login.
        if ($user && (int) $order->user_id === (int) $user->id && ! $order->owner_unverified) {
            return true;
        }

        return in_array($order->id, (array) $request->session()->get(self::KEY, []), true);
    }

    /**
     * The owner proved the mailbox (e-mail code): orders this browser placed before that moment become part of the account.
     * Orders placed by other browsers stay unclaimed, so a stranger can't push orders into someone else's account.
     */
    public static function claim(Request $request, \App\Models\User $user, ?Order $extra = null): int
    {
        $ids = (array) $request->session()->get(self::KEY, []);
        if ($extra && (int) $extra->user_id === (int) $user->id) {
            $ids[] = $extra->id;
        }

        if ($ids === []) {
            return 0;
        }

        return Order::query()->where('user_id', $user->id)->where('owner_unverified', true)->whereIn('id', $ids)->update(['owner_unverified' => false]);
    }

    /**
     * The mailbox of this account is proven (e-mail code, password reset, verified registration): every order placed
     * on the account before that moment belongs to its owner, whichever browser/device placed it or whether that
     * session still exists. Only accounts that already exist reach this point, and checkout refuses an existing
     * address without a code, so nobody can push orders into a verified account.
     */
    public static function releaseFor(\App\Models\User $user): int
    {
        return Order::query()->where('user_id', $user->id)->where('owner_unverified', true)->update(['owner_unverified' => false]);
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(1, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }
}
