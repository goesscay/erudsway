<?php

namespace App\Modules\Support\Http\Requests;

use App\Modules\Support\Enums\TicketCategory;
use Illuminate\Validation\Rule;

/** A new Help Centre ticket. */
class StoreTicketRequest extends ReplyRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'subject' => ['required', 'string', 'min:4', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'module' => ['nullable', 'string', 'max:120'],
            'order_reference' => ['nullable', 'string', 'max:60'],
        ];
    }
}
