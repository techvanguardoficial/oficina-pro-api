<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\CarModel;
use App\Models\Client;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    protected CarModel $carModel;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carModel = CarModel::factory()->create();
        $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_all_vehicles(): void
    {
        $this->authenticateUser();
        Vehicle::factory(3)->create([
            'company_id' => $this->company->id,
            'car_models_id' => $this->carModel->id,
        ]);

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'placa',
                        'company_id',
                        'car_models_id',
                    ]
                ]
            ]);
    }

    public function test_can_create_vehicle_with_valid_data(): void
    {
        $this->authenticateUser();

        $data = [
            'placa' => 'ABC1234',
            'info' => 'Test vehicle',
            'chassis' => 'CHASSIS123',
            'year' => 2023,
            'color' => 'Red',
            'km' => 100,
            'car_models_id' => $this->carModel->id,
            'clients_id' => $this->client->id,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'placa' => 'ABC1234',
                'company_id' => $this->company->id,
            ]);

        $this->assertDatabaseHas('vehicles', [
            'placa' => 'ABC1234',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_create_vehicle_without_authentication(): void
    {
        $data = [
            'placa' => 'ABC1234',
            'car_models_id' => $this->carModel->id,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(401);
    }

    public function test_cannot_create_vehicle_with_duplicate_placa(): void
    {
        $this->authenticateUser();
        Vehicle::factory()->create([
            'placa' => 'ABC1234',
            'company_id' => $this->company->id,
        ]);

        $data = [
            'placa' => 'ABC1234',
            'car_models_id' => $this->carModel->id,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('placa');
    }

    public function test_cannot_create_vehicle_with_invalid_placa_length(): void
    {
        $this->authenticateUser();

        $data = [
            'placa' => 'TOOLONGPLACA',
            'car_models_id' => $this->carModel->id,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('placa');
    }

    public function test_cannot_create_vehicle_without_required_fields(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/vehicles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['placa', 'car_models_id']);
    }

    public function test_cannot_create_vehicle_with_invalid_year(): void
    {
        $this->authenticateUser();

        $data = [
            'placa' => 'ABC1234',
            'car_models_id' => $this->carModel->id,
            'year' => 1800,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('year');
    }

    public function test_cannot_create_vehicle_with_negative_km(): void
    {
        $this->authenticateUser();

        $data = [
            'placa' => 'ABC1234',
            'car_models_id' => $this->carModel->id,
            'km' => -100,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('km');
    }

    public function test_can_view_vehicle(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'car_models_id' => $this->carModel->id,
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->placa}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'placa' => $vehicle->placa,
                'company_id' => $this->company->id,
            ]);
    }

    public function test_can_update_vehicle(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'car_models_id' => $this->carModel->id,
        ]);

        $data = [
            'color' => 'Blue',
            'km' => 5000,
        ];

        $response = $this->putJson("/api/v1/vehicles/{$vehicle->placa}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'color' => 'Blue',
                'km' => 5000,
            ]);

        $this->assertDatabaseHas('vehicles', [
            'placa' => $vehicle->placa,
            'color' => 'Blue',
        ]);
    }

    public function test_can_delete_vehicle(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create([
            'company_id' => $this->company->id,
            'car_models_id' => $this->carModel->id,
        ]);

        $response = $this->deleteJson("/api/v1/vehicles/{$vehicle->placa}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('vehicles', ['placa' => $vehicle->placa]);
    }

    public function test_cannot_create_vehicle_exceeding_plan_limit(): void
    {
        // Update plan to have a vehicle limit of 0
        $this->plan->update(['max_vehicles' => 0]);
        $this->authenticateUser();

        $data = [
            'placa' => 'ABC1234',
            'car_models_id' => $this->carModel->id,
        ];

        $response = $this->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Plan limit exceeded',
            ]);
    }
}
