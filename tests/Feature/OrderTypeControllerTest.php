<?php

namespace Tests\Feature;

use App\Models\OrderType;
use Tests\TestCase;

class OrderTypeControllerTest extends TestCase
{
    public function test_can_list_all_order_types(): void
    {
        $this->authenticateUser();
        OrderType::factory(3)->create();

        $response = $this->getJson('/api/v1/order-types');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_can_create_order_type_with_valid_data(): void
    {
        $this->authenticateUser();

        $data = [
            'type' => 'Maintenance',
        ];

        $response = $this->postJson('/api/v1/order-types', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'type' => 'Maintenance',
            ]);

        $this->assertDatabaseHas('order_types', [
            'type' => 'Maintenance',
        ]);
    }

    public function test_cannot_create_order_type_without_authentication(): void
    {
        $data = [
            'type' => 'Maintenance',
        ];

        $response = $this->postJson('/api/v1/order-types', $data);

        $response->assertStatus(401);
    }

    public function test_cannot_create_duplicate_order_type(): void
    {
        $this->authenticateUser();
        OrderType::factory()->create(['type' => 'Maintenance']);

        $data = [
            'type' => 'Maintenance',
        ];

        $response = $this->postJson('/api/v1/order-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_cannot_create_order_type_without_type(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/order-types', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_cannot_exceed_max_length(): void
    {
        $this->authenticateUser();

        $data = [
            'type' => str_repeat('a', 256),
        ];

        $response = $this->postJson('/api/v1/order-types', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_can_view_order_type(): void
    {
        $this->authenticateUser();
        $orderType = OrderType::factory()->create();

        $response = $this->getJson("/api/v1/order-types/{$orderType->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $orderType->id,
                'type' => $orderType->type,
            ]);
    }

    public function test_can_update_order_type(): void
    {
        $this->authenticateUser();
        $orderType = OrderType::factory()->create();

        $data = [
            'type' => 'Updated Type',
        ];

        $response = $this->putJson("/api/v1/order-types/{$orderType->id}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'Updated Type',
            ]);

        $this->assertDatabaseHas('order_types', [
            'id' => $orderType->id,
            'type' => 'Updated Type',
        ]);
    }

    public function test_can_delete_order_type(): void
    {
        $this->authenticateUser();
        $orderType = OrderType::factory()->create();

        $response = $this->deleteJson("/api/v1/order-types/{$orderType->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('order_types', ['id' => $orderType->id]);
    }
}
