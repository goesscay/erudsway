<?php

namespace App\Modules\Certificates\Http\Resources;

use App\Modules\Certificates\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A certificate as shown to its owner and on the public verification page.
 *
 * @mixin Certificate
 */
class CertificateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'serial' => $this->serial,
            'recipient_name' => $this->recipient_name,
            'course_title' => $this->course_title,
            'instructor_name' => $this->instructor_name,
            'duration_seconds' => $this->duration_seconds,
            'issued_at' => $this->issued_at->toIso8601String(),
            /** False once the learner's access to the course has ended (refund or revoke). */
            'valid' => $this->isValid(),
            'pdf_url' => url("/api/v1/certificates/{$this->code}/pdf"),
            'course_slug' => $this->whenLoaded('course', fn () => $this->course->slug),
        ];
    }
}
