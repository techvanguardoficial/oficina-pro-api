<?php

namespace Tests\Feature;

use App\Models\OrderStatus;
use Tests\TestCase;

/**
 * Os status de OS são um cadastro global, compartilhado por todas as oficinas.
 * A API só expõe listagem e consulta; criar, editar ou excluir não é permitido a nenhuma oficina.
 */
class OrderStatusControllerTest extends TestCase
{
    public function test_can_list_statuss(): void
    {
        $this->authenticateUser();
        OrderStatus::factory(3)->create();

        $response = $this->getJson('/api/v1/order-statuses');

        $response->assertStatus(200)
            ->assertJsonIsArray()
            ->assertJsonCount(3);
    }

    public function test_can_view_a_status(): void
    {
        $this->authenticateUser();
        $item = OrderStatus::factory()->create();

        $response = $this->getJson("/api/v1/order-statuses/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $item->id,
                'status' => $item->status,
            ]);
    }

    public function test_cannot_list_statuss_without_authentication(): void
    {
        $this->getJson('/api/v1/order-statuses')->assertStatus(401);
    }

    public function test_oficina_cannot_create_update_or_delete_statuss(): void
    {
        $this->authenticateUser();
        $item = OrderStatus::factory()->create();

        $this->postJson('/api/v1/order-statuses', ['status' => 'Novo'])->assertStatus(405);
        $this->putJson("/api/v1/order-statuses/{$item->id}", ['status' => 'Alterado'])->assertStatus(405);
        $this->deleteJson("/api/v1/order-statuses/{$item->id}")->assertStatus(405);

        $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'status' => $item->status, 'deleted_at' => null]);
    }
}
