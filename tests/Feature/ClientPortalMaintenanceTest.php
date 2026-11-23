<?php

namespace Tests\Feature;

use App\Models\CarMileage;
use App\Models\Client;
use App\Models\ClientAppUser;
use App\Models\OrderService;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lembretes do Portal contam a partir da última vez que o serviço foi feito,
 * e não da última visita à oficina. Regressão: todo lembrete novo nascia "Vencido".
 */
class ClientPortalMaintenanceTest extends TestCase
{
    private ClientAppUser $appUser;
    private Vehicle $vehicle;
    private string $url = '/api/v1/client/vehicles/ABC1D23/maintenance';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 10:00:00');

        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $this->vehicle = Vehicle::factory()->create([
            'clients_id' => $client->id,
            'company_id' => $this->company->id,
            'placa'      => 'ABC1D23',
            'km'         => 50000,
        ]);

        // Última visita à oficina, há 8 meses, com 72.000 km
        $order = OrderService::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $mileage = CarMileage::create([
            'vehicle_id'        => $this->vehicle->id,
            'mileage'           => 72000,
            'company_id'        => $this->company->id,
            'order_services_id' => $order->id,
        ]);
        $mileage->forceFill(['created_at' => '2026-03-20 09:00:00'])->save();

        $this->appUser = ClientAppUser::create([
            'name' => 'Motorista Teste', 'email' => 'motorista@example.com',
            'phone' => '11900000000', 'password' => 'secret-password',
            'onboarding_completed' => true,
        ]);
        $this->appUser->clients()->attach($client->id);

        Sanctum::actingAs($this->appUser, ['client'], 'client');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_reminder_counts_from_today_and_is_not_overdue(): void
    {
        $this->postJson($this->url, [
            'description' => 'Troca de óleo', 'interval_km' => 10000, 'interval_months' => 6,
        ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('last_done_at', '2026-11-23')
            ->assertJsonPath('last_done_km', 72000)
            ->assertJsonPath('next_due_date', '2027-05-23')
            ->assertJsonPath('next_due_km', 82000)
            ->assertJsonPath('km_left', 10000);
    }

    public function test_last_done_in_the_past_drives_status_and_next_due(): void
    {
        $this->postJson($this->url, [
            'description' => 'Troca de óleo', 'interval_months' => 6,
            'last_done_at' => '2026-05-01', 'last_done_km' => 60000,
        ])->assertStatus(201)->assertJsonPath('status', 'overdue')->assertJsonPath('next_due_date', '2026-11-01');

        $this->postJson($this->url, [
            'description' => 'Alinhamento', 'interval_km' => 15000,
            'last_done_km' => 60000,
        ])->assertStatus(201)->assertJsonPath('status', 'due_soon')->assertJsonPath('km_left', 3000);

        // Lista: vencidos primeiro, com o km da última visita
        $this->getJson($this->url)
            ->assertStatus(200)
            ->assertJsonPath('current_km', 72000)
            ->assertJsonPath('current_km_date', '2026-03-20')
            ->assertJsonPath('schedules.0.description', 'Troca de óleo')
            ->assertJsonPath('schedules.1.description', 'Alinhamento');
    }

    public function test_reminder_requires_an_interval(): void
    {
        $this->postJson($this->url, ['description' => 'Lavar o carro'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interval_km', 'interval_months'], 'details');

        $this->postJson($this->url, [
            'description' => 'Óleo', 'interval_km' => 10000, 'last_done_at' => '2026-12-31',
        ])->assertStatus(422)->assertJsonValidationErrors(['last_done_at'], 'details');

        $id = $this->postJson($this->url, ['description' => 'Óleo', 'interval_km' => 10000])->json('id');

        $this->putJson("{$this->url}/{$id}", ['interval_km' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interval_km'], 'details');
    }

    public function test_mark_as_done_restarts_the_count(): void
    {
        $id = $this->postJson($this->url, [
            'description' => 'Troca de óleo', 'interval_months' => 6, 'last_done_at' => '2026-01-10',
        ])->assertJsonPath('status', 'overdue')->json('id');

        $this->postJson("{$this->url}/{$id}/done")
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('last_done_at', '2026-11-23')
            ->assertJsonPath('last_done_km', 72000);
    }

    public function test_cannot_touch_another_users_reminder(): void
    {
        $other = ClientAppUser::create([
            'name' => 'Outro', 'email' => 'outro@example.com', 'phone' => '11911111111',
            'password' => 'secret-password', 'onboarding_completed' => true,
        ]);
        $schedule = VehicleMaintenanceSchedule::create([
            'client_app_user_id' => $other->id, 'placa' => 'ABC1D23',
            'description' => 'Óleo', 'interval_km' => 10000,
        ]);

        $this->postJson("{$this->url}/{$schedule->id}/done")->assertStatus(404);
        $this->deleteJson("{$this->url}/{$schedule->id}")->assertStatus(404);
    }
}
