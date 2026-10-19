<?php

namespace App\Modules\Certificates\Services;

use App\Modules\Certificates\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;

/** The certificate as an A4 landscape PDF, rendered on request from the issued snapshot. */
class CertificateRenderer
{
    public function render(Certificate $certificate): string
    {
        $hours = $certificate->duration_seconds / 3600;

        return Pdf::loadView('certificates::certificate', [
            'certificate' => $certificate,
            'length' => $hours >= 1 ? rtrim(rtrim(number_format($hours, 1), '0'), '.').' total hours' : max(1, (int) round($certificate->duration_seconds / 60)).' total minutes',
            'logo' => $this->logoDataUri(),
        ])->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(Certificate $certificate): string
    {
        return 'Erudsway-certificate-'.$certificate->serial.'.pdf';
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/erudsway-logo.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
