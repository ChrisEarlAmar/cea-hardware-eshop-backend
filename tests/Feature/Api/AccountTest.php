<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_an_account_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/accounts', ['name' => 'Acme Hardware']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acme Hardware');

        $this->assertSame('owner', $user->fresh()->role);
        $this->assertSame($response->json('data.id'), $user->fresh()->account_id);
    }

    public function test_a_user_cannot_create_a_second_account(): void
    {
        $account = Account::create(['name' => 'Existing Co']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/accounts', ['name' => 'Another Co']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_user_can_join_an_existing_account_as_a_member(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/accounts/{$account->id}/join");

        $response->assertStatus(200);

        $user->refresh();
        $this->assertSame($account->id, $user->account_id);
        $this->assertSame('member', $user->role);
    }

    public function test_a_user_cannot_join_a_second_account(): void
    {
        $ownAccount = Account::create(['name' => 'Own Co']);
        $otherAccount = Account::create(['name' => 'Other Co']);
        $user = User::factory()->create(['account_id' => $ownAccount->id, 'role' => 'owner']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/accounts/{$otherAccount->id}/join");

        $response->assertStatus(422);
        $this->assertSame($ownAccount->id, $user->fresh()->account_id);
    }

    public function test_members_endpoint_requires_an_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/users')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'no_account');
    }

    public function test_members_endpoint_only_returns_same_account_users(): void
    {
        $accountA = Account::create(['name' => 'Account A']);
        $accountB = Account::create(['name' => 'Account B']);

        $ownerA = User::factory()->create(['account_id' => $accountA->id, 'role' => 'owner']);
        $memberA = User::factory()->create(['account_id' => $accountA->id, 'role' => 'member']);
        $ownerB = User::factory()->create(['account_id' => $accountB->id, 'role' => 'owner']);

        $response = $this->actingAs($ownerA, 'sanctum')->getJson('/api/account/users');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($ownerA->id, $ids);
        $this->assertContains($memberA->id, $ids);
        $this->assertNotContains($ownerB->id, $ids);
    }

    public function test_owner_can_remove_a_member(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);
        $member = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/account/users/{$member->id}")
            ->assertStatus(200);

        $member->refresh();
        $this->assertNull($member->account_id);
        $this->assertNull($member->role);
    }

    public function test_member_cannot_remove_another_member(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $memberOne = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);
        $memberTwo = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);

        $this->actingAs($memberOne, 'sanctum')
            ->deleteJson("/api/account/users/{$memberTwo->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertSame($account->id, $memberTwo->fresh()->account_id);
    }

    public function test_owner_cannot_remove_a_member_from_a_different_account(): void
    {
        $accountA = Account::create(['name' => 'Account A']);
        $accountB = Account::create(['name' => 'Account B']);

        $ownerA = User::factory()->create(['account_id' => $accountA->id, 'role' => 'owner']);
        $memberB = User::factory()->create(['account_id' => $accountB->id, 'role' => 'member']);

        $this->actingAs($ownerA, 'sanctum')
            ->deleteJson("/api/account/users/{$memberB->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->assertSame($accountB->id, $memberB->fresh()->account_id);
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $owner = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/account/users/{$owner->id}")
            ->assertStatus(422);

        $this->assertSame($account->id, $owner->fresh()->account_id);
    }
}
