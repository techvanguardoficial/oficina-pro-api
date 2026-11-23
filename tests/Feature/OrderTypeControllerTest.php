<?php

namespace Tests\Feature;

use App\Models\OrderType;
use Tests\TestCase;

/**
 * Os tipos de OS são um cadastro global, compartilhado por todas as oficinas.
 * A API só expõe listagem e consulta; criar, editar ou excluir não é permitido a nenhuma oficina.
 */
class OrderTypeControllerTest extends TestCase
{
    public function test_can_list_types(): void
    {
        $this->authenticateUser();
        OrderType::factory(3)->create();

        $response = $this->getJson('/api/v1/order-types');

        $response->assertStatus(200)
            ->assertJsonIsArray()
            ->assertJsonCount(3);
    }

    public function test_can_view_a_type(): void
    {
        $this->authenticateUser();
        $item = OrderType::factory()->create();

        $response = $this->getJson("/api/v1/order-types/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $item->id,
                'type' => $item->type,
            ]);
    }

    public function test_cannot_list_types_without_authentication(): void
    {
        $this->getJson('/api/v1/order-types')->assertStatus(401);
    }

    public function test_oficina_cannot_create_update_or_delete_types(): void
    {
        $this->authenticateUser();
        $item = OrderType::factory()->create();

        $this->postJson('/api/v1/order-types', ['type' => 'Novo'])->assertStatus(405);
        $this->putJson("/api/v1/order-types/{$item->id}", ['type' => 'Alterado'])->assertStatus(405);
        $this->deleteJson("/api/v1/order-types/{$item->id}")->assertStatus(405);

        $this->assertDatabaseHas($item->getTable(), ['id' => $item->id, 'type' => $item->type, 'deleted_at' => null]);
    }
}
