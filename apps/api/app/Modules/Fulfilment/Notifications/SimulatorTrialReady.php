<?php

namespace App\Modules\Fulfilment\Notifications;

use App\Modules\Fulfilment\Models\FulfilmentTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SimulatorTrialReady extends Notification implements ShouldQueue
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
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $ends = $this->task->access_ends_at?->timezone('Asia/Kolkata')->format('j M Y, g:i a');

        // The password isn't emailed: it's shown on the simulator page, behind the learner's login.
        return (new MailMessage)
            ->subject('Your 7-day GST simulator trial has started')
            ->line('Your practice GST login is ready. File GSTR-1 and GSTR-3B, work through IMS and pay challans on a portal that behaves like the real one — with practice data, so nothing you do is real.')
            ->line($ends ? "Your trial runs until {$ends}." : 'Your trial runs for 7 days.')
            ->action('Open the simulator', "{$frontend}/gst-simulator");
    }
}
