<?php

namespace Tests\Feature\Api\Admin;

use App\Models\GiftCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_staff_user_is_forbidden_from_the_gift_card_admin_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/gift-cards')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/gift-cards', [
            'code' => 'NOPE', 'initial_balance' => '10.00',
        ])->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/gift-cards')->assertStatus(401);
    }

    public function test_staff_can_issue_a_gift_card(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/gift-cards', [
            'code' => 'HOLIDAY25', 'initial_balance' => '25.00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'HOLIDAY25')
            ->assertJsonPath('data.initial_balance', '25.00')
            ->assertJsonPath('data.remaining_balance', '25.00')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('gift_cards', ['code' => 'HOLIDAY25', 'remaining_balance' => '25.00']);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $staff->id, 'action' => 'gift_card.create']);
    }

    public function test_a_duplicate_gift_card_code_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        GiftCard::create(['code' => 'DUPLICATE', 'initial_balance' => '10.00', 'remaining_balance' => '10.00']);

        $this->actingAs($staff, 'sanctum')->postJson('/api/admin/gift-cards', [
            'code' => 'DUPLICATE', 'initial_balance' => '10.00',
        ])->assertStatus(422);
    }

    public function test_staff_can_deactivate_a_gift_card_without_deleting_it(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $giftCard = GiftCard::create(['code' => 'RETIRE', 'initial_balance' => '10.00', 'remaining_balance' => '4.00']);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/gift-cards/{$giftCard->id}");

        $response->assertStatus(200)->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('gift_cards', ['id' => $giftCard->id, 'active' => false, 'remaining_balance' => '4.00']);
    }

    public function test_staff_can_list_gift_cards(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        GiftCard::create(['code' => 'A', 'initial_balance' => '10.00', 'remaining_balance' => '10.00']);
        GiftCard::create(['code' => 'B', 'initial_balance' => '20.00', 'remaining_balance' => '20.00']);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/gift-cards');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
