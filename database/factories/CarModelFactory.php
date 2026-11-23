<?php

namespace Database\Factories;

use App\Models\CarMaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CarModel>
 */
class CarModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'model' => fake()->unique()->lexify('MODELO ????'),
            'car_makers_id' => CarMaker::factory(),
        ];
    }
}
