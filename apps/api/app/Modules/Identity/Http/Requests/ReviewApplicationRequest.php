<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewApplicationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // A rejection must explain itself to the applicant; approval notes are optional.
        $rejecting = $this->routeIs('*.reject');

        return [
            'notes' => [$rejecting ? 'required' : 'nullable', 'string', $rejecting ? 'min:10' : 'min:0', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['notes.required' => 'Explain the decision so the applicant knows what to improve.'];
    }
}
