<?php

namespace App\Modules\Commerce\Notifications;

use App\Modules\Commerce\Models\Invoice;
use App\Modules\Commerce\Models\Order;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class OrderReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order, public readonly ?Invoice $invoice = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $order = $this->order->loadMissing('items');
        $mail = (new MailMessage)
            ->subject("Your Erudsway receipt — order {$order->number}")
            ->greeting('Thank you for your purchase')
            ->line('You now have lifetime access to:');

        foreach ($order->items as $item) {
            $mail->line('• '.Markdown::escape($item->course_title));
        }

        $mail->line('Total paid: ₹'.number_format($order->total / 100, 2).' (including GST).')
            ->action('Start learning', "{$frontend}/my-learning");

        if ($this->invoice?->file_path && Storage::disk('local')->exists($this->invoice->file_path)) {
            $mail->line("Your tax invoice {$this->invoice->number} is attached.")
                ->attachData((string) Storage::disk('local')->get($this->invoice->file_path), str_replace('/', '-', $this->invoice->number).'.pdf', ['mime' => 'application/pdf']);
        }

        return $mail->line('Course purchases are final. If you were charged twice or can’t access your course, reply to this email or write to support@erudsway.com with your order number.');
    }
}
