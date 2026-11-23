<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderType>
 */
class OrderTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->unique()->lexify('Tipo ????'),
        ];
    }
}
