<?php

namespace App\Modules\Certificates\Notifications;

use App\Modules\Certificates\Models\Certificate;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Always sent: earning a certificate isn't something learners opt out of. */
class CertificateIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Certificate $certificate) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{kind: string, title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'certificate',
            'title' => 'You’ve earned a certificate',
            'body' => "You completed {$this->certificate->course_title}.",
            'url' => "/certificates/{$this->certificate->code}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->certificate;

        return (new MailMessage)
            ->subject("Your certificate for {$c->course_title}")
            ->greeting('Congratulations!')
            ->line('You completed every lecture of '.Markdown::escape($c->course_title).' and earned a certificate of completion.')
            ->line("Certificate number {$c->serial}. Anyone can check it at the link below.")
            ->action('View your certificate', $c->verifyUrl());
    }
}
