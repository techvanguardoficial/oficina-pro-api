<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAppUser;
use App\Models\Company;
use App\Models\OrderService;
use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Models\Part;
use App\Models\Service;
use App\Models\Vehicle;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Portal do Cliente: última visita do veículo, histórico sem OS apagadas
 * nem itens vazios, e oficinas com logo.
 */
class ClientPortalHistoryTest extends TestCase
{
    private ClientAppUser $appUser;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $this->vehicle = Vehicle::factory()->create([
            'clients_id' => $client->id,
            'company_id' => $this->company->id,
            'placa' => 'ABC1D23',
        ]);

        $this->appUser = ClientAppUser::create([
            'name' => 'Motorista Teste',
            'email' => 'motorista@example.com',
            'phone' => '11900000000',
            'password' => 'secret-password',
            'onboarding_completed' => true,
        ]);
        $this->appUser->clients()->attach($client->id);
    }

    private function order(string $createdAt, array $attrs = []): OrderService
    {
        $order = OrderService::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'orders_status_id' => OrderStatus::factory()->create(['status' => 'Concluído'])->id,
            'orders_types_id' => OrderType::factory()->create(['type' => 'ORDEM DE SERVIÇO'])->id,
        ], $attrs));
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_vehicle_list_shows_last_visit_and_numeric_km(): void
    {
        $this->order('2026-03-10 09:00:00');
        $latest = $this->order('2026-09-20 15:00:00');
        // OS apagada mais recente não conta como visita
        $this->order('2026-11-01 10:00:00')->delete();

        Sanctum::actingAs($this->appUser, ['client'], 'client');

        $response = $this->getJson('/api/v1/client/vehicles')->assertStatus(200);

        $vehicle = collect($response->json())->firstWhere('placa', 'ABC1D23');
        $this->assertSame('2026-09-20', $vehicle['last_visit']['date']);
        $this->assertSame($latest->company_id, $vehicle['last_visit']['company_id']);
        $this->assertSame($this->company->fantasy_name ?: $this->company->name, $vehicle['last_visit']['workshop']);
        $this->assertIsInt($vehicle['km']);
    }

    public function test_vehicle_without_orders_has_no_last_visit(): void
    {
        Sanctum::actingAs($this->appUser, ['client'], 'client');

        $this->getJson('/api/v1/client/vehicles/ABC1D23')
            ->assertStatus(200)
            ->assertJsonPath('last_visit', null);
    }

    public function test_history_hides_deleted_orders_and_empty_items(): void
    {
        $kept = $this->order('2026-05-05 10:00:00');
        Service::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'orders_id' => $kept->id,
            'description' => 'Troca de óleo', 'price' => 150,
        ]);
        Part::create([
            'company_id' => $this->company->id, 'orders_id' => $kept->id,
            'description' => 'Filtro de óleo', 'quantity' => 1, 'unit_price' => 40, 'price' => 40,
        ]);
        // Peça fantasma: vazia e sem quantidade
        Part::create([
            'company_id' => $this->company->id, 'orders_id' => $kept->id,
            'description' => '', 'quantity' => 0, 'unit_price' => 0, 'price' => 0,
        ]);

        $deleted = $this->order('2026-06-06 10:00:00');
        $deleted->delete();

        Sanctum::actingAs($this->appUser, ['client'], 'client');

        $response = $this->getJson("/api/v1/client/vehicles/ABC1D23/history?company_id={$this->company->id}")
            ->assertStatus(200)
            ->assertJsonPath('total', 1);

        $order = $response->json('history.0');
        $this->assertSame($kept->id, $order['id']);
        $this->assertCount(1, $order['parts']);
        $this->assertSame('Filtro de óleo', $order['parts'][0]['description']);
        $this->assertSame('Troca de óleo', $order['services'][0]['description']);
    }

    public function test_workshops_include_logo_url_and_count_only_existing_orders(): void
    {
        $this->order('2026-05-05 10:00:00');
        $this->order('2026-06-06 10:00:00')->delete();

        Sanctum::actingAs($this->appUser, ['client'], 'client');

        $workshop = $this->getJson('/api/v1/client/vehicles/ABC1D23/workshops')
            ->assertStatus(200)
            ->json('workshops.0');

        $this->assertArrayHasKey('logo_url', $workshop);
        $this->assertSame(1, $workshop['total_os']);
    }

    public function test_other_clients_vehicle_is_not_visible(): void
    {
        $stranger = Client::factory()->create(['company_id' => Company::factory()->create()->id]);
        Vehicle::factory()->create(['clients_id' => $stranger->id, 'placa' => 'ZZZ9Z99']);

        Sanctum::actingAs($this->appUser, ['client'], 'client');

        $this->getJson('/api/v1/client/vehicles/ZZZ9Z99')->assertStatus(404);
    }
}
