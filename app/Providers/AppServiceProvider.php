<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The default limiter for every api/* route (applied globally via
        // ->throttleApi() in bootstrap/app.php) - keyed by authenticated
        // user when there is one, falling back to IP for guests.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Keyed by email+IP together, not just IP: a credential-stuffing
        // botnet rotates IPs specifically to dodge a pure per-IP limit, and
        // a shared office IP shouldn't lock out every legitimate user
        // behind it just because one of them mistyped a password. Set
        // above the per-account lockout threshold below (5) so, from a
        // single IP, the more surgical identity-based lockout is what
        // actually kicks in first - this limiter is the coarser backstop
        // for raw request volume (e.g. many different emails from one IP).
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        // This backend has no web-based password-reset view of its own -
        // the link emailed to the user must point at the Next.js frontend,
        // which owns the actual reset form.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($user->email);
        });
    }
}
