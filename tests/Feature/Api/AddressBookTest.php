<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_addresses_require_authentication(): void
    {
        $this->getJson('/api/addresses')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_user_can_create_a_personal_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->addressPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.scope', 'personal')
            ->assertJsonPath('data.label', 'Warehouse');

        $address = Address::sole();
        $this->assertSame($user->id, $address->user_id);
        $this->assertNull($address->account_id);
    }

    public function test_creating_a_shared_address_requires_belonging_to_an_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', [...$this->addressPayload(), 'shared' => true])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, Address::count());
    }

    public function test_a_member_can_create_a_shared_company_address(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', [...$this->addressPayload(), 'shared' => true]);

        $response->assertStatus(201)->assertJsonPath('data.scope', 'company');

        $address = Address::sole();
        $this->assertNull($address->user_id);
        $this->assertSame($account->id, $address->account_id);
    }

    public function test_index_lists_personal_and_company_addresses_with_their_scope(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        Address::create([...$this->addressPayload(), 'user_id' => $user->id, 'label' => 'Home']);
        Address::create([...$this->addressPayload(), 'account_id' => $account->id, 'label' => 'HQ']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/addresses');

        $response->assertStatus(200);
        $scopesByLabel = collect($response->json('data'))->pluck('scope', 'label');

        $this->assertSame('personal', $scopesByLabel['Home']);
        $this->assertSame('company', $scopesByLabel['HQ']);
    }

    public function test_a_user_does_not_see_another_accounts_shared_address(): void
    {
        $accountA = Account::create(['name' => 'Account A']);
        $accountB = Account::create(['name' => 'Account B']);
        $userA = User::factory()->create(['account_id' => $accountA->id, 'role' => 'owner']);
        User::factory()->create(['account_id' => $accountB->id, 'role' => 'owner']);

        Address::create([...$this->addressPayload(), 'account_id' => $accountB->id, 'label' => 'B HQ']);

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/addresses');

        $labels = collect($response->json('data'))->pluck('label');
        $this->assertNotContains('B HQ', $labels);
    }

    public function test_a_user_cannot_edit_another_users_personal_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = Address::create([...$this->addressPayload(), 'user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/addresses/{$address->id}", ['label' => 'Hacked'])
            ->assertStatus(404);

        $this->assertNotSame('Hacked', $address->fresh()->label);
    }

    public function test_a_user_in_a_different_account_cannot_edit_a_shared_address(): void
    {
        $accountA = Account::create(['name' => 'Account A']);
        $accountB = Account::create(['name' => 'Account B']);
        $userB = User::factory()->create(['account_id' => $accountB->id, 'role' => 'member']);

        $address = Address::create([...$this->addressPayload(), 'account_id' => $accountA->id]);

        $this->actingAs($userB, 'sanctum')
            ->patchJson("/api/addresses/{$address->id}", ['label' => 'Hacked'])
            ->assertStatus(404);

        $this->assertNotSame('Hacked', $address->fresh()->label);
    }

    public function test_any_account_member_can_edit_the_shared_address_not_just_the_owner(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $member = User::factory()->create(['account_id' => $account->id, 'role' => 'member']);
        $address = Address::create([...$this->addressPayload(), 'account_id' => $account->id]);

        $this->actingAs($member, 'sanctum')
            ->patchJson("/api/addresses/{$address->id}", ['label' => 'Updated HQ'])
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'Updated HQ');
    }

    public function test_a_user_cannot_delete_another_users_personal_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = Address::create([...$this->addressPayload(), 'user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}")
            ->assertStatus(404);

        $this->assertNotNull($address->fresh());
    }

    public function test_a_user_can_delete_their_own_personal_address(): void
    {
        $user = User::factory()->create();
        $address = Address::create([...$this->addressPayload(), 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}")
            ->assertStatus(200);

        $this->assertNull($address->fresh());
    }

    public function test_setting_a_new_default_unsets_the_previous_personal_default(): void
    {
        $user = User::factory()->create();
        $first = Address::create([...$this->addressPayload(), 'user_id' => $user->id, 'is_default' => true]);
        $second = Address::create([...$this->addressPayload(), 'user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/addresses/{$second->id}", ['is_default' => true])
            ->assertStatus(200);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_setting_a_company_default_does_not_touch_the_users_personal_default(): void
    {
        $account = Account::create(['name' => 'Acme Hardware']);
        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'owner']);

        $personal = Address::create([...$this->addressPayload(), 'user_id' => $user->id, 'is_default' => true]);
        $company = Address::create([...$this->addressPayload(), 'account_id' => $account->id, 'is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/addresses/{$company->id}", ['is_default' => true])
            ->assertStatus(200);

        $this->assertTrue($personal->fresh()->is_default);
        $this->assertTrue($company->fresh()->is_default);
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(): array
    {
        return [
            'label' => 'Warehouse',
            'recipient_name' => 'Jane Doe',
            'line1' => '123 Main St',
            'line2' => null,
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'US',
            'phone' => '555-0100',
        ];
    }
}
