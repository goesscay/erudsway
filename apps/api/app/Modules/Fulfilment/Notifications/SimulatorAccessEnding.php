<?php

namespace App\Modules\Fulfilment\Notifications;

use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A few days before a simulator trial or plan ends. */
class SimulatorAccessEnding extends Notification implements ShouldQueue
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
        $ends = $this->task->access_ends_at?->timezone('Asia/Kolkata')->format('j M Y');
        $trial = $this->task->type === TaskType::SimulatorTrial;

        return (new MailMessage)
            ->subject($trial ? 'Your GST simulator trial ends soon' : 'Your GST simulator access ends soon')
            ->line(($trial ? 'Your free trial' : 'Your simulator access')." ends on {$ends}. After that the practice login and its returns are removed.")
            ->line($trial
                ? 'Choose a plan before then to keep the same login and everything you’ve practised.'
                : 'Renew before then to keep the same login and everything you’ve practised.')
            ->action($trial ? 'See simulator plans' : 'Renew my access', "{$frontend}/gst-simulator#plans");
    }
}
