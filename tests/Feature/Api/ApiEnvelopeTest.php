<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_failure_returns_422_envelope(): void
    {
        $response = $this->postJson('/api/echo', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['message']]]);
    }

    public function test_unauthenticated_request_returns_401_envelope(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson([
                'error' => [
                    'code' => 'unauthenticated',
                ],
            ]);
    }

    public function test_authenticated_user_can_access_protected_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unknown_route_returns_404_envelope(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'not_found',
                ],
            ]);
    }

    public function test_authorization_exception_returns_403_envelope(): void
    {
        Route::middleware('api')->get('/api/_test/forbidden', function () {
            throw new AuthorizationException('You cannot do that.');
        });

        $response = $this->getJson('/api/_test/forbidden');

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'You cannot do that.',
                ],
            ]);
    }

    public function test_unhandled_exception_returns_sanitized_500_envelope(): void
    {
        Route::middleware('api')->get('/api/_test/boom', function () {
            throw new RuntimeException('some internal detail that must not leak');
        });

        $response = $this->getJson('/api/_test/boom');

        $response->assertStatus(500)
            ->assertJson([
                'error' => [
                    'code' => 'server_error',
                    'message' => 'Something went wrong. Please try again later.',
                ],
            ]);

        $response->assertDontSee('some internal detail', false);
    }
}
