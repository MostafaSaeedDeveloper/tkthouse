<?php

namespace App\Integrations;

use App\Models\IntegrationAccount;
use App\Support\PaymentEnvironment;
use Illuminate\Support\Facades\Http;

/**
 * Read-only connectivity checks. They never create a payment, send a customer message or send mail.
 *  - Paymob:   authentication-token request (POST /auth/tokens) — validates the API key only.
 *  - UltraMsg: instance status (GET /{instance}/instance/status).
 *  - SMTP:     TCP connect + server greeting; with a recipient it sends one real test message.
 *  - Fawaterak: no read-only endpoint is confirmed in the project/docs, so the account is only checked for completeness and
 *    stays "unverified"; real verification happens in the Stage E sandbox run. (Nothing is guessed.)
 */
class ConnectionTester
{
    /** @return array{ok: bool, verified: bool, message: string} */
    public function test(IntegrationAccount $account, ?string $recipient = null): array
    {
        $config = $account->config ?? [];
        $secrets = [];
        foreach (Definitions::get($account->provider)['fields'] ?? [] as $f) {
            if ($f['secret']) {
                $secrets[$f['name']] = $account->secret($f['name']);
            }
        }

        if ($missing = Definitions::missing($account->provider, $config, $secrets)) {
            return $this->result(false, false, 'Missing required fields: '.implode(', ', $missing).'.');
        }

        try {
            return match ($account->provider) {
                'paymob' => $this->paymob($account),
                'ultramsg' => $this->ultramsg($account),
                'email' => $recipient ? $this->sendTestMail($account, $recipient) : $this->smtp($account),
                'fake' => \App\Support\PaymentEnvironment::fakeGatewayAllowed() ? $this->result(true, true, 'Fake gateway: no external call is made.') : $this->result(false, false, 'The fake gateway is disabled in this environment.'),
                'fawaterak' => $this->result(true, false, 'Fields are complete. Fawaterak has no confirmed read-only endpoint, so it stays "unverified" until a sandbox run (Stage E).'),
                default => $this->result(false, false, 'Unsupported provider.'),
            };
        } catch (\Throwable $e) {
            return $this->result(false, false, 'Connection failed: '.$this->safe($e->getMessage(), $account));
        }
    }

    private function paymob(IntegrationAccount $a): array
    {
        if (! PaymentEnvironment::liveGatewaysAllowed()) {
            return $this->result(false, false, 'Live gateways are disabled in this environment (PAYMENTS_LIVE_GATEWAYS).');
        }

        $response = Http::baseUrl('https://accept.paymob.com/api')->timeout(15)->post('/auth/tokens', ['api_key' => $a->secret('api_key')]);

        return $response->successful() && filled($response->json('token'))
            ? $this->result(true, true, 'Authenticated with Paymob (no payment was created).')
            : $this->result(false, false, 'Paymob rejected the API key (HTTP '.$response->status().').');
    }

    private function ultramsg(IntegrationAccount $a): array
    {
        $base = rtrim((string) ($a->config['base_url'] ?? 'https://api.ultramsg.com'), '/');
        $response = Http::timeout(15)->get($base.'/'.rawurlencode((string) $a->config['instance_id']).'/instance/status', ['token' => $a->secret('token')]);

        if (! $response->successful() || $response->json('error')) {
            return $this->result(false, false, 'UltraMsg rejected the instance or token (HTTP '.$response->status().').');
        }

        $state = strtolower((string) $response->json('status.accountStatus.status'));

        return $state === 'authenticated'
            ? $this->result(true, true, 'WhatsApp instance is authenticated (no message was sent).')
            : $this->result(false, false, 'Instance reachable but not authenticated (status: '.($state ?: 'unknown').').');
    }

    private function smtp(IntegrationAccount $a): array
    {
        $greeting = $this->smtpGreeting((string) $a->config['host'], (int) $a->config['port'], ($a->config['encryption'] ?? 'tls') === 'ssl');

        return str_starts_with((string) $greeting, '220')
            ? $this->result(true, true, 'SMTP server answered (no mail was sent; credentials are checked on first delivery).')
            : $this->result(false, false, 'SMTP server did not greet us.');
    }

    /** Sends one real message through THIS account's credentials (not the app's active mailer). A delivered message is the proof. */
    private function sendTestMail(IntegrationAccount $a, string $to): array
    {
        $enc = (string) ($a->config['encryption'] ?? 'tls');
        $mailer = \Illuminate\Support\Facades\Mail::build([
            'transport' => 'smtp',
            'host' => $a->config['host'],
            'port' => (int) $a->config['port'],
            'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
            'username' => $a->config['username'] ?? null,
            'password' => $a->secret('password'),
            'timeout' => 15,
        ]);
        $from = (string) $a->config['from_address'];
        $name = (string) ($a->config['from_name'] ?? '') ?: config('app.name');

        $mailer->raw('This is a test message from the TKT House Settings Center. If you can read it, this SMTP account works. No action is needed.', function ($m) use ($to, $from, $name) {
            $m->from($from, $name)->to($to)->subject('TKT House — SMTP test');
        });

        return $this->result(true, true, 'Test e-mail sent to '.$to.'. Check that inbox (and spam) to confirm delivery.');
    }

    protected function smtpGreeting(string $host, int $port, bool $ssl): ?string
    {
        $socket = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://').$host.':'.$port, $errno, $error, 8);
        if (! $socket) {
            throw new \RuntimeException($error ?: 'unreachable');
        }
        stream_set_timeout($socket, 8);
        $line = fgets($socket, 512);
        fclose($socket);

        return $line ?: null;
    }

    private function result(bool $ok, bool $verified, string $message): array
    {
        return ['ok' => $ok, 'verified' => $verified, 'message' => $message];
    }

    /** Never let a secret reach a message that gets stored or shown. */
    private function safe(string $message, IntegrationAccount $account): string
    {
        foreach (Definitions::get($account->provider)['fields'] ?? [] as $f) {
            if ($f['secret'] && ($v = $account->secret($f['name']))) {
                $message = str_replace($v, '[hidden]', $message);
            }
        }

        return mb_substr($message, 0, 250);
    }
}
