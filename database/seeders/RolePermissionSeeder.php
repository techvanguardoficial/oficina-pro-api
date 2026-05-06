<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Define all permissions
        $permissions = [
            // User management
            ['name' => 'create_user', 'description' => 'Criar novos usuários'],
            ['name' => 'view_users', 'description' => 'Visualizar usuários'],
            ['name' => 'edit_user', 'description' => 'Editar usuários'],
            ['name' => 'delete_user', 'description' => 'Deletar usuários'],

            // Client management
            ['name' => 'create_client', 'description' => 'Criar novos clientes'],
            ['name' => 'view_clients', 'description' => 'Visualizar clientes'],
            ['name' => 'edit_client', 'description' => 'Editar clientes'],
            ['name' => 'delete_client', 'description' => 'Deletar clientes'],

            // Vehicle management
            ['name' => 'create_vehicle', 'description' => 'Criar novos veículos'],
            ['name' => 'view_vehicles', 'description' => 'Visualizar veículos'],
            ['name' => 'edit_vehicle', 'description' => 'Editar veículos'],
            ['name' => 'delete_vehicle', 'description' => 'Deletar veículos'],

            // Order management
            ['name' => 'create_order', 'description' => 'Criar novos orçamentos'],
            ['name' => 'view_orders', 'description' => 'Visualizar orçamentos'],
            ['name' => 'edit_order', 'description' => 'Editar orçamentos'],
            ['name' => 'delete_order', 'description' => 'Deletar orçamentos'],

            // Financial management
            ['name' => 'create_income', 'description' => 'Registrar receitas'],
            ['name' => 'view_income', 'description' => 'Visualizar receitas'],
            ['name' => 'edit_income', 'description' => 'Editar receitas'],
            ['name' => 'delete_income', 'description' => 'Deletar receitas'],

            ['name' => 'create_expense', 'description' => 'Registrar despesas'],
            ['name' => 'view_expense', 'description' => 'Visualizar despesas'],
            ['name' => 'edit_expense', 'description' => 'Editar despesas'],
            ['name' => 'delete_expense', 'description' => 'Deletar despesas'],

            // Subscription management
            ['name' => 'manage_subscription', 'description' => 'Gerenciar assinatura'],
            ['name' => 'view_subscription', 'description' => 'Visualizar assinatura'],
            ['name' => 'upgrade_subscription', 'description' => 'Fazer upgrade da assinatura'],
            ['name' => 'downgrade_subscription', 'description' => 'Fazer downgrade da assinatura'],

            // Financial reports
            ['name' => 'view_reports', 'description' => 'Visualizar relatórios financeiros'],
            ['name' => 'export_reports', 'description' => 'Exportar relatórios'],

            // System settings
            ['name' => 'manage_settings', 'description' => 'Gerenciar configurações do sistema'],
            ['name' => 'view_settings', 'description' => 'Visualizar configurações'],
            ['name' => 'view_audit_logs', 'description' => 'Visualizar logs de auditoria'],
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Define roles with their permissions
        $roles = [
            'admin' => [
                // Admin has all permissions
                'create_user', 'view_users', 'edit_user', 'delete_user',
                'create_client', 'view_clients', 'edit_client', 'delete_client',
                'create_vehicle', 'view_vehicles', 'edit_vehicle', 'delete_vehicle',
                'create_order', 'view_orders', 'edit_order', 'delete_order',
                'create_income', 'view_income', 'edit_income', 'delete_income',
                'create_expense', 'view_expense', 'edit_expense', 'delete_expense',
                'manage_subscription', 'view_subscription', 'upgrade_subscription', 'downgrade_subscription',
                'view_reports', 'export_reports',
                'manage_settings', 'view_settings', 'view_audit_logs',
            ],
            'manager' => [
                // Manager can do most things except user management and settings
                'view_users',
                'create_client', 'view_clients', 'edit_client',
                'create_vehicle', 'view_vehicles', 'edit_vehicle',
                'create_order', 'view_orders', 'edit_order', 'delete_order',
                'create_income', 'view_income', 'edit_income',
                'create_expense', 'view_expense', 'edit_expense',
                'manage_subscription', 'view_subscription', 'upgrade_subscription', 'downgrade_subscription',
                'view_reports', 'export_reports',
                'view_settings', 'view_audit_logs',
            ],
            'user' => [
                // Regular user can only view and create basic items
                'view_clients',
                'view_vehicles',
                'create_order', 'view_orders', 'edit_order',
                'view_income',
                'view_expense',
                'view_subscription',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => ucfirst($roleName) . ' role']
            );

            $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);
        }
    }
}
