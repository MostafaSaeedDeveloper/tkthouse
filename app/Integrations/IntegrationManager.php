<?php

namespace App\Integrations;

use App\Models\IntegrationAccount;
use App\Models\User;
use App\Support\PaymentEnvironment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class IntegrationManager
{
    public const RUNTIME_CACHE = 'integrations.runtime.v1';

    public function __construct(private readonly ConnectionTester $tester)
    {
    }

    /** Creates/updates an account. Blank secret inputs keep the stored secret; any credential change disables + unverifies. */
    public function save(?IntegrationAccount $account, string $provider, array $input, ?User $by): IntegrationAccount
    {
        $def = Definitions::get($provider) ?? throw new \InvalidArgumentException('Unknown provider.');
        $account ??= new IntegrationAccount(['provider' => $provider, 'created_by' => $by?->id]);

        $config = $account->config ?? [];
        $secrets = [];
        try {
            $secrets = $account->secrets ?? [];
        } catch (\Throwable) {
        }

        foreach ($def['fields'] as $f) {
            $value = isset($input[$f['name']]) ? trim((string) $input[$f['name']]) : '';
            if ($f['secret']) {
                if ($value !== '') {
                    $secrets[$f['name']] = $value; // new value typed explicitly
                }
            } else {
                $config[$f['name']] = $value !== '' ? $value : ($f['default'] ?? null);
            }
        }

        $fingerprint = IntegrationAccount::fingerprint($config, $secrets);
        $environment = in_array($input['environment'] ?? $account->environment, ['sandbox', 'production'], true) ? ($input['environment'] ?? $account->environment ?? 'sandbox') : 'sandbox';
        $changed = ! $account->exists || $fingerprint !== $account->credential_fingerprint || $environment !== $account->environment;

        $account->fill([
            'name' => trim((string) ($input['name'] ?? $account->name)),
            'environment' => $environment, 'config' => $config, 'secrets' => $secrets,
            'credential_fingerprint' => $fingerprint, 'updated_by' => $by?->id,
        ]);

        if ($changed) {
            $account->fill(['enabled' => false, 'status' => IntegrationAccount::UNVERIFIED, 'last_error' => null]);
        }
        $account->save();
        $this->flush();

        // Field names only — never values.
        activity('settings')->causedBy($by)->performedOn($account)
            ->withProperties(['provider' => $provider, 'credentials_changed' => $changed, 'fields' => array_keys(array_filter($input, fn ($v, $k) => $k !== 'name' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH))])
            ->log($changed && $account->wasRecentlyCreated ? 'Integration account created' : 'Integration account updated');

        return $account;
    }

    /** @return array{ok: bool, verified: bool, message: string} */
    public function test(IntegrationAccount $account, ?User $by): array
    {
        $result = $this->tester->test($account);

        $account->forceFill([
            'last_tested_at' => now(),
            'last_success_at' => $result['verified'] ? now() : $account->last_success_at,
            'status' => $result['verified'] ? IntegrationAccount::VERIFIED : ($result['ok'] ? IntegrationAccount::UNVERIFIED : IntegrationAccount::ERROR),
            'last_error' => $result['ok'] ? null : $result['message'],
        ]);
        if (! $result['verified']) {
            $account->enabled = false; // a failed re-test never leaves an unverified account live
        }
        $account->save();
        $this->flush();

        activity('settings')->causedBy($by)->performedOn($account)
            ->withProperties(['provider' => $account->provider, 'ok' => $result['ok'], 'verified' => $result['verified']])
            ->log('Integration connection tested');

        return $result;
    }

    /** Saving never enables. Enabling needs a verified test of exactly these credentials plus the platform gates. */
    public function enable(IntegrationAccount $account, bool $confirmedProduction, ?User $by): void
    {
        if (! $this->isCompleteAndVerified($account)) {
            throw ValidationException::withMessages(['account' => 'Run a successful connection test with the current credentials before enabling.']);
        }

        if ($account->environment === 'production') {
            if (! $confirmedProduction) {
                throw ValidationException::withMessages(['confirm' => 'Confirm that you want to enable this account in Production.']);
            }
            if (in_array($account->provider, ['paymob', 'fawaterak'], true) && ! PaymentEnvironment::liveGatewaysAllowed()) {
                throw ValidationException::withMessages(['account' => 'Live payment gateways are disabled in this environment.']);
            }
        }

        if ($account->provider === 'fawaterak') {
            throw ValidationException::withMessages(['account' => 'Fawaterak accounts cannot be enabled until the sandbox verification of its callbacks and inquiry is completed.']);
        }

        $account->forceFill(['enabled' => true, 'updated_by' => $by?->id])->save();
        $this->flush();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => $account->provider, 'environment' => $account->environment])->log('Integration account enabled');
    }

    public function disable(IntegrationAccount $account, ?User $by): void
    {
        $account->forceFill(['enabled' => false, 'updated_by' => $by?->id])->save();
        $this->flush();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => $account->provider])->log('Integration account disabled');
    }

    public function isCompleteAndVerified(IntegrationAccount $account): bool
    {
        return $account->status === IntegrationAccount::VERIFIED
            && $account->credential_fingerprint === IntegrationAccount::fingerprint($account->config ?? [], $this->secretsOf($account));
    }

    private function secretsOf(IntegrationAccount $a): array
    {
        try {
            return $a->secrets ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function flush(): void
    {
        Cache::forget(self::RUNTIME_CACHE);
    }

    /** The one usable (enabled + verified) account for a messaging provider, or null => legacy .env behaviour. */
    public static function runtimeAccount(string $provider): ?IntegrationAccount
    {
        try {
            return IntegrationAccount::query()->where('provider', $provider)->where('enabled', true)
                ->where('status', IntegrationAccount::VERIFIED)->orderBy('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
