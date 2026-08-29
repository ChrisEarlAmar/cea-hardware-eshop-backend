<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** After this many consecutive failed logins, the account locks out. */
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_MINUTES = 15;

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // The User model casts `password` as 'hashed', so this is
            // hashed automatically on save - never store it as plain text.
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('web')->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], status: 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user !== null && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Please try again in a few minutes.'],
            ]);
        }

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->recordFailedAttempt($user);

            // Reported against `email` (not `password`) so the failure
            // doesn't reveal whether the account exists.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->failed_login_attempts > 0 || $user->locked_until !== null) {
            $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        }

        $token = $user->createToken('web')->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Logged out.']);
    }

    /**
     * Always responds the same way regardless of whether the email exists,
     * so this endpoint can't be used to enumerate registered accounts. The
     * actual reset link (if any) is emailed via Laravel's password broker,
     * which reuses the User model's own Notifiable trait.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink(['email' => $validated['email']]);

        return ApiResponse::success([
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->update([
                    'password' => $password,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                // Any token issued before the password changed should stop
                // working - a leaked-but-now-outdated session is no longer
                // useful to whoever leaked it.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return ApiResponse::success(['message' => 'Password has been reset. Please log in again.']);
    }

    private function recordFailedAttempt(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $lockedUntil = $user->locked_until;

        if ($attempts >= self::LOCKOUT_THRESHOLD) {
            $lockedUntil = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $user->update(['failed_login_attempts' => $attempts, 'locked_until' => $lockedUntil]);
    }
}
