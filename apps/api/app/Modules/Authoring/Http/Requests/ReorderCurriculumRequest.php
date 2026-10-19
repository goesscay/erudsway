<?php

namespace App\Modules\Authoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The full new order: every section of the course, each with every lecture it should hold.
 * Lectures can move between sections.
 */
class ReorderCurriculumRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sections' => ['present', 'array'],
            'sections.*.id' => ['required', 'integer', 'distinct'],
            'sections.*.lectures' => ['present', 'array'],
            'sections.*.lectures.*' => ['integer', 'distinct'],
        ];
    }
}
