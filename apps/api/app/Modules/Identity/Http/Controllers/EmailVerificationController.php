<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Target of the link in the verification email. Redirects back to the web app.
     * Browser-only, so it is left out of the API docs.
     */
    #[ExcludeRouteFromDocs]
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $user = User::query()->where('uuid', $id)->first();

        if (! $request->hasValidSignature() || ! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away("{$frontend}/verify-email?status=invalid");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away("{$frontend}/verify-email?status=verified");
    }

    /** Send the verification email again. */
    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::data(['message' => 'Your email is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::data(['message' => 'We have sent a new verification link to your email.']);
    }
}
