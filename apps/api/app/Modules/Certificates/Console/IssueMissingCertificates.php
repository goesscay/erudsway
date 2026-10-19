<?php

namespace App\Modules\Certificates\Console;

use App\Modules\Certificates\Services\CertificateIssuer;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Console\Command;

/** Issues certificates for completed enrollments that don't have one (e.g. after an import). */
class IssueMissingCertificates extends Command
{
    protected $signature = 'certificates:issue-missing';

    protected $description = 'Issue certificates for completed enrollments that have none';

    public function handle(CertificateIssuer $issuer): int
    {
        $issued = 0;
        Enrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('completed_at')
            ->whereNotExists(fn ($q) => $q->from('certificates')->whereColumn('certificates.enrollment_id', 'enrollments.id'))
            ->chunkById(200, function ($enrollments) use ($issuer, &$issued) {
                foreach ($enrollments as $enrollment) {
                    $issued += $issuer->issue($enrollment) ? 1 : 0;
                }
            });

        $this->info("Issued {$issued} certificate(s).");

        return self::SUCCESS;
    }
}
