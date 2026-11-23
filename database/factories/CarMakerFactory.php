<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CarMaker>
 */
class CarMakerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'manufacturer' => fake()->unique()->lexify('MONTADORA ????'),
        ];
    }
}
