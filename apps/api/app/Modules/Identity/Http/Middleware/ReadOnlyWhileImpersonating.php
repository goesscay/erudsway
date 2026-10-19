<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Services\Impersonation;
use App\Support\Http\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin is viewing as someone else, nothing may be changed on that person's
 * behalf — no purchases, progress, posts or settings. Only reading, starting video
 * playback, and ending the impersonation (or signing out) get through.
 */
class ReadOnlyWhileImpersonating
{
    private const ALLOWED = ['identity.impersonation.stop', 'identity.logout', 'learning.playback'];

    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->isMethodSafe()
            && $this->impersonation->impersonatorId($request) !== null
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)
        ) {
            throw new DomainException('IMPERSONATING', 'You’re viewing as another user, so changes are switched off. Stop viewing as them to make changes.', 403);
        }

        return $next($request);
    }
}
