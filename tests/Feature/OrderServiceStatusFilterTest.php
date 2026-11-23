<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\OrderService;
use App\Models\OrderStatus;
use App\Models\Vehicle;
use Tests\TestCase;

/**
 * Filtro por status da listagem de OS (GET /order-services/list-all?status=)
 * e contagem por status (GET /order-services/status-counts).
 */
class OrderServiceStatusFilterTest extends TestCase
{
    private OrderStatus $completed;
    private OrderStatus $canceled;
    private OrderStatus $inProgress;
    private OrderStatus $open;
    private OrderStatus $waitingPart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->completed = OrderStatus::factory()->create(['status' => 'Concluído']);
        $this->canceled = OrderStatus::factory()->create(['status' => 'Cancelado']);
        $this->inProgress = OrderStatus::factory()->create(['status' => 'Em andamento']);
        $this->open = OrderStatus::factory()->create(['status' => 'Aberto']);
        $this->waitingPart = OrderStatus::factory()->create(['status' => 'Aguardando Peça']);

        // Oficina do usuário: 3 concluídas, 1 cancelada, 2 em andamento, 1 aberta, 0 aguardando peça
        $this->createOrders($this->company, $this->completed, 3);
        $this->createOrders($this->company, $this->canceled, 1);
        $this->createOrders($this->company, $this->inProgress, 2);
        $this->createOrders($this->company, $this->open, 1);

        // Outra oficina: não pode aparecer nem contar
        $this->createOrders(Company::factory()->create(), $this->inProgress, 4);
    }

    private function createOrders(Company $company, OrderStatus $status, int $count): void
    {
        $client = Client::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $company->id]);

        OrderService::factory($count)->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'orders_status_id' => $status->id,
        ]);
    }

    private function listStatusIds(array $query = []): array
    {
        $response = $this->getJson('/api/v1/order-services/list-all?' . http_build_query($query));
        $response->assertStatus(200);

        return collect($response->json('data'))->pluck('orders_status_id')->unique()->sort()->values()->all();
    }

    public function test_lists_all_orders_of_own_company_without_filter(): void
    {
        $this->authenticateUser();

        $this->getJson('/api/v1/order-services/list-all')
            ->assertStatus(200)
            ->assertJsonPath('total', 7);
    }

    public function test_open_filter_excludes_completed_and_canceled(): void
    {
        $this->authenticateUser();

        $this->getJson('/api/v1/order-services/list-all?status=open')
            ->assertStatus(200)
            ->assertJsonPath('total', 3);

        $this->assertEqualsCanonicalizing(
            [$this->inProgress->id, $this->open->id],
            $this->listStatusIds(['status' => 'open']),
        );
    }

    public function test_filters_by_one_or_more_status_ids(): void
    {
        $this->authenticateUser();

        $this->getJson("/api/v1/order-services/list-all?status={$this->completed->id}")
            ->assertJsonPath('total', 3);

        $this->getJson("/api/v1/order-services/list-all?status={$this->completed->id},{$this->canceled->id}")
            ->assertJsonPath('total', 4);

        $this->getJson("/api/v1/order-services/list-all?status={$this->waitingPart->id}")
            ->assertJsonPath('total', 0);
    }

    public function test_rejects_invalid_status_filter(): void
    {
        $this->authenticateUser();

        foreach (['abc', '1;2', 'open,1', '1,'] as $invalid) {
            $this->getJson('/api/v1/order-services/list-all?status=' . urlencode($invalid))
                ->assertStatus(422)
                ->assertJsonValidationErrors('status', 'details');
        }
    }

    public function test_status_counts_cover_only_own_company(): void
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/order-services/status-counts');

        $response->assertStatus(200)
            ->assertJsonPath('all', 7)
            ->assertJsonPath('open', 3);

        $totals = collect($response->json('statuses'))->pluck('total', 'status')->all();

        $this->assertSame(3, $totals['Concluído']);
        $this->assertSame(1, $totals['Cancelado']);
        $this->assertSame(2, $totals['Em andamento']);
        $this->assertSame(1, $totals['Aberto']);
        $this->assertSame(0, $totals['Aguardando Peça']);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/order-services/list-all?status=open')->assertStatus(401);
        $this->getJson('/api/v1/order-services/status-counts')->assertStatus(401);
    }
}
