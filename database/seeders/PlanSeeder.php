<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basic',
                'description' => 'Perfeito para oficinas pequenas que estão começando',
                'price' => 9990, // R$ 99,00
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXsLn1LLu60wf',
                'stripe_price_id' => 'price_1TIymWL2BH1WYzRkKERs9Ybv',
                'trial_days' => 7,
                'max_users' => 2,
                'max_vehicles' => 200,
                'max_orders' => -1,
                'max_clients' => 100,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'slug' => 'professional',
                'description' => 'Ideal para oficinas em crescimento com múltiplas operações',
                'price' => 29990, // R$ 299,00
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXs7qYlgMqpdm',
                'stripe_price_id' => 'price_1TIymxL2BH1WYzRkG2YRHRzV',
                'trial_days' => 7,
                'max_users' => 5,
                'max_vehicles' => 500,
                'max_orders' => -1,
                'max_clients' => 300,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solução completa para grandes redes e grupos de oficinas',
                'price' => 39990, // R$ 399,00
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXtlDqjInEkhg',
                'stripe_price_id' => 'price_1TIynPL2BH1WYzRkrwhtbXx6',
                'trial_days' => 7,
                'max_users' => -1, // Ilimitado
                'max_vehicles' => -1,
                'max_orders' => -1,
                'max_clients' => -1,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        Plan::insert($plans);
    }
}
