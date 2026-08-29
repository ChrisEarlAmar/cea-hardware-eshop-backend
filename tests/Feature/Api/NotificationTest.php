<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_list_their_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new OrderPlaced($this->fakeOrder($user)));

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.unread_count'));
        $this->assertSame('order_placed', $response->json('data.0.type'));
    }

    public function test_marking_a_notification_read_clears_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new OrderPlaced($this->fakeOrder($user)));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertStatus(200);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');
        $this->assertSame(0, $response->json('meta.unread_count'));
        $this->assertNotNull($response->json('data.0.read_at'));
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $owner->notify(new OrderPlaced($this->fakeOrder($owner)));
        $notificationId = $owner->notifications()->first()->id;

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertStatus(404);
    }

    private function fakeOrder(User $user): \App\Models\Order
    {
        return \App\Models\Order::create([
            'user_id' => $user->id,
            'status' => \App\Models\Order::STATUS_AWAITING_PAYMENT,
            'total' => '10.00',
        ]);
    }
}
