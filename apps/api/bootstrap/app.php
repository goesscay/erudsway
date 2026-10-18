<?php

use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // API routes live in each module's routes.php and are mounted under /api/v1
        // by App\Support\Modules\ModuleServiceProvider.
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-based Sanctum auth for the Next.js frontend on the same parent domain.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request) || $request->expectsJson());

        $exceptions->render(function (DomainException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiResponse::error($e->errorCode, $e->getMessage(), $e->status) : null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? ApiResponse::error('VALIDATION_FAILED', 'Some fields need your attention.', 422, $e->errors())
                : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiResponse::error('UNAUTHENTICATED', 'Please log in to continue.', 401) : null;
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? ApiResponse::error('FORBIDDEN', 'You do not have permission to do this.', 403)
                : null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            return $isApi($request) ? ApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? ApiResponse::error('TOO_MANY_REQUESTS', 'Too many attempts. Please wait and try again.', 429)
                    ->withHeaders($e->getHeaders())
                : null;
        });

        // Anything else: never leak internals outside debug mode.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request) || config('app.debug')) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return ApiResponse::error('SERVER_ERROR', 'Something went wrong on our side.', $status);
        });
    })->create();
