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

        $offered = $this->filtered($resolution, $resolution->allowedMethods);

        // An agent whose allowed methods are all unavailable right now must never leave the customer unable to pay.
        if ($offered->isEmpty() && $resolution->allowedMethods !== null) {
            return $this->filtered($resolution, null);
        }

        return $offered;
    }

    /** @return Collection<int, PaymentMethod> */
    private function filtered(Resolution $resolution, ?array $allowed): Collection
    {
        return PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->orderBy('sort_order')->orderBy('id')->get()
            ->filter(function (PaymentMethod $m) use ($resolution, $allowed) {
                if ($allowed !== null && ! in_array($m->code, $allowed, true)) {
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

    /** Why each active method is not offered (for the log and for admins looking at an empty checkout). @return list<string> */
    public function diagnose(Resolution $resolution): array
    {
        $out = [];
        if ($resolution->blocked) {
            $out[] = 'Online payment is blocked: '.($resolution->reason ?? 'unknown').'. Check Settings → Payments (default account must be enabled and verified).';
        }
        if (! \App\Support\PaymentEnvironment::liveGatewaysAllowed()) {
            $out[] = 'Live gateways are disabled (PAYMENTS_LIVE_GATEWAYS).';
        } elseif (! \App\Support\PaymentEnvironment::fawaterakAllowed()) {
            $out[] = 'Fawaterak is locked in production until PAYMENTS_FAWATERAK_VERIFIED=true.';
        }
        $methods = PaymentMethod::query()->where('is_active', true)->where('code', '!=', 'card')->get();
        if ($methods->isEmpty()) {
            $out[] = 'No payment method is active (Settings → Payments → Methods).';
        }
        foreach ($methods as $m) {
            $account = $resolution->account;
            if ($resolution->allowedMethods !== null && ! in_array($m->code, $resolution->allowedMethods, true)) {
                continue;
            }
            if (! $this->gateways->isOnline($m)) {
                continue;
            }
            if ($account === null) {
                $cfg = is_array($m->config) ? $m->config : [];
                $out[] = $m->name.' (legacy configuration): '.($m->provider === 'fawaterak'
                    ? (blank($cfg['api_key'] ?? config('services.fawaterak.api_key')) ? 'no Fawaterak API key (set FAWATERAK_API_KEY in the env or on the method).' : 'provider key "'.($cfg['provider_key'] ?? '').'" is missing or not a number.')
                    : 'its credentials are incomplete.');
            } elseif ($account->provider !== $m->provider) {
                $out[] = $m->name.': the account "'.$account->name.'" belongs to '.$account->provider.', not '.$m->provider.'.';
            } elseif (! in_array($m->code, $account->supportedMethods(), true)) {
                $out[] = $m->name.': not ticked in the account "'.$account->name.'" (Supported methods).';
            } elseif (! $this->gateways->for((string) $m->provider)->isConfigured($m, $account)) {
                $out[] = $m->name.': the account "'.$account->name.'" is missing its API key or the provider/integration id for this method ('.$account->methodField($m->code).').';
            }
        }

        return array_values(array_unique($out));
    }
}
