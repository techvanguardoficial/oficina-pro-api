<?php

namespace Tests;

use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Plan $plan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    protected function setupTestData(): void
    {
        // Create a test plan
        $this->plan = Plan::factory()->create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 9900,
            'interval' => 'month',
            'max_users' => 10,
            'max_clients' => 100,
            'max_vehicles' => 25,
            'max_orders' => 200,
            'is_active' => true,
        ]);

        // Create a test company
        $this->company = Company::factory()->create();

        // Create a subscription
        $this->subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        // Create a test user
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'admin' => '1',
        ]);
    }

    protected function authenticateUser(?User $user = null): self
    {
        $user = $user ?? $this->user;
        $this->actingAs($user, 'sanctum');
        return $this;
    }
}
