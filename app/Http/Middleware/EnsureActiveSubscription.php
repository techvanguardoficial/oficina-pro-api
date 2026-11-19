<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia as rotas de uso do sistema quando a oficina não tem assinatura
 * ativa (status "active" ou "trialing"). Rotas de conta, assinatura e
 * pagamento ficam fora deste middleware para a oficina conseguir regularizar.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if (!$company || !$company->hasActiveSubscription()) {
            return response()->json([
                'error' => true,
                'message' => 'A assinatura da oficina não está ativa. Escolha um plano para continuar usando o sistema.',
                'code' => 'SUBSCRIPTION_INACTIVE',
                'details' => [],
            ], 402);
        }

        return $next($request);
    }
}
