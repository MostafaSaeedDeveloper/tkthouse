<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Settings\SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Order::observe(OrderObserver::class);

        $this->applyManagedMailSettings();
        $this->guardOutboundMail();
    }

    /** A verified + enabled "E-mail delivery" account from the Settings Center overrides the .env SMTP settings. */
    private function applyManagedMailSettings(): void
    {
        $account = \App\Integrations\IntegrationManager::runtimeAccount('email');
        if (! $account) {
            return;
        }

        $enc = (string) ($account->field('encryption') ?: 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $account->field('host'),
            'mail.mailers.smtp.port' => (int) $account->field('port'),
            'mail.mailers.smtp.scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $account->field('username'),
            'mail.mailers.smtp.password' => $account->secret('password'),
            'mail.from.address' => $account->field('from_address'),
            'mail.from.name' => $account->field('from_name') ?: config('mail.from.name'),
        ]);
    }

    /** Dev/staging safety net: only allow-listed recipients ever receive mail (see config/notifications.php). */
    private function guardOutboundMail(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $allowlist = config('notifications.mail_allowlist');

            if ($allowlist === null) {
                return null; // no restriction (production)
            }

            $allowed = fn (string $email) => collect($allowlist)->contains(
                fn (string $rule) => str_starts_with($rule, '@')
                    ? str_ends_with(strtolower($email), strtolower($rule))
                    : strcasecmp($email, $rule) === 0
            );

            $message = $event->message;
            $to = array_values(array_filter($message->getTo(), fn ($address) => $allowed($address->getAddress())));
            $blocked = count($message->getTo()) - count($to);

            if ($blocked > 0) {
                Log::info('Outbound mail recipients blocked by NOTIFY_MAIL_ALLOWLIST.', ['blocked' => $blocked, 'subject' => $message->getSubject()]);
            }

            $message->cc(...array_values(array_filter($message->getCc(), fn ($a) => $allowed($a->getAddress()))));
            $message->bcc(...array_values(array_filter($message->getBcc(), fn ($a) => $allowed($a->getAddress()))));

            if ($to === []) {
                return false; // nothing left to send to: cancel the message
            }

            $message->to(...$to);

            return null;
        });
    }
}
