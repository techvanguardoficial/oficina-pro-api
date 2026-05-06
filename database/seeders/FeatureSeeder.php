<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // Reporting
            [
                'name' => 'Relatórios Básicos',
                'slug' => 'basic_reports',
                'description' => 'Relatórios simples de receita e despesa',
                'category' => 'reporting',
                'sort_order' => 1,
            ],
            [
                'name' => 'Relatórios Avançados',
                'slug' => 'advanced_reports',
                'description' => 'Relatórios detalhados com gráficos e análises',
                'category' => 'reporting',
                'sort_order' => 2,
            ],
            [
                'name' => 'Dashboard Customizado',
                'slug' => 'custom_dashboard',
                'description' => 'Dashboard personalizável com widgets',
                'category' => 'reporting',
                'sort_order' => 3,
            ],
            [
                'name' => 'Exportar Dados',
                'slug' => 'export_data',
                'description' => 'Exportar relatórios em PDF, Excel e CSV',
                'category' => 'reporting',
                'sort_order' => 4,
            ],

            // General Features
            [
                'name' => 'Gerenciamento de Clientes',
                'slug' => 'client_management',
                'description' => 'CRUD completo de clientes',
                'category' => 'general',
                'sort_order' => 5,
            ],
            [
                'name' => 'Gerenciamento de Veículos',
                'slug' => 'vehicle_management',
                'description' => 'Cadastro e histórico de veículos',
                'category' => 'general',
                'sort_order' => 6,
            ],
            [
                'name' => 'Gerenciamento de Ordens',
                'slug' => 'order_management',
                'description' => 'Criar e rastrear ordens de serviço',
                'category' => 'general',
                'sort_order' => 7,
            ],
            [
                'name' => 'Gerenciamento de Usuários',
                'slug' => 'user_management',
                'description' => 'Adicionar e gerenciar usuários da empresa',
                'category' => 'general',
                'sort_order' => 8,
            ],
            [
                'name' => 'Controle de Estoque',
                'slug' => 'inventory_control',
                'description' => 'Gerenciar peças e insumos',
                'category' => 'general',
                'sort_order' => 9,
            ],

            // API & Integrations
            [
                'name' => 'API Access',
                'slug' => 'api_access',
                'description' => 'Acesso à API REST para integrações',
                'category' => 'integrations',
                'sort_order' => 10,
            ],
            [
                'name' => 'Webhooks',
                'slug' => 'webhooks',
                'description' => 'Configurar webhooks para eventos',
                'category' => 'integrations',
                'sort_order' => 11,
            ],
            [
                'name' => 'Integrações Externas',
                'slug' => 'external_integrations',
                'description' => 'Integrar com sistemas terceiros',
                'category' => 'integrations',
                'sort_order' => 12,
            ],

            // Support
            [
                'name' => 'Suporte por Email',
                'slug' => 'email_support',
                'description' => 'Suporte técnico por email (24h)',
                'category' => 'support',
                'sort_order' => 13,
            ],
            [
                'name' => 'Suporte Prioritário',
                'slug' => 'priority_support',
                'description' => 'Suporte prioritário com SLA 2h',
                'category' => 'support',
                'sort_order' => 14,
            ],
            [
                'name' => 'Suporte Dedicado',
                'slug' => 'dedicated_support',
                'description' => 'Suporte 24/7 com gerente dedicado',
                'category' => 'support',
                'sort_order' => 15,
            ],

            // Advanced
            [
                'name' => 'Multi-Filial',
                'slug' => 'multi_branch',
                'description' => 'Gerenciar múltiplas filiais',
                'category' => 'advanced',
                'sort_order' => 16,
            ],
            [
                'name' => 'Agendamento Online',
                'slug' => 'online_scheduling',
                'description' => 'Portal de agendamento para clientes',
                'category' => 'advanced',
                'sort_order' => 17,
            ],
            [
                'name' => 'Portal do Cliente',
                'slug' => 'customer_portal',
                'description' => 'Clientes acompanham suas ordens online',
                'category' => 'advanced',
                'sort_order' => 18,
            ],
            [
                'name' => 'Notificações SMS',
                'slug' => 'sms_notifications',
                'description' => 'Enviar notificações via SMS',
                'category' => 'advanced',
                'sort_order' => 19,
            ],
        ];

        Feature::insert($features);
    }
}
