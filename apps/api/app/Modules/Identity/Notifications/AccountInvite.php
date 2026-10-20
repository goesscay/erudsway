<?php

namespace App\Modules\Identity\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when staff create an account: a link to choose a password (valid three days). */
class AccountInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontend.'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email, 'invite' => 1]);

        return (new MailMessage)
            ->subject('Your Erudsway account is ready')
            ->line('The Erudsway team has created an account for you with this email address.')
            ->line('Choose a password to sign in.')
            ->action('Set your password', $url)
            ->line('This link works for 3 days. After that, use “Forgot password” on the sign-in page.');
    }
}
