<?php

namespace App\Modules\Authoring\Http\Requests;

use App\Modules\Catalog\Enums\CourseLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /** Trim list items and drop empty ones before validating. */
    protected function prepareForValidation(): void
    {
        foreach (['outcomes', 'requirements', 'audience'] as $key) {
            if (is_array($this->input($key))) {
                $this->merge([$key => array_values(array_filter(array_map(
                    fn ($v) => is_string($v) ? trim($v) : $v,
                    $this->input($key),
                ), fn ($v) => $v !== '' && $v !== null))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:5', 'max:120'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'language' => ['sometimes', 'required', Rule::in(config('catalog.languages'))],
            'level' => ['sometimes', 'required', Rule::enum(CourseLevel::class)],
            'category' => [
                'sometimes', 'required', 'string',
                Rule::exists('categories', 'slug')->whereNotNull('parent_id')->where('is_active', true),
            ],
            'outcomes' => ['sometimes', 'array', 'max:12'],
            'outcomes.*' => ['string', 'min:3', 'max:160'],
            'requirements' => ['sometimes', 'array', 'max:10'],
            'requirements.*' => ['string', 'min:3', 'max:160'],
            'audience' => ['sometimes', 'array', 'max:10'],
            'audience.*' => ['string', 'min:3', 'max:160'],
            /** Null means the course is free. Sending this key confirms the pricing step. */
            'price_tier_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('price_tiers', 'id')->where('is_active', true),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['category.exists' => 'Choose one of the listed subcategories.'];
    }
}
