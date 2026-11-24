<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\OrderService;
use App\Models\OrderServicePayment;
use App\Models\OrderStatus;
use App\Models\Part;
use App\Models\Service;
use App\Models\Vehicle;
use Tests\TestCase;

/**
 * Pagamento parcial (abatimento) de OS, lista de OS com pagamento pendente
 * e receita contada pela data de cada pagamento.
 */
class OrderServicePaymentTest extends TestCase
{
    private OrderStatus $completed;
    private OrderStatus $canceled;
    private OrderStatus $inProgress;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->completed = OrderStatus::factory()->create(['status' => 'Concluído']);
        // Nome usado na base de produção
        $this->canceled = OrderStatus::factory()->create(['status' => 'CANCELADA']);
        $this->inProgress = OrderStatus::factory()->create(['status' => 'Em andamento']);

        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $this->vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $this->company->id]);
    }

    /** OS de R$ 1000: peça 2 × 200 + serviço 600. */
    private function createOrder(?OrderStatus $status = null, ?Vehicle $vehicle = null): OrderService
    {
        $vehicle ??= $this->vehicle;
        $order = OrderService::factory()->create([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'orders_status_id' => ($status ?? $this->completed)->id,
        ]);

        Part::create([
            'company_id' => $vehicle->company_id, 'orders_id' => $order->id,
            'description' => 'Pastilha', 'quantity' => 2, 'unit_price' => 200,
        ]);
        Service::create([
            'company_id' => $vehicle->company_id, 'orders_id' => $order->id,
            'description' => 'Mão de obra', 'price' => 600,
        ]);

        return $order;
    }

    private function pay(OrderService $order, float $amount, array $extra = [])
    {
        return $this->postJson("/api/v1/order-services/{$order->id}/payments", ['amount' => $amount, ...$extra]);
    }

    public function test_partial_payment_shows_remaining_and_due_date(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();
        $due = now()->addDays(6)->toDateString();

        $this->pay($order, 500, ['method' => 'pix', 'payment_due_date' => $due])
            ->assertStatus(201)
            ->assertJsonPath('total', 1000)
            ->assertJsonPath('paid_total', 500)
            ->assertJsonPath('remaining', 500)
            ->assertJsonPath('payment_status', 'parcial')
            ->assertJsonPath('payment_due_date', $due)
            ->assertJsonPath('payment.paid_at', now()->toDateString())
            ->assertJsonPath('payment.user_id', $this->user->id);

        $this->getJson("/api/v1/order-services/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('paid_total', 500)
            ->assertJsonPath('remaining', 500)
            ->assertJsonPath('payment_status', 'parcial')
            ->assertJsonCount(1, 'payments');
    }

    public function test_paying_the_rest_settles_order_and_clears_due_date(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();

        $this->pay($order, 500, ['payment_due_date' => now()->addDays(6)->toDateString()]);
        $this->pay($order, 500)
            ->assertStatus(201)
            ->assertJsonPath('remaining', 0)
            ->assertJsonPath('payment_status', 'pago')
            ->assertJsonPath('payment_due_date', null);
    }

    public function test_rejects_amount_above_remaining_and_future_date(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();

        $this->pay($order, 600);
        $this->pay($order, 400.01)->assertStatus(422)->assertJsonValidationErrors('amount', 'details');
        $this->pay($order, 0)->assertStatus(422);
        $this->pay($order, 100, ['paid_at' => now()->addDay()->toDateString()])->assertStatus(422);
        $this->pay($order, 100, ['method' => 'cheque_voador'])->assertStatus(422);

        $this->assertSame(1, OrderServicePayment::count());
    }

    public function test_canceled_order_rejects_payment_and_is_not_open(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder($this->canceled);

        $this->pay($order, 100)->assertStatus(422);

        // CANCELADA (nome da produção) conta como encerrada
        $this->getJson('/api/v1/order-services/list-all?status=open')
            ->assertJsonPath('total', 0);
    }

    public function test_deleting_payment_restores_remaining(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();
        $other = $this->createOrder();

        $paymentId = $this->pay($order, 300)->json('payment.id');

        // Pagamento de outra OS: não encontrado
        $this->deleteJson("/api/v1/order-services/{$other->id}/payments/{$paymentId}")->assertStatus(404);

        $this->deleteJson("/api/v1/order-services/{$order->id}/payments/{$paymentId}")
            ->assertStatus(200)
            ->assertJsonPath('paid_total', 0)
            ->assertJsonPath('remaining', 1000)
            ->assertJsonPath('payment_status', 'pendente');
    }

    public function test_cannot_touch_payments_of_another_company(): void
    {
        $this->authenticateUser();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $otherCompany->id]);
        $vehicle = Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $otherCompany->id]);
        $order = $this->createOrder(null, $vehicle);

        $this->pay($order, 100)->assertStatus(403);
        $this->getJson("/api/v1/order-services/{$order->id}/payments")->assertStatus(403);
    }

    public function test_update_cannot_lower_total_below_paid(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();
        $this->pay($order, 800);

        $this->putJson("/api/v1/order-services/{$order->id}", [
            'services' => [['description' => 'Mão de obra', 'price' => 100]],
        ])->assertStatus(422);

        // Nada mudou
        $this->getJson("/api/v1/order-services/{$order->id}")
            ->assertJsonPath('total', 1000)
            ->assertJsonPath('remaining', 200);

        // Subir o valor pode
        $this->putJson("/api/v1/order-services/{$order->id}", [
            'services' => [['description' => 'Mão de obra', 'price' => 700]],
        ])->assertStatus(200)->assertJsonPath('remaining', 300);
    }

    public function test_pending_payment_list_and_summary(): void
    {
        $this->authenticateUser();

        $unpaid = $this->createOrder();                       // concluída, nada pago: entra
        $partial = $this->createOrder();                      // concluída, 400 pagos, vencida: entra
        $paid = $this->createOrder();                         // concluída e quitada: fora
        $deposit = $this->createOrder($this->inProgress);     // em andamento com sinal: entra
        $this->createOrder($this->inProgress);                // em andamento sem pagamento: fora
        $this->createOrder($this->canceled);                  // cancelada: fora

        $this->pay($partial, 400, ['payment_due_date' => now()->subDay()->toDateString()]);
        $this->pay($paid, 1000);
        $this->pay($deposit, 100, ['payment_due_date' => now()->addDays(10)->toDateString()]);

        // Outra oficina: não aparece
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $otherCompany->id]);
        $this->createOrder(null, Vehicle::factory()->create(['clients_id' => $client->id, 'company_id' => $otherCompany->id]));

        $response = $this->getJson('/api/v1/order-services/pending-payment')->assertStatus(200);

        // Vencimento mais próximo primeiro, sem vencimento por último
        $this->assertSame([$partial->id, $deposit->id, $unpaid->id], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.remaining', 600)
            ->assertJsonPath('summary.orders', 3)
            ->assertJsonPath('summary.receivable', 2500)
            ->assertJsonPath('summary.overdueOrders', 1)
            ->assertJsonPath('summary.overdue', 600);

        $this->getJson('/api/v1/order-services/pending-payment?overdue=1')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $partial->id);

        $this->getJson('/api/v1/dashboard/stats')
            ->assertJsonPath('pendingPayment.receivable', 2500)
            ->assertJsonPath('pendingPayment.orders', 3);
    }

    public function test_revenue_counts_on_payment_date(): void
    {
        $this->authenticateUser();
        $order = $this->createOrder();
        $tenDaysAgo = now()->subDays(10)->toDateString();
        $today = now()->toDateString();

        $this->pay($order, 500, ['paid_at' => $tenDaysAgo]);
        $this->pay($order, 300);

        $this->getJson("/api/v1/transactions/period?date={$today}")
            ->assertJsonPath('incomes', 300)
            ->assertJsonPath('breakdown.incomes_from_order_payments', 300);

        $this->getJson("/api/v1/transactions/period?start_date={$tenDaysAgo}&end_date={$today}")
            ->assertJsonPath('incomes', 800);

        $list = $this->getJson("/api/v1/transactions/list?start_date={$tenDaysAgo}&end_date={$today}")->assertStatus(200);
        $this->assertSame([500.0, 300.0], collect($list->json())->where('source', 'order')->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertSame([$tenDaysAgo, $today], collect($list->json())->where('source', 'order')->pluck('date')->values()->all());

        $this->getJson('/api/v1/transactions')->assertJsonPath('incomes.today', 300);

        // OS excluída sai da receita
        $this->deleteJson("/api/v1/order-services/{$order->id}")->assertStatus(204);
        $this->getJson("/api/v1/transactions/period?date={$today}")->assertJsonPath('incomes', 0);
    }

    public function test_backfill_creates_full_payment_for_completed_orders(): void
    {
        $completed = $this->createOrder();
        $completed->forceFill(['completed_at' => now()->subMonth()])->saveQuietly();
        $alreadyPaid = $this->createOrder();
        OrderServicePayment::create([
            'company_id' => $this->company->id, 'order_service_id' => $alreadyPaid->id,
            'amount' => 200, 'paid_at' => now()->toDateString(),
        ]);
        $this->createOrder($this->inProgress);

        $migration = require base_path('database/migrations/2026_11_24_000002_backfill_payments_for_completed_order_services.php');
        $migration->up();

        $payment = OrderServicePayment::where('order_service_id', $completed->id)->sole();
        $this->assertSame(1000.0, $payment->amount);
        $this->assertSame(now()->subMonth()->toDateString(), $payment->paid_at->toDateString());
        // OS que já tinha pagamento e OS em andamento não ganham pagamento automático
        $this->assertSame(2, OrderServicePayment::count());
    }
}
