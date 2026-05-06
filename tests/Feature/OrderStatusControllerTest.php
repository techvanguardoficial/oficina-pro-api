<?php

namespace Tests\Feature;

use App\Models\OrderStatus;
use Tests\TestCase;

class OrderStatusControllerTest extends TestCase
{
    public function test_can_list_all_order_statuses(): void
    {
        $this->authenticateUser();
        OrderStatus::factory(3)->create();

        $response = $this->getJson('/api/v1/order-statuses');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_can_create_order_status_with_valid_data(): void
    {
        $this->authenticateUser();

        $data = [
            'status' => 'Completed',
        ];

        $response = $this->postJson('/api/v1/order-statuses', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'status' => 'Completed',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'status' => 'Completed',
        ]);
    }

    public function test_cannot_create_order_status_without_authentication(): void
    {
        $data = [
            'status' => 'Completed',
        ];

        $response = $this->postJson('/api/v1/order-statuses', $data);

        $response->assertStatus(401);
    }

    public function test_cannot_create_duplicate_order_status(): void
    {
        $this->authenticateUser();
        OrderStatus::factory()->create(['status' => 'Completed']);

        $data = [
            'status' => 'Completed',
        ];

        $response = $this->postJson('/api/v1/order-statuses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_cannot_create_order_status_without_status(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/order-statuses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_cannot_exceed_max_length(): void
    {
        $this->authenticateUser();

        $data = [
            'status' => str_repeat('a', 256),
        ];

        $response = $this->postJson('/api/v1/order-statuses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_can_view_order_status(): void
    {
        $this->authenticateUser();
        $orderStatus = OrderStatus::factory()->create();

        $response = $this->getJson("/api/v1/order-statuses/{$orderStatus->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $orderStatus->id,
                'status' => $orderStatus->status,
            ]);
    }

    public function test_can_update_order_status(): void
    {
        $this->authenticateUser();
        $orderStatus = OrderStatus::factory()->create();

        $data = [
            'status' => 'Updated Status',
        ];

        $response = $this->putJson("/api/v1/order-statuses/{$orderStatus->id}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'Updated Status',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'id' => $orderStatus->id,
            'status' => 'Updated Status',
        ]);
    }

    public function test_can_delete_order_status(): void
    {
        $this->authenticateUser();
        $orderStatus = OrderStatus::factory()->create();

        $response = $this->deleteJson("/api/v1/order-statuses/{$orderStatus->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('order_statuses', ['id' => $orderStatus->id]);
    }
}
