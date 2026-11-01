<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_payment_methods_requires_authentication(): void
    {
        $this->getJson('/api/payment-methods')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_starting_a_setup_intent_requires_authentication(): void
    {
        $this->postJson('/api/payment-methods')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_removing_a_payment_method_requires_authentication(): void
    {
        $this->deleteJson('/api/payment-methods/pm_123')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_user_can_list_their_saved_cards(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('listPaymentMethods')->once()->andReturn([
                ['id' => 'pm_abc', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
            ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/payment-methods')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', 'pm_abc')
            ->assertJsonPath('data.0.brand', 'visa')
            ->assertJsonPath('data.0.last4', '4242');
    }

    public function test_a_user_with_no_saved_cards_sees_an_empty_list(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('listPaymentMethods')->once()->andReturn([]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/payment-methods');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_starting_a_setup_intent_returns_its_client_secret(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('createSetupIntentFor')->once()->andReturn([
                'id' => 'seti_123',
                'client_secret' => 'seti_123_secret_abc',
            ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/payment-methods')
            ->assertStatus(201)
            ->assertJsonPath('data.id', 'seti_123')
            ->assertJsonPath('data.client_secret', 'seti_123_secret_abc');
    }

    public function test_starting_a_setup_intent_is_rejected_when_payment_is_not_configured(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/payment-methods')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'payment_not_configured');
    }

    public function test_a_user_can_remove_a_card_that_belongs_to_them(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('listPaymentMethods')->once()->andReturn([
                ['id' => 'pm_mine', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
            ]);
            $mock->shouldReceive('detachPaymentMethod')->once()->with('pm_mine');
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/payment-methods/pm_mine')
            ->assertStatus(200)
            ->assertJsonPath('data.detached', true);
    }

    /**
     * The core proof this endpoint exists for: a payment method id that
     * doesn't show up in the requesting user's own saved-card list can't be
     * detached through this endpoint, regardless of whether it's a real
     * Stripe id belonging to someone else.
     */
    public function test_a_user_cannot_remove_a_card_that_does_not_belong_to_them(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('listPaymentMethods')->once()->andReturn([
                ['id' => 'pm_mine', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030],
            ]);
            $mock->shouldNotReceive('detachPaymentMethod');
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/payment-methods/pm_someone_elses')
            ->assertStatus(404);
    }
}
