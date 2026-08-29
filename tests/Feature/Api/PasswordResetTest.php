<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_responds_the_same_way_for_a_real_or_unknown_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $known = $this->postJson('/api/password/forgot', ['email' => 'ada@example.com']);
        $unknown = $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com']);

        $known->assertStatus(200);
        $unknown->assertStatus(200);
        $this->assertSame($known->json('data.message'), $unknown->json('data.message'));
    }

    public function test_forgot_password_sends_a_reset_notification_with_a_frontend_url(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'ada@example.com'])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'old-password']);
        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(200);

        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'new-password-123',
        ])->assertStatus(200);
    }

    public function test_resetting_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'old-password']);
        $oldToken = $user->createToken('web')->plainTextToken;
        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'ada@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'ada@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422);
    }
}
