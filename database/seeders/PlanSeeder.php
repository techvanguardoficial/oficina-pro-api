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
                'price' => 9990,
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXsLn1LLu60wf',
                'stripe_price_id' => 'price_1TIymWL2BH1WYzRkKERs9Ybv',
                'trial_days' => 7,
                'max_users' => 1,
                'max_clients' => null, // ilimitado (histórico de cadastro nunca trava)
                'max_vehicles' => null, // ilimitado
                'max_orders' => 50, // limite de OS abertas por mês
                'max_parts_for_order' => 3,
                'max_services_for_order' => 3,
                'max_stock_quantity' => 100,
                'has_advanced_reports' => false,
                'has_api_access' => false,
                'has_priority_support' => false,
                'has_multi_branch' => false,
                'has_stock_management' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'slug' => 'professional',
                'description' => 'Ideal para oficinas em crescimento com múltiplas operações',
                'price' => 29990,
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXs7qYlgMqpdm',
                'stripe_price_id' => 'price_1TIymxL2BH1WYzRkG2YRHRzV',
                'trial_days' => 7,
                'max_users' => 3,
                'max_clients' => null, // ilimitado
                'max_vehicles' => null, // ilimitado
                'max_orders' => 300, // limite de OS abertas por mês
                'max_parts_for_order' => null,
                'max_services_for_order' => null,
                'max_stock_quantity' => 200,
                'has_advanced_reports' => true,
                'has_api_access' => false,
                'has_priority_support' => true,
                'has_multi_branch' => false,
                'has_stock_management' => true,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solução completa para grandes redes e grupos de oficinas',
                'price' => 39990,
                'interval' => 'month',
                'stripe_product_id' => 'prod_UHXtlDqjInEkhg',
                'stripe_price_id' => 'price_1TIynPL2BH1WYzRkrwhtbXx6',
                'trial_days' => 7,
                'max_users' => 10,
                'max_clients' => null,
                'max_vehicles' => null,
                'max_orders' => null,
                'max_parts_for_order' => null,
                'max_services_for_order' => null,
                'max_stock_quantity' => null,
                'has_advanced_reports' => true,
                'has_api_access' => true,
                'has_priority_support' => true,
                'has_multi_branch' => true,
                'has_stock_management' => true,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
