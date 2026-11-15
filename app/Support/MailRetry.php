<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

/** Sends one message with a short retry for the provider's "too many emails per second" answer. Returns the failure text instead of throwing. */
class MailRetry
{
    /** @return string|null null on success, otherwise a short error text */
    public static function send(callable $send, array $context = []): ?string
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $send();

                return null;
            } catch (UnexpectedResponseException $e) {
                $rateLimited = $e->getCode() === 550 && str_contains(mb_strtolower($e->getMessage()), 'too many emails per second');
                if (! $rateLimited || $attempt === $maxAttempts) {
                    Log::error('Mail send failed.', [...$context, 'attempt' => $attempt, 'error' => $e->getMessage()]);

                    return mb_substr($e->getMessage(), 0, 200);
                }
                usleep($attempt * 500000);
            } catch (Throwable $e) {
                Log::error('Mail send failed.', [...$context, 'attempt' => $attempt, 'error' => $e->getMessage()]);

                return mb_substr($e->getMessage(), 0, 200);
            }
        }

        return 'unknown error';
    }
}
