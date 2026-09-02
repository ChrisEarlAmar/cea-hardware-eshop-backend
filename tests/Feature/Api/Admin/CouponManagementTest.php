<?php

namespace Tests\Feature\Api\Admin;

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CouponController;
use App\Models\AdminAuditLog;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NOTE: these routes are not yet registered in routes/api.php (see
 * ## ROUTES NEEDED in the delivering agent's report) - that file is owned
 * by a concurrent agent and must not be edited here. setUp() registers the
 * exact routes/middleware this feature needs, standing in for the real
 * route entries. SubstituteBindings is listed explicitly because it's
 * normally supplied for free by the "api" middleware group that
 * routes/api.php is loaded under - registering routes ad hoc here bypasses
 * that group, so implicit {coupon} route-model binding needs it spelled
 * out or it silently resolves to an empty, unsaved model instead.
 */
class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'staff', SubstituteBindings::class])->group(function () {
            Route::get('/api/admin/coupons', [CouponController::class, 'index']);
            Route::post('/api/admin/coupons', [CouponController::class, 'store']);
            Route::patch('/api/admin/coupons/{coupon}', [CouponController::class, 'update']);
            Route::delete('/api/admin/coupons/{coupon}', [CouponController::class, 'destroy']);
            Route::get('/api/admin/audit-log', [AuditLogController::class, 'index']);
        });
    }

    public function test_a_non_staff_user_is_forbidden_from_the_coupon_admin_surface(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code' => 'BLOCKED10', 'type' => Coupon::TYPE_PERCENT, 'value' => '10.00',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/coupons')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'NOPE', 'type' => Coupon::TYPE_PERCENT, 'value' => '10',
        ])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->patchJson("/api/admin/coupons/{$coupon->id}", [
            'code' => 'BLOCKED10', 'type' => Coupon::TYPE_PERCENT, 'value' => '20',
        ])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/coupons/{$coupon->id}")
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/coupons')->assertStatus(401);
    }

    public function test_staff_can_list_coupons(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        Coupon::create(['code' => 'FIRST5', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);
        Coupon::create(['code' => 'SECOND10', 'type' => Coupon::TYPE_PERCENT, 'value' => '10.00']);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/coupons');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_staff_can_create_a_coupon(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'WELCOME10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => '10',
            'max_uses' => 100,
            'min_order_total' => '50.00',
            'expires_at' => '2027-01-01',
            'active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'WELCOME10')
            ->assertJsonPath('data.type', 'percent')
            ->assertJsonPath('data.value', '10.00')
            ->assertJsonPath('data.max_uses', 100)
            ->assertJsonPath('data.used_count', 0)
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME10', 'type' => 'percent']);
    }

    public function test_a_percent_value_over_100_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'TOOBIG', 'type' => Coupon::TYPE_PERCENT, 'value' => '150',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.value.0', 'A percent coupon cannot exceed 100.');

        $this->assertDatabaseMissing('coupons', ['code' => 'TOOBIG']);
    }

    public function test_a_fixed_value_over_100_is_allowed(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'BIGFIXED', 'type' => Coupon::TYPE_FIXED, 'value' => '150',
        ])->assertStatus(201);
    }

    public function test_a_duplicate_coupon_code_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        Coupon::create(['code' => 'DUPLICATE', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);

        $response = $this->actingAs($staff, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'DUPLICATE', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('code', $response->json('error.fields'));
    }

    public function test_staff_can_update_a_coupon_and_keep_its_own_code(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $coupon = Coupon::create(['code' => 'EDITME', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/coupons/{$coupon->id}", [
            'code' => 'EDITME',
            'type' => Coupon::TYPE_FIXED,
            'value' => '7.50',
            'active' => true,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.value', '7.50');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'value' => '7.50']);
    }

    public function test_updating_a_coupon_to_another_coupons_code_is_rejected(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        Coupon::create(['code' => 'TAKEN', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);
        $coupon = Coupon::create(['code' => 'MINE', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);

        $response = $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/coupons/{$coupon->id}", [
            'code' => 'TAKEN', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('code', $response->json('error.fields'));
    }

    public function test_staff_can_deactivate_a_coupon_without_deleting_it(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $coupon = Coupon::create([
            'code' => 'RETIRE', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00', 'used_count' => 3,
        ]);

        $response = $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/coupons/{$coupon->id}");

        $response->assertStatus(200)->assertJsonPath('data.active', false);

        // Soft-deactivated, not hard-deleted - used_count history survives.
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id, 'active' => false, 'used_count' => 3,
        ]);
    }

    public function test_creating_a_coupon_writes_an_audit_log_entry(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => 'AUDITED10', 'type' => Coupon::TYPE_PERCENT, 'value' => '10',
        ])->assertStatus(201);

        $coupon = Coupon::where('code', 'AUDITED10')->firstOrFail();

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $staff->id,
            'action' => 'coupon.create',
            'subject_type' => $coupon->getMorphClass(),
            'subject_id' => $coupon->id,
        ]);
    }

    public function test_updating_a_coupon_writes_an_audit_log_entry(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $coupon = Coupon::create(['code' => 'AUDITUPD', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);

        $this->actingAs($staff, 'sanctum')->patchJson("/api/admin/coupons/{$coupon->id}", [
            'code' => 'AUDITUPD', 'type' => Coupon::TYPE_FIXED, 'value' => '9.00',
        ])->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $staff->id,
            'action' => 'coupon.update',
            'subject_type' => $coupon->getMorphClass(),
            'subject_id' => $coupon->id,
        ]);
    }

    public function test_deactivating_a_coupon_writes_an_audit_log_entry_visible_in_the_audit_log_endpoint(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $coupon = Coupon::create(['code' => 'AUDITDEACT', 'type' => Coupon::TYPE_FIXED, 'value' => '5.00']);

        $this->actingAs($staff, 'sanctum')->deleteJson("/api/admin/coupons/{$coupon->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $staff->id,
            'action' => 'coupon.deactivate',
            'subject_type' => $coupon->getMorphClass(),
            'subject_id' => $coupon->id,
        ]);

        // Prove the two features are actually wired together: the row
        // written by CouponController is readable back through the
        // Admin\AuditLogController viewer, not just present in the table.
        $this->assertSame(1, AdminAuditLog::where('action', 'coupon.deactivate')->count());

        $auditResponse = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/audit-log?action=coupon.deactivate');

        $auditResponse->assertStatus(200);
        $this->assertCount(1, $auditResponse->json('data'));
        $this->assertSame($staff->id, $auditResponse->json('data.0.actor.id'));
        $this->assertSame((string) $coupon->id, (string) $auditResponse->json('data.0.subject_id'));
    }
}
