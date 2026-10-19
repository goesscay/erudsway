<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Jobs\ProcessWebhookEvent;
use App\Modules\Commerce\Models\WebhookEvent;
use App\Modules\Commerce\Payments\PaymentGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Razorpay webhooks: verify the signature on the raw body, store the event once (unique
 * event id), then process it on the queue. Always answers quickly so Razorpay doesn't retry.
 */
class WebhookController extends Controller
{
    public function razorpay(Request $request, PaymentGateway $gateway): Response
    {
        $body = $request->getContent();
        if (! $gateway->verifyWebhook($body, (string) $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 400);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?: [];
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $body));

        try {
            // Its own (nested) transaction, so on PostgreSQL a duplicate rolls back to a
            // savepoint instead of poisoning any surrounding transaction.
            $event = DB::transaction(fn () => WebhookEvent::query()->create([
                'gateway' => 'razorpay',
                'event_id' => $eventId,
                'type' => (string) ($payload['event'] ?? 'unknown'),
                'payload' => $payload,
                'signature_valid' => true,
                'received_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->noContent(); // Redelivery of an event we already have.
        }

        ProcessWebhookEvent::dispatch($event->id);

        return response()->noContent();
    }
}
