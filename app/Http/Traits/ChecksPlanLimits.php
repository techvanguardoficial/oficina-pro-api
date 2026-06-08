<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ChecksPlanLimits
{
    /**
     * Check if the company has reached its plan limit for a given resource
     */
    protected function checkPlanLimit(string $resource): ?JsonResponse
    {
        $user = auth()->user();
        $company = $user->company;

        if (!$company->hasActiveSubscription()) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'Your company does not have an active subscription.',
            ], 403);
        }

        $subscription = $company->subscription;
        $plan = $subscription->plan;

        $limits = [
            'users' => [
                'current' => $company->users()->count(),
                'max' => $plan->max_users,
                'label' => 'usuários',
            ],
            'clients' => [
                'current' => $company->clients()->count(),
                'max' => $plan->max_clients,
                'label' => 'clientes',
            ],
            'vehicles' => [
                'current' => $company->vehicles()->count(),
                'max' => $plan->max_vehicles,
                'label' => 'veículos',
            ],
            'orders' => [
                // Conta apenas as OS abertas no mês corrente — o limite reflete
                // o volume de uso recorrente, não o histórico acumulado da empresa.
                'current' => $company->orderServices()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'max' => $plan->max_orders,
                'label' => 'ordens de serviço neste mês',
            ],
        ];

        if (!isset($limits[$resource])) {
            return null;
        }

        $limit = $limits[$resource];

        if ($limit['max'] > 0 && $limit['current'] >= $limit['max']) {
            return response()->json([
                'error' => 'Plan limit exceeded',
                'message' => "Você atingiu o limite de {$limit['max']} {$limit['label']} para seu plano.",
                'resource' => $resource,
                'current' => $limit['current'],
                'limit' => $limit['max'],
            ], 403);
        }

        return null;
    }
}
