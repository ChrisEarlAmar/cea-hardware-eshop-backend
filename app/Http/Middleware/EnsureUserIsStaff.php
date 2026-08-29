<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privilege-based authorization: staff can deliberately see every
 * customer's data, as an explicit narrow grant - a different pattern from
 * the resource-ownership checks used everywhere else in this app (a
 * customer can only ever see their own cart/orders/account).
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_staff) {
            abort(403, 'This action requires staff access.');
        }

        return $next($request);
    }
}
