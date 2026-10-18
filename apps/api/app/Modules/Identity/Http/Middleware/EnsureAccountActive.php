<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Models\User;
use App\Support\Http\DomainException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who was suspended while signed in.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }

            throw new DomainException('ACCOUNT_SUSPENDED', 'This account has been suspended. Please contact support.', 403);
        }

        return $next($request);
    }
}
