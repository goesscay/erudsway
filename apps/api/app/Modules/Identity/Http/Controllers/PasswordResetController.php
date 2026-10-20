<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Services\LegacyPasswords;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Email a password reset link.
     *
     * Always responds the same way so the endpoint cannot be used to discover accounts.
     *
     * @unauthenticated
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->safe()->only('email'));

        return ApiResponse::data([
            'message' => 'If an account exists for that email, we have sent a password reset link.',
        ]);
    }

    /**
     * Set a new password using the emailed token.
     *
     * @unauthenticated
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password', 'password_confirmation', 'token']);
        $setPassword = function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            LegacyPasswords::clear($user);
            event(new PasswordReset($user));
        };

        $status = Password::reset($credentials, $setPassword);
        if ($status === Password::INVALID_TOKEN) {
            // A link from an account staff created. Using it proves the email is theirs.
            $status = Password::broker('invites')->reset($credentials, function (User $user, string $password) use ($setPassword) {
                $setPassword($user, $password);
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            });
        }

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'This reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        return ApiResponse::data(['message' => 'Your password has been reset. You can now log in.']);
    }
}
