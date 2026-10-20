<?php

namespace App\Modules\Authoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Changes to an edition in the course builder. Simulator and doubt-session flags are staff-only (checked in the controller). */
class UpdateEditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'price_tier_id' => ['sometimes', 'integer', Rule::exists('price_tiers', 'id')->where('is_active', true)],
            'includes' => ['sometimes', 'array', 'max:10'],
            'includes.*' => ['string', 'min:2', 'max:160'],
            'is_recommended' => ['sometimes', 'boolean'],
            'access_days' => ['sometimes', 'nullable', 'integer', 'between:1,3650'],
            'video_view_limit' => ['sometimes', 'nullable', 'integer', 'between:1,20'],
            'grants_simulator' => ['sometimes', 'boolean'],
            'includes_doubt_session' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['price_tier_id.exists' => 'Choose one of the listed prices.'];
    }
}
