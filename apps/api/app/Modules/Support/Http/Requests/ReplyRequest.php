<?php

namespace App\Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** A message on a ticket, with up to three screenshots or PDFs of 5 MB each. */
class ReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['sometimes', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
        ];
    }

    /** @return list<UploadedFile> */
    public function uploads(): array
    {
        return array_values($this->file('attachments', []));
    }
}
