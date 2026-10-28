<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Platform\Enums\ContactTopic;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A message from the website's Contact page. */
class StoreContactMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20', Phone::rule()],
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // Left empty by people; filled in by bots that complete every field.
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['message.min' => 'Tell us a little more (at least 10 characters).'];
    }
}
