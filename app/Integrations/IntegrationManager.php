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
            $raw = $input[$f['name']] ?? '';
            $value = is_array($raw) ? implode(',', array_map('trim', $raw)) : trim((string) $raw);
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
            $account->fill(['enabled' => false, 'status' => IntegrationAccount::UNVERIFIED, 'last_error' => null, 'refund_enabled_at' => null]);
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
    public function test(IntegrationAccount $account, ?User $by, ?string $recipient = null): array
    {
        $result = $this->tester->test($account, $recipient);

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

        if ($account->provider === 'fawaterak' && ($account->environment === 'production' || app()->isProduction()) && ! config('payments.fawaterak.verified')) {
            throw ValidationException::withMessages(['account' => 'Fawaterak is locked for Production until a real sandbox run confirms its inquiry, payment fields and account binding (PAYMENTS_FAWATERAK_VERIFIED).']);
        }
        if ($account->archived_at !== null) {
            throw ValidationException::withMessages(['account' => 'Archived accounts cannot be enabled.']);
        }

        $account->forceFill(['enabled' => true, 'updated_by' => $by?->id])->save();
        $this->flush();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => $account->provider, 'environment' => $account->environment])->log('Integration account enabled');
    }

    /**
     * Fawaterak has no confirmed read-only endpoint, so a person records the outcome of the sandbox runbook. This only marks the
     * CURRENT credentials as verified (changing them resets it); Production stays locked by PAYMENTS_FAWATERAK_VERIFIED.
     */
    public function attestSandbox(IntegrationAccount $account, string $note, ?User $by): void
    {
        if ($account->provider !== 'fawaterak' || $account->environment !== 'sandbox') {
            throw ValidationException::withMessages(['account' => 'Sandbox attestation is only for Fawaterak sandbox accounts.']);
        }
        if ($missing = Definitions::missing('fawaterak', $account->config ?? [], $this->secretsOf($account))) {
            throw ValidationException::withMessages(['account' => 'Missing required fields: '.implode(', ', $missing).'.']);
        }
        $account->forceFill(['status' => IntegrationAccount::VERIFIED, 'last_tested_at' => now(), 'last_success_at' => now(), 'last_error' => null])->save();
        $this->flush();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => 'fawaterak', 'note' => mb_substr($note, 0, 300)])->log('Integration sandbox verification recorded');
    }

    /**
     * An operator records that a real refund (and its confirmation) worked for this account in the provider's sandbox. Until then
     * the system offers only the manual / external refund workflow for this account. Changing credentials resets it.
     */
    public function setRefundVerified(IntegrationAccount $account, bool $on, string $note, ?User $by): void
    {
        if (! in_array($account->provider, ['paymob', 'fake'], true)) {
            throw ValidationException::withMessages(['account' => 'This provider has no documented refund API.']);
        }
        if ($on && blank($account->secret('secret_key')) && $account->provider === 'paymob') {
            throw ValidationException::withMessages(['account' => 'Store the Paymob secret key first (it authenticates the refund API).']);
        }
        $account->forceFill(['refund_enabled_at' => $on ? now() : null, 'refund_enabled_by' => $on ? $by?->id : null])->save();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => $account->provider, 'note' => mb_substr($note, 0, 300)])
            ->log($on ? 'Refunds confirmed for merchant account' : 'Refunds disabled for merchant account');
    }

    /** Disabled + hidden from selection; historical attempts keep using it for inquiry, callbacks and reconciliation. */
    public function archive(IntegrationAccount $account, ?User $by): void
    {
        $account->forceFill(['enabled' => false, 'archived_at' => now(), 'updated_by' => $by?->id])->save();
        $this->flush();
        activity('settings')->causedBy($by)->performedOn($account)->withProperties(['provider' => $account->provider])->log('Integration account archived');
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
