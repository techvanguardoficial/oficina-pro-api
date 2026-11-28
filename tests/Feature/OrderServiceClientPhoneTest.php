<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OrderService;
use App\Models\Phone;
use App\Models\Vehicle;
use Tests\TestCase;

/** A OS (lista, detalhe, a receber) traz o telefone do cliente para exibir e imprimir. */
class OrderServiceClientPhoneTest extends TestCase
{
    private OrderService $order;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create(['company_id' => $this->company->id]);
        Phone::create(['clients_id' => $client->id, 'phone_one' => '11999998888', 'phone_two' => '1133334444']);
        $vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $this->company->id]);
        $this->order = OrderService::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->authenticateUser();
    }

    public function test_list_and_detail_include_client_phone(): void
    {
        $this->getJson('/api/v1/order-services')
            ->assertOk()
            ->assertJsonPath('data.0.vehicle.client.phone.phone_one', '11999998888')
            ->assertJsonPath('data.0.vehicle.client.phone.phone_two', '1133334444');

        $this->getJson("/api/v1/order-services/{$this->order->id}")
            ->assertOk()
            ->assertJsonPath('vehicle.client.phone.phone_one', '11999998888');
    }
}
