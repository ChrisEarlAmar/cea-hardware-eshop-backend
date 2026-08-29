<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status_in_envelope(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['status', 'timestamp'],
            ])
            ->assertJsonPath('data.status', 'ok');
    }
}
