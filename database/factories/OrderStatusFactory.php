<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatus>
 */
class OrderStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => fake()->unique()->lexify('Status ????'),
        ];
    }
}
