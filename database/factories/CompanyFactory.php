<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'fantasy_name' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('119########'),
            'status' => '1',
        ];
    }
}
