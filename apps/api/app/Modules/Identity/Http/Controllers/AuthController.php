<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\RegisterUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Services\LegacyPasswords;
use App\Support\Http\DomainException;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Cookie-based (Sanctum SPA) authentication for the web frontend.
 * Call GET /sanctum/csrf-cookie first, then send X-XSRF-TOKEN on these requests.
 */
class AuthController extends Controller
{
    /**
     * Create a student account and sign the new user in.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request, RegisterUser $register): UserResource
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->safe()->only(['name', 'email', 'password']);
        $user = $register->handle($data, Phone::normalise($request->safe()->string('phone')->toString()));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return new UserResource($user->load('profile'));
    }

    /**
     * Sign in with email and password.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, LegacyPasswords $legacy): UserResource
    {
        $credentials = $request->safe()->only(['email', 'password']);
        // Accounts moved from the old site may still hold their Moodle password.
        $legacy->upgrade((string) $credentials['email'], (string) $credentials['password']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            throw new DomainException('ACCOUNT_SUSPENDED', 'This account has been suspended. Please contact support.', 403);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return new UserResource($user->load(['profile', 'instructorProfile']));
    }

    /** Sign out and invalidate the session. */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
