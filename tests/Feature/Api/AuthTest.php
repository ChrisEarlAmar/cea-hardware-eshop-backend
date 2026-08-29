<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['password']]]);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.fields.email.0', 'The email has already been taken.');
    }

    public function test_a_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_token_can_access_the_protected_user_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_repeated_failed_logins_lock_the_account(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ada@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // The 5th bad attempt above crossed the lockout threshold - even a
        // CORRECT password is now rejected until the lock expires.
        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $user = User::where('email', 'ada@example.com')->first();
        $this->assertTrue($user->isLocked());
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'password123']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password123'])->assertStatus(200);

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_excess_login_attempts_from_the_same_email_and_ip_are_rate_limited(): void
    {
        // This email is never registered, so AuthController's per-account
        // lockout never engages (there's no account to lock) - isolating
        // this test to just the email+IP rate limiter below it.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', [
                'email' => 'throttle-target@example.com',
                'password' => 'wrong-password-'.$i,
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'throttle-target@example.com',
            'password' => 'wrong-password-again',
        ])->assertStatus(429);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The auth guard memoizes its resolved user for its own lifetime,
        // and this test's two requests share one app container (unlike two
        // real, separate HTTP requests). Without this, the assertion below
        // would see the guard's stale resolution from the /api/logout call
        // above rather than re-checking the now-deleted token.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertStatus(401);
    }
}
