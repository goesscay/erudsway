<?php

namespace App\Modules\Certificates\Services;

use App\Modules\Certificates\Models\Certificate;
use App\Modules\Certificates\Notifications\CertificateIssued;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues one certificate per completed enrollment. Safe to call repeatedly: an enrollment
 * that already has a certificate keeps it.
 */
class CertificateIssuer
{
    /** No 0/O, 1/I/L — codes get read aloud and typed in. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function issue(Enrollment $enrollment): ?Certificate
    {
        if ($enrollment->completed_at === null || $enrollment->status !== EnrollmentStatus::Active) {
            return null;
        }
        $existing = Certificate::query()->where('enrollment_id', $enrollment->id)->first();
        if ($existing) {
            return $existing;
        }

        $course = $enrollment->course()->with(['instructor', 'instructorProfile'])->firstOrFail();
        $user = $enrollment->user()->firstOrFail();

        try {
            $certificate = DB::transaction(function () use ($enrollment, $course, $user) {
                $certificate = Certificate::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'serial' => 'pending-'.$enrollment->id,
                    'code' => $this->uniqueCode(),
                    'recipient_name' => $user->name,
                    'course_title' => $course->title,
                    'instructor_name' => $course->instructorProfile->display_name ?? $course->instructor->name,
                    'duration_seconds' => $course->duration_seconds,
                    'issued_at' => $enrollment->completed_at ?? now(),
                ]);
                $certificate->forceFill(['serial' => sprintf('ERW-C-%s-%05d', $certificate->issued_at->format('Y'), $certificate->id)])->save();

                return $certificate;
            });
        } catch (UniqueConstraintViolationException) {
            // Issued concurrently by another request.
            return Certificate::query()->where('enrollment_id', $enrollment->id)->first();
        }

        $user->notify(new CertificateIssued($certificate));

        return $certificate;
    }

    private function uniqueCode(): string
    {
        do {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = implode('-', str_split($raw, 4));
        } while (Certificate::query()->where('code', $code)->exists());

        return $code;
    }
}
