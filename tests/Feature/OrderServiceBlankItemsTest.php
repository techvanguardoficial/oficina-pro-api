<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OrderService;
use App\Models\Part;
use App\Models\Vehicle;
use Tests\TestCase;

/**
 * Editar OS com uma linha de peça vazia (sem descrição e sem valor) respondia 422
 * "parts.0.description is required". A linha vazia agora é ignorada.
 */
class OrderServiceBlankItemsTest extends TestCase
{
    private OrderService $order;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $this->company->id]);
        $this->order = OrderService::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
        ]);
        // Peça fantasma de OS antiga
        Part::create([
            'company_id' => $this->company->id, 'orders_id' => $this->order->id,
            'description' => '', 'quantity' => 1, 'unit_price' => 0, 'price' => 0,
        ]);

        $this->authenticateUser();
    }

    public function test_update_ignores_blank_part_and_service_rows(): void
    {
        $this->putJson("/api/v1/order-services/{$this->order->id}", [
            'info'     => '',
            'mileage'  => 59001,
            'parts'    => [['description' => '', 'quantity' => 1, 'unit_price' => 0, 'information' => '']],
            'services' => [
                ['description' => 'Troca do óleo e filtro de óleo', 'price' => 375],
                ['description' => 'Troca do filtro de ar', 'price' => 68],
                ['description' => '', 'price' => 0],
            ],
        ])->assertStatus(200);

        $this->assertSame(0, $this->order->parts()->count());
        $this->assertSame(2, $this->order->services()->count());
    }

    public function test_row_with_value_but_no_description_is_still_rejected(): void
    {
        $this->putJson("/api/v1/order-services/{$this->order->id}", [
            'parts' => [['description' => '', 'quantity' => 2, 'unit_price' => 50]],
        ])->assertStatus(422);

        $this->assertSame(1, $this->order->parts()->count()); // nada mudou
    }
}
