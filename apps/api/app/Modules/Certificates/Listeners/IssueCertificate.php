<?php

namespace App\Modules\Certificates\Listeners;

use App\Modules\Certificates\Services\CertificateIssuer;
use App\Modules\Learning\Events\CourseCompleted;

class IssueCertificate
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    public function handle(CourseCompleted $event): void
    {
        $this->issuer->issue($event->enrollment);
    }
}
