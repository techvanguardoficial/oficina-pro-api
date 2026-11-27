<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OrderService;
use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Models\Vehicle;
use Tests\TestCase;

/**
 * Data de conclusão informada: OS esquecida aberta e fechada dias depois
 * pode registrar o dia em que de fato foi concluída.
 */
class OrderServiceCompletedAtTest extends TestCase
{
    private OrderStatus $completed;
    private OrderStatus $inProgress;
    private OrderType $type;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->completed = OrderStatus::factory()->create(['status' => 'Concluído']);
        $this->inProgress = OrderStatus::factory()->create(['status' => 'Em andamento']);
        $this->type = OrderType::factory()->create();
        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $this->vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $this->company->id]);
        $this->authenticateUser();
    }

    private function createOrder(OrderStatus $status, array $extra = [])
    {
        return $this->postJson('/api/v1/order-services', [
            'vehicle_placa' => $this->vehicle->placa,
            'orders_types_id' => $this->type->id,
            'orders_status_id' => $status->id,
            'services' => [['description' => 'Mão de obra', 'price' => 100]],
            ...$extra,
        ]);
    }

    private function completedDate(int $id): ?string
    {
        return OrderService::find($id)->completed_at?->toDateString();
    }

    public function test_closing_an_order_with_a_past_date(): void
    {
        $id = $this->createOrder($this->inProgress)->assertStatus(201)->json('order.id');
        $lastWeek = now()->subDays(6)->toDateString();

        $this->putJson("/api/v1/order-services/{$id}", [
            'orders_status_id' => $this->completed->id,
            'completed_at' => $lastWeek,
        ])->assertStatus(200);

        $this->assertSame($lastWeek, $this->completedDate($id));

        // Já concluída: dá para corrigir só a data
        $this->putJson("/api/v1/order-services/{$id}", ['completed_at' => now()->subDays(2)->toDateString()])
            ->assertStatus(200);
        $this->assertSame(now()->subDays(2)->toDateString(), $this->completedDate($id));

        // E o resumo de pagamento traz a data para sugerir no primeiro pagamento
        $this->getJson("/api/v1/order-services/{$id}/payments")
            ->assertStatus(200)
            ->assertJsonPath('completed_at', OrderService::find($id)->completed_at->toIso8601String());
    }

    public function test_without_date_completion_is_now(): void
    {
        $id = $this->createOrder($this->completed)->assertStatus(201)->json('order.id');
        $this->assertSame(now()->toDateString(), $this->completedDate($id));
    }

    public function test_create_already_closed_on_a_past_date(): void
    {
        $date = now()->subDays(3)->toDateString();
        $id = $this->createOrder($this->completed, ['completed_at' => $date])->assertStatus(201)->json('order.id');
        $this->assertSame($date, $this->completedDate($id));
    }

    public function test_future_date_is_rejected_and_ignored_when_not_completed(): void
    {
        $this->createOrder($this->completed, ['completed_at' => now()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('completed_at', 'details');

        $id = $this->createOrder($this->inProgress, ['completed_at' => now()->subDay()->toDateString()])
            ->assertStatus(201)->json('order.id');
        $this->assertNull($this->completedDate($id));

        // Reabrir limpa a data
        $this->putJson("/api/v1/order-services/{$id}", ['orders_status_id' => $this->completed->id])->assertStatus(200);
        $this->assertNotNull($this->completedDate($id));
        $this->putJson("/api/v1/order-services/{$id}", ['orders_status_id' => $this->inProgress->id])->assertStatus(200);
        $this->assertNull($this->completedDate($id));
    }
}
