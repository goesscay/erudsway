<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Enums\ContactTopic;
use App\Modules\Platform\Http\Requests\StoreContactMessageRequest;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Notifications\ContactMessageReceived;
use App\Support\Http\ApiResponse;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/** The website's Contact page form. */
class ContactController extends Controller
{
    /**
     * The topics a visitor can choose.
     *
     * @unauthenticated
     */
    public function topics(): JsonResponse
    {
        return ApiResponse::data([
            /** @var list<array{value: 'course'|'payment'|'technical'|'simulator'|'teaching'|'partnership'|'other', label: string}> */
            'items' => array_map(fn (ContactTopic $t) => ['value' => $t->value, 'label' => $t->label()], ContactTopic::cases()),
        ]);
    }

    /**
     * Send a message to the Erudsway team. It's kept for staff and emailed to the support inbox.
     *
     * @unauthenticated
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $message = ContactMessage::query()->create([
            'user_id' => Auth::guard('sanctum')->id(),
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => Phone::normalise($data['phone'] ?? null),
            'topic' => $data['topic'],
            'message' => trim($data['message']),
            'ip' => $request->ip(),
        ]);
        Notification::route('mail', (string) config('app.support_email'))->notify(new ContactMessageReceived($message));

        return ApiResponse::data(['message' => 'Thanks — your message has reached our team. We usually reply within one working day.'], status: 201);
    }
}
