<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_can_list_all_users(): void
    {
        $this->authenticateUser();
        User::factory(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonIsArray()
            ->assertJsonCount(4); // 3 created + 1 from setupTestData
    }

    public function test_can_create_user_with_valid_data(): void
    {
        $this->authenticateUser();

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
            'admin' => false,
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'company_id' => $this->company->id,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_create_user_without_authentication(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(401);
    }

    public function test_cannot_create_user_with_duplicate_email(): void
    {
        $this->authenticateUser();
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_create_user_without_required_fields(): void
    {
        $this->authenticateUser();

        $userData = [
            'name' => 'John Doe',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cannot_create_user_with_short_password(): void
    {
        $this->authenticateUser();

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'short',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_can_view_user(): void
    {
        $this->authenticateUser();
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    public function test_can_update_user(): void
    {
        $this->authenticateUser();
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->putJson("/api/v1/users/{$user->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_user(): void
    {
        $this->authenticateUser();
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_cannot_create_user_exceeding_plan_limit(): void
    {
        // Update plan to have a user limit of 1
        $this->plan->update(['max_users' => 1]);
        $this->authenticateUser();

        // Try to create a second user
        $userData = [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'SecurePassword123',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Plan limit exceeded',
            ]);
    }
}
