<?php

namespace App\Modules\Fulfilment\Notifications;

use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoubtSessionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly FulfilmentTask $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->task->scheduled_for?->timezone('Asia/Kolkata')->format('l, j F Y \a\t g:i A').' IST';
        $mail = (new MailMessage)
            ->subject('Your doubt session is scheduled')
            ->line('Your 60-minute doubt session for “'.Markdown::escape($this->task->course->title).'” is on '.$when.'.');
        if ($this->task->staff_notes) {
            $mail->line(Markdown::escape($this->task->staff_notes));
        }

        return $mail->line('Reply to this email if the time doesn’t work for you.');
    }
}
