<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreAffiliateReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $refCode = trim((string) $request->query('ref', ''));

        if ($refCode !== '') {
            $affiliate = User::query()->where('affiliate_code', $refCode)->first();

            if ($affiliate) {
                $isSelfReferral = $request->user() && (int) $request->user()->id === (int) $affiliate->id;

                if (! $isSelfReferral) {
                    $request->session()->put('affiliate.referrer_id', $affiliate->id);
                    $request->session()->put('affiliate.referrer_code', $affiliate->affiliate_code);
                }
            }
        }

        return $next($request);
    }
}
