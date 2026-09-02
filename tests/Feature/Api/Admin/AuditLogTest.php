<?php

namespace Tests\Feature\Api\Admin;

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NOTE: this route is not yet registered in routes/api.php (see
 * ## ROUTES NEEDED in the delivering agent's report) - that file is owned
 * by a concurrent agent and must not be edited here. setUp() registers the
 * exact route/middleware pair it needs to exercise, standing in for the
 * real route entry.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'staff'])->group(function () {
            Route::get('/api/admin/audit-log', [AuditLogController::class, 'index']);
        });
    }

    public function test_a_non_staff_user_is_forbidden_from_the_audit_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/audit-log')
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_before_the_staff_check(): void
    {
        $this->getJson('/api/admin/audit-log')->assertStatus(401);
    }

    public function test_staff_can_list_audit_log_entries_newest_first_with_actor_loaded(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $actor = User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);

        $older = AdminAuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'coupon.create',
            'subject_type' => 'Coupon',
            'subject_id' => 1,
            'metadata' => ['code' => 'OLD10'],
        ]);
        $older->forceFill(['created_at' => Carbon::now()->subDay()])->save();

        $newer = AdminAuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'coupon.update',
            'subject_type' => 'Coupon',
            'subject_id' => 1,
            'metadata' => ['code' => 'NEW10'],
        ]);
        $newer->forceFill(['created_at' => Carbon::now()])->save();

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/admin/audit-log');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('coupon.update', $response->json('data.0.action'));
        $this->assertSame('coupon.create', $response->json('data.1.action'));
        $this->assertSame('Alice Admin', $response->json('data.0.actor.name'));
        $this->assertSame('alice@example.com', $response->json('data.0.actor.email'));
        $this->assertSame(['code' => 'NEW10'], $response->json('data.0.metadata'));
    }

    public function test_the_index_can_be_filtered_by_actor_id(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $actorA = User::factory()->create();
        $actorB = User::factory()->create();

        AdminAuditLog::create(['actor_id' => $actorA->id, 'action' => 'coupon.create']);
        AdminAuditLog::create(['actor_id' => $actorB->id, 'action' => 'coupon.create']);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/admin/audit-log?actor_id={$actorA->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($actorA->id, $response->json('data.0.actor.id'));
    }

    public function test_the_index_can_be_filtered_by_a_partial_action_match(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $actor = User::factory()->create();

        AdminAuditLog::create(['actor_id' => $actor->id, 'action' => 'coupon.create']);
        AdminAuditLog::create(['actor_id' => $actor->id, 'action' => 'coupon.deactivate']);
        AdminAuditLog::create(['actor_id' => $actor->id, 'action' => 'review.approve']);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/audit-log?action=coupon');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_the_index_can_be_filtered_by_a_date_range(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $actor = User::factory()->create();

        $outOfRange = AdminAuditLog::create(['actor_id' => $actor->id, 'action' => 'coupon.create']);
        $outOfRange->forceFill(['created_at' => Carbon::parse('2026-01-01 10:00:00')])->save();

        $inRange = AdminAuditLog::create(['actor_id' => $actor->id, 'action' => 'coupon.update']);
        $inRange->forceFill(['created_at' => Carbon::parse('2026-03-15 10:00:00')])->save();

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/audit-log?from=2026-03-01&to=2026-03-31');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('coupon.update', $response->json('data.0.action'));
    }

    public function test_the_index_is_paginated(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);
        $actor = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            AdminAuditLog::create(['actor_id' => $actor->id, 'action' => "action.{$i}"]);
        }

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/audit-log?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(1, $response->json('meta.page'));
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }
}
