<?php

namespace App\Services\Payments\Routing;

use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Collection;

/** Which payment methods a customer may pick for a given routing decision. Never assumes every account supports every method. */
class OfferedMethods
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    /** @return Collection<int, PaymentMethod> */
    public function for(Resolution $resolution): Collection
    {
        // Nothing routing-related applies: exactly the pre-existing list.
        if ($resolution->account === null && ! $resolution->blocked && $resolution->allowedMethods === null) {
            return $this->gateways->available();
        }

        return PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->orderBy('sort_order')->orderBy('id')->get()
            ->filter(function (PaymentMethod $m) use ($resolution) {
                if ($resolution->allowedMethods !== null && ! in_array($m->code, $resolution->allowedMethods, true)) {
                    return false;
                }
                if (! $this->gateways->isOnline($m)) {
                    return true; // manual methods (staff confirm) stay available, also when online payment is blocked
                }
                if ($resolution->blocked) {
                    return false;
                }
                $account = $resolution->account;
                if ($account === null) {
                    return $this->gateways->for((string) $m->provider)->isConfigured($m);
                }

                return $account->provider === $m->provider
                    && in_array($m->code, $account->supportedMethods(), true)
                    && $this->gateways->for((string) $m->provider)->isConfigured($m, $account);
            })->values();
    }

    public function offers(Resolution $resolution, string $code): bool
    {
        return $this->for($resolution)->contains('code', $code);
    }
}
