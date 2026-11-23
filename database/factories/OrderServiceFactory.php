<?php

namespace Database\Factories;

use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderService>
 */
class OrderServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'info' => fake()->sentence(),
            'vehicle_id' => Vehicle::factory(),
            'orders_types_id' => OrderType::factory(),
            'orders_status_id' => OrderStatus::factory(),
            // Mesma oficina do veículo, a não ser que o teste informe outra
            'company_id' => fn (array $attributes) => Vehicle::find($attributes['vehicle_id'])?->company_id,
        ];
    }
}
