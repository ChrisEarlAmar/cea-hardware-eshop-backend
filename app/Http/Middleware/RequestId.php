<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Threads one correlation id through a request: reuses an incoming
 * X-Request-Id if the caller already has one (the Next.js BFF sets one on
 * every server-to-server call - see middleware.ts and lib/api.ts there), or
 * mints a new one otherwise. Every log line for this request shares it via
 * Log::shareContext(), and it's echoed back on the response so a failure
 * reported by a user can be grepped across both the Next.js and Laravel
 * logs for the exact same request.
 */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::ulid();

        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
