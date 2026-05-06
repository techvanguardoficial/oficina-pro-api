<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_can_register_new_user(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'cnpj' => '12345678901234',
            'company_name' => 'Test Company',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('companies', [
            'cnpj' => '12345678901234',
        ]);
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'cnpj' => '12345678901234',
            'company_name' => 'Test Company',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_register_with_mismatched_passwords(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'DifferentPassword',
            'cnpj' => '12345678901234',
            'company_name' => 'Test Company',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123'),
        ]);

        $data = [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ];

        $response = $this->postJson('/api/v1/login', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'token',
            ]);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123'),
        ]);

        $data = [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ];

        $response = $this->postJson('/api/v1/login', $data);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'error' => 'Invalid credentials',
            ]);
    }

    public function test_cannot_login_with_nonexistent_email(): void
    {
        $data = [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword',
        ];

        $response = $this->postJson('/api/v1/login', $data);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'error' => 'Invalid credentials',
            ]);
    }

    public function test_cannot_login_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_can_logout(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Logged out successfully',
            ]);
    }

    public function test_cannot_logout_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
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
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_can_request_password_reset(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Password reset link sent',
            ]);
    }

    public function test_cannot_request_password_reset_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
