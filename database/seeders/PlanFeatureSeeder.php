<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Feature;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'basic' => Plan::where('slug', 'basic')->first(),
            'professional' => Plan::where('slug', 'professional')->first(),
            'enterprise' => Plan::where('slug', 'enterprise')->first(),
        ];

        $features = [
            'basic_reports' => Feature::where('slug', 'basic_reports')->first(),
            'advanced_reports' => Feature::where('slug', 'advanced_reports')->first(),
            'custom_dashboard' => Feature::where('slug', 'custom_dashboard')->first(),
            'export_data' => Feature::where('slug', 'export_data')->first(),
            'client_management' => Feature::where('slug', 'client_management')->first(),
            'vehicle_management' => Feature::where('slug', 'vehicle_management')->first(),
            'order_management' => Feature::where('slug', 'order_management')->first(),
            'user_management' => Feature::where('slug', 'user_management')->first(),
            'inventory_control' => Feature::where('slug', 'inventory_control')->first(),
            'api_access' => Feature::where('slug', 'api_access')->first(),
            'webhooks' => Feature::where('slug', 'webhooks')->first(),
            'external_integrations' => Feature::where('slug', 'external_integrations')->first(),
            'email_support' => Feature::where('slug', 'email_support')->first(),
            'priority_support' => Feature::where('slug', 'priority_support')->first(),
            'dedicated_support' => Feature::where('slug', 'dedicated_support')->first(),
            'multi_branch' => Feature::where('slug', 'multi_branch')->first(),
            'online_scheduling' => Feature::where('slug', 'online_scheduling')->first(),
            'customer_portal' => Feature::where('slug', 'customer_portal')->first(),
            'sms_notifications' => Feature::where('slug', 'sms_notifications')->first(),
        ];

        // ============================================================
        // PLANO BÁSICO - Features Incluídas
        // ============================================================
        $basicFeatures = [
            'basic_reports' => true,
            'client_management' => true,
            'vehicle_management' => true,
            'order_management' => true,
            'user_management' => true,
            'email_support' => true,
            // Não incluídas
            'advanced_reports' => false,
            'custom_dashboard' => false,
            'export_data' => false,
            'inventory_control' => false,
            'api_access' => false,
            'webhooks' => false,
            'external_integrations' => false,
            'priority_support' => false,
            'dedicated_support' => false,
            'multi_branch' => false,
            'online_scheduling' => false,
            'customer_portal' => false,
            'sms_notifications' => false,
        ];

        foreach ($basicFeatures as $featureSlug => $isIncluded) {
            $plans['basic']->features()->attach(
                $features[$featureSlug]->id,
                ['is_included' => $isIncluded]
            );
        }

        // ============================================================
        // PLANO PROFISSIONAL - Features Incluídas
        // ============================================================
        $professionalFeatures = [
            'basic_reports' => true,
            'advanced_reports' => true,
            'custom_dashboard' => true,
            'export_data' => true,
            'client_management' => true,
            'vehicle_management' => true,
            'order_management' => true,
            'user_management' => true,
            'inventory_control' => true,
            'api_access' => true,
            'webhooks' => true,
            'external_integrations' => true,
            'email_support' => true,
            'priority_support' => true,
            'online_scheduling' => true,
            'customer_portal' => true,
            'sms_notifications' => true,
            // Não incluídas
            'dedicated_support' => false,
            'multi_branch' => false,
        ];

        foreach ($professionalFeatures as $featureSlug => $isIncluded) {
            $plans['professional']->features()->attach(
                $features[$featureSlug]->id,
                ['is_included' => $isIncluded]
            );
        }

        // ============================================================
        // PLANO ENTERPRISE - TODAS as Features
        // ============================================================
        $enterpriseFeatures = [
            'basic_reports' => true,
            'advanced_reports' => true,
            'custom_dashboard' => true,
            'export_data' => true,
            'client_management' => true,
            'vehicle_management' => true,
            'order_management' => true,
            'user_management' => true,
            'inventory_control' => true,
            'api_access' => true,
            'webhooks' => true,
            'external_integrations' => true,
            'email_support' => true,
            'priority_support' => true,
            'dedicated_support' => true,
            'multi_branch' => true,
            'online_scheduling' => true,
            'customer_portal' => true,
            'sms_notifications' => true,
        ];

        foreach ($enterpriseFeatures as $featureSlug => $isIncluded) {
            $plans['enterprise']->features()->attach(
                $features[$featureSlug]->id,
                ['is_included' => $isIncluded]
            );
        }
    }
}
