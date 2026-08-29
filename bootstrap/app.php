<?php

use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's default 60-requests/minute-per-user-or-IP limiter,
        // applied to every api/* route via the "api" middleware group.
        $middleware->throttleApi();

        $middleware->prepend(RequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias(['staff' => EnsureUserIsStaff::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API response - success or error - follows one consistent
        // envelope. This renderer is the single place that decides the
        // error shape for API requests; non-API requests fall through to
        // Laravel's normal rendering.
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('validation_failed', $e->getMessage(), 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(
                    'unauthenticated',
                    'Authentication is required to access this resource.',
                    401,
                );
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                // Laravel converts AuthorizationException into
                // AccessDeniedHttpException before it reaches this renderer,
                // so both need to be checked here.
                return ApiResponse::error(
                    'forbidden',
                    $e->getMessage() ?: 'You are not authorized to perform this action.',
                    403,
                );
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return ApiResponse::error('not_found', 'The requested resource was not found.', 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    'http_error',
                    $e->getMessage() ?: 'An error occurred.',
                    $e->getStatusCode(),
                );
            }

            report($e);

            return ApiResponse::error(
                'server_error',
                'Something went wrong. Please try again later.',
                500,
            );
        });
    })->create();
