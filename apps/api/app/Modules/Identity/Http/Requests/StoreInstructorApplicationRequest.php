<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\TeachingExperience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstructorApplicationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'min:10', 'max:120'],
            'topics' => ['required', 'array', 'min:1', 'max:5'],
            'topics.*' => ['required', 'string', 'distinct', 'min:2', 'max:40'],
            'experience' => ['required', 'string', 'min:100', 'max:3000'],
            'teaching_experience' => ['required', Rule::enum(TeachingExperience::class)],
            'sample_url' => ['nullable', 'url:https', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'experience.min' => 'Tell us a little more — at least 100 characters about your professional experience.',
            'sample_url.url' => 'Enter a full https:// link to a sample lesson, video or portfolio.',
        ];
    }
}
