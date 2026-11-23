<?php

namespace Database\Factories;

use App\Models\CarModel;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Placa no padrão Mercosul: AAA0A00
            'placa' => strtoupper(fake()->unique()->bothify('???#?##')),
            'year' => (string) fake()->numberBetween(2005, 2025),
            'color' => fake()->safeColorName(),
            'km' => (string) fake()->numberBetween(1000, 200000),
            'car_models_id' => CarModel::factory(),
            'clients_id' => Client::factory(),
            // Mesma oficina do cliente, a não ser que o teste informe outra
            'company_id' => fn (array $attributes) => Client::find($attributes['clients_id'])?->company_id,
        ];
    }
}
