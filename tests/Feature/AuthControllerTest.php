<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Oficina Teste',
            'company_cnpj' => '12345678000199',
            'company_email' => 'contato@oficinateste.com.br',
            'company_phone' => '11999990000',
            'user_name' => 'João da Silva',
            'user_email' => 'joao@oficinateste.com.br',
            'user_phone' => '11988887777',
            'user_password' => 'SecurePassword123',
            'user_password_confirmation' => 'SecurePassword123',
        ], $overrides);
    }

    public function test_can_register_new_company_and_admin_user(): void
    {
        $response = $this->postJson('/api/v1/register', $this->registrationData());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'name', 'company_id'],
                'subscription',
            ]);

        $this->assertDatabaseHas('companies', ['cnpj' => '12345678000199']);
        $this->assertDatabaseHas('users', [
            'email' => 'joao@oficinateste.com.br',
            'admin' => '1',
        ]);
    }

    public function test_cannot_register_with_existing_company_cnpj(): void
    {
        Company::factory()->create(['cnpj' => '12345678000199']);

        $response = $this->postJson('/api/v1/register', $this->registrationData());

        $response->assertStatus(422)
            ->assertJsonValidationErrors('company_cnpj', 'details');
    }

    public function test_cannot_register_with_mismatched_passwords(): void
    {
        $response = $this->postJson('/api/v1/register', $this->registrationData([
            'user_password_confirmation' => 'DifferentPassword',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_password', 'details');
    }

    public function test_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123'),
            'company_id' => $this->company->id,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'name'],
                'subscription',
            ]);
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email', 'details');
    }

    public function test_cannot_login_with_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword',
        ]);

        // Mesma resposta da senha errada: não revela se o e-mail existe
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email', 'details');
    }

    public function test_cannot_login_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'], 'details');
    }

    public function test_can_logout_and_revoke_the_current_token(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Logout realizado com sucesso.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_cannot_logout_without_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertStatus(401);
    }

    public function test_can_get_current_user_info(): void
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->user->id,
                'email' => $this->user->email,
            ]);
    }

    public function test_cannot_get_user_info_without_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_can_request_password_reset(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Link de recuperação enviado com sucesso.']);
    }

    public function test_cannot_request_password_reset_without_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email', 'details');
    }
}
