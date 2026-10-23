<?php

namespace App\Modules\Fulfilment\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

/** Start the free simulator trial. A mobile number is needed; send it here if the profile has none. */
class StartTrialRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:20', Phone::rule()],
        ];
    }
}
