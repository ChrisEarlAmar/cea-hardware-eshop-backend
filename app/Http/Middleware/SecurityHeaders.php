<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A standard defense-in-depth header set applied to every response. Each
 * header blocks one concrete browser-side attack: clickjacking
 * (X-Frame-Options), MIME-type sniffing (X-Content-Type-Options), protocol
 * downgrade (HSTS), and unwanted feature access or leaking the referrer
 * cross-origin (Permissions-Policy, Referrer-Policy).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
