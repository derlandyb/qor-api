<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteSkeletonTest extends TestCase
{
    public function test_GIVEN_an_undefined_v1_route_WHEN_dispatched_THEN_it_returns_a_pt_br_404_envelope(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertStatus(404)
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_GIVEN_an_undefined_admin_v1_route_WHEN_dispatched_THEN_it_returns_a_pt_br_404_envelope(): void
    {
        $response = $this->getJson('/api/admin/v1/does-not-exist');

        $response->assertStatus(404)
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }
}
