<?php

namespace App\Modules\Catalog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets browsers and a CDN briefly cache anonymous catalogue reads. Responses to signed-in
 * users stay private so nothing personalised is ever shared.
 */
class PublicCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $seconds = (int) config('catalog.public_cache_seconds');
            $response->headers->set(
                'Cache-Control',
                $request->user() ? 'private, no-store' : "public, max-age={$seconds}, s-maxage={$seconds}, stale-while-revalidate=300",
            );
            $response->setVary(['Cookie', 'Accept'], false);
        }

        return $response;
    }
}
