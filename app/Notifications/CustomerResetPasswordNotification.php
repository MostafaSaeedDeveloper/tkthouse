<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset Your Password — TKT House')
            ->view('emails.auth.password-reset-link', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
