<?php

namespace Tests\Feature;

use App\Models\Client;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    public function test_can_list_all_clients(): void
    {
        $this->authenticateUser();
        Client::factory(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_can_create_client_with_valid_data(): void
    {
        $this->authenticateUser();

        $clientData = [
            'name' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'cpf_cnpj' => '12345678901234',
            'status' => true,
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'John',
                'lastname' => 'Doe',
                'email' => 'john@example.com',
                'company_id' => $this->company->id,
            ]);

        $this->assertDatabaseHas('clients', [
            'email' => 'john@example.com',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_create_client_without_authentication(): void
    {
        $clientData = [
            'name' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'cpf_cnpj' => '12345678901234',
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(401);
    }

    public function test_cannot_create_client_with_duplicate_email(): void
    {
        $this->authenticateUser();
        Client::factory()->create([
            'email' => 'john@example.com',
            'company_id' => $this->company->id,
        ]);

        $clientData = [
            'name' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'cpf_cnpj' => '12345678901234',
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_create_client_with_duplicate_cpf_cnpj(): void
    {
        $this->authenticateUser();
        Client::factory()->create([
            'cpf_cnpj' => '12345678901234',
            'company_id' => $this->company->id,
        ]);

        $clientData = [
            'name' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'cpf_cnpj' => '12345678901234',
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cpf_cnpj');
    }

    public function test_cannot_create_client_without_required_fields(): void
    {
        $this->authenticateUser();

        $clientData = [
            'name' => 'John',
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lastname', 'email', 'cpf_cnpj']);
    }

    public function test_can_view_client(): void
    {
        $this->authenticateUser();
        $client = Client::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $client->id,
                'email' => $client->email,
            ]);
    }

    public function test_can_update_client(): void
    {
        $this->authenticateUser();
        $client = Client::factory()->create(['company_id' => $this->company->id]);

        $updateData = [
            'name' => 'Updated Name',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
            ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_client(): void
    {
        $this->authenticateUser();
        $client = Client::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_cannot_create_client_exceeding_plan_limit(): void
    {
        // Update plan to have a client limit of 0
        $this->plan->update(['max_clients' => 0]);
        $this->authenticateUser();

        $clientData = [
            'name' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'cpf_cnpj' => '12345678901234',
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Plan limit exceeded',
            ]);
    }
}
