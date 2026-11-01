<?php

namespace Tests\Feature\Api\Admin;

use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_staff_user_is_forbidden_from_the_tax_rate_admin_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/tax-rates')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/tax-rates', [
            'state' => 'CA', 'rate_percent' => '7.25',
        ])->assertStatus(403);
    }

    public function test_staff_can_create_a_tax_rate(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/tax-rates', [
            'state' => 'ca', 'rate_percent' => '7.25',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.state', 'ca')
            ->assertJsonPath('data.rate_percent', '7.25');

        $this->assertDatabaseHas('tax_rates', ['state' => 'ca', 'rate_percent' => '7.25']);
    }

    public function test_a_duplicate_state_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        TaxRate::create(['state' => 'NY', 'rate_percent' => '8.00']);

        $this->actingAs($staff, 'sanctum')->postJson('/api/admin/tax-rates', [
            'state' => 'NY', 'rate_percent' => '9.00',
        ])->assertStatus(422);
    }

    public function test_a_rate_over_100_percent_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff, 'sanctum')->postJson('/api/admin/tax-rates', [
            'state' => 'TX', 'rate_percent' => '150',
        ])->assertStatus(422);
    }

    public function test_staff_can_update_a_tax_rate(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $taxRate = TaxRate::create(['state' => 'WA', 'rate_percent' => '6.50']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/tax-rates/{$taxRate->id}", [
            'state' => 'WA', 'rate_percent' => '7.00',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.rate_percent', '7.00');
    }

    public function test_staff_can_delete_a_tax_rate(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $taxRate = TaxRate::create(['state' => 'FL', 'rate_percent' => '6.00']);

        $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/tax-rates/{$taxRate->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('tax_rates', ['id' => $taxRate->id]);
    }

    public function test_staff_can_list_tax_rates(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        TaxRate::create(['state' => 'CA', 'rate_percent' => '7.25']);
        TaxRate::create(['state' => 'NY', 'rate_percent' => '8.00']);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/tax-rates');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
