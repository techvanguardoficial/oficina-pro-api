<?php

namespace Tests\Feature;

use App\Models\Client;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    public function test_can_list_only_clients_of_own_company(): void
    {
        $this->authenticateUser();
        Client::factory(3)->create(['company_id' => $this->company->id]);
        Client::factory()->create(); // outra oficina

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200)
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data');
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
            'phone_one' => '11987654321',
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
            ->assertJsonValidationErrors('email', 'details');
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
            ->assertJsonValidationErrors('cpf_cnpj', 'details');
    }

    public function test_cannot_create_client_without_required_fields(): void
    {
        $this->authenticateUser();

        // Só nome e telefone são obrigatórios; o resto o cliente completa no Portal
        $response = $this->postJson('/api/v1/clients', ['lastname' => 'Doe']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone_one'], 'details')
            ->assertJsonMissingValidationErrors(['lastname', 'email', 'cpf_cnpj'], 'details');
    }

    public function test_can_create_client_with_only_name_and_phone(): void
    {
        $this->authenticateUser();

        // O modal manda os campos não preenchidos como texto vazio
        $this->postJson('/api/v1/clients', [
            'name' => 'Rosane', 'lastname' => '', 'email' => '', 'cpf_cnpj' => '',
            'phone_one' => '11987654321', 'phone_two' => '',
            'address' => '', 'number' => '', 'district' => '', 'city' => '', 'uf' => '', 'zipcode' => '',
        ])->assertStatus(201);

        $this->assertDatabaseHas('clients', [
            'name' => 'Rosane', 'email' => null, 'cpf_cnpj' => null, 'company_id' => $this->company->id,
        ]);
        $this->assertDatabaseHas('phones', ['phone_one' => '11987654321']);

        // Um segundo cliente sem e-mail nem CPF não esbarra na regra de "já cadastrado"
        $this->postJson('/api/v1/clients', [
            'name' => 'Carlos', 'email' => '', 'cpf_cnpj' => '', 'phone_one' => '11912345678',
        ])->assertStatus(201);
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
        // Limite 0 significa ilimitado; com limite 1 e um cliente já cadastrado, o próximo é bloqueado
        $this->plan->update(['max_clients' => 1]);
        Client::factory()->create(['company_id' => $this->company->id]);
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
