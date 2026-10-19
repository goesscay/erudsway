<?php

namespace App\Modules\Certificates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Certificates\Http\Resources\CertificateResource;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Certificates\Services\CertificateRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CertificateController extends Controller
{
    /** My certificates, newest first. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return CertificateResource::collection(
            Certificate::query()->where('user_id', $user->id)->with(['enrollment', 'course:id,slug'])->latest('issued_at')->get()
        );
    }

    /**
     * Public verification. Anyone with the code can see who earned it, for which course,
     * and whether it is still valid.
     */
    public function show(string $code): CertificateResource
    {
        return new CertificateResource($this->find($code));
    }

    public function pdf(string $code, CertificateRenderer $renderer): Response
    {
        $certificate = $this->find($code);
        abort_unless($certificate->isValid(), 404);

        return response($renderer->render($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$renderer->filename($certificate).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function find(string $code): Certificate
    {
        return Certificate::query()->where('code', strtoupper(trim($code)))->with(['enrollment', 'course:id,slug'])->firstOrFail();
    }
}
