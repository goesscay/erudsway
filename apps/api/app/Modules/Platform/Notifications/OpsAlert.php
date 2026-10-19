<?php

namespace App\Modules\Platform\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent straight away (not queued): the queue may be the thing that's broken. */
class OpsAlert extends Notification
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('[Erudsway '.app()->environment().'] '.count($this->problems).' problem(s) need attention')
            ->greeting('Something needs a look');
        foreach ($this->problems as $problem) {
            $mail->line('• '.$problem);
        }

        return $mail->line('Checked at '.now('Asia/Kolkata')->format('j M Y, g:i a').' IST on '.gethostname().'. You won’t hear about the same problem again for six hours unless it clears and comes back.');
    }
}
