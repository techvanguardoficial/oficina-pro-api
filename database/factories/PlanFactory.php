<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'price' => 9990,
            'annual_price' => 107892,
            'interval' => 'month',
            'trial_days' => 0,
            'max_users' => 10,
            'max_vehicles' => null,
            'max_orders' => null,
            'max_parts_for_order' => null,
            'max_services_for_order' => null,
            'max_clients' => null,
            'has_stock_management' => true,
            'max_stock_quantity' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
