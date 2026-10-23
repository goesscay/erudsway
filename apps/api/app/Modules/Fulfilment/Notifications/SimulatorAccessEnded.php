<?php

namespace App\Modules\Fulfilment\Notifications;

use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The simulator login was removed because the trial or plan ended. */
class SimulatorAccessEnded extends Notification implements ShouldQueue
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
        $trial = $this->task->type === TaskType::SimulatorTrial;

        return (new MailMessage)
            ->subject($trial ? 'Your GST simulator trial has ended' : 'Your GST simulator access has ended')
            ->line($trial
                ? 'Your 7-day free trial of the GST simulator has ended, and its practice login has been removed.'
                : 'Your GST simulator access has ended, and its practice login has been removed.')
            ->line('Pick a plan to get a fresh login and keep practising.')
            ->action('See simulator plans', "{$frontend}/gst-simulator#plans");
    }
}
