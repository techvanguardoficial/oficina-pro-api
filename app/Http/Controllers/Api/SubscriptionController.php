<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;

class SubscriptionController extends Controller
{
    use HasRoleAndPermissions;
    protected StripeSubscriptionService $stripeService;
    protected \Stripe\StripeClient $stripe;

    public function __construct(StripeSubscriptionService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
    }

    /**
     * Listar planos disponíveis
     */
    public function listPlans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($plans);
    }

    /**
     * Obter plano específico
     */
    public function getPlan(Plan $plan)
    {
        return response()->json($plan);
    }

    /**
     * Obter assinatura atual da empresa
     */
    public function getCurrentSubscription(Request $request)
    {
        $this->authorizePermission('view_subscription');

        $company = $request->user()->company;
        $subscription = $company->subscription?->load('plan', 'invoices');

        if (!$subscription) {
            return response()->json(['message' => 'Nenhuma assinatura ativa'], 404);
        }

        return response()->json([
            'subscription' => $subscription,
            'plan' => $subscription->plan,
            'is_active' => $subscription->isActive(),
            'is_on_trial' => $subscription->isOnTrial(),
            'trial_ends_in' => $subscription->trialEndsIn(),
            'limit_violations' => $subscription->checkLimits(),
        ]);
    }

    /**
     * Criar/atualizar assinatura
     */
    public function updateSubscription(Request $request)
    {
        $this->authorizePermission('manage_subscription');

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'nullable|string',
        ]);

        try {
            $company = $request->user()->company;
            $plan = Plan::findOrFail($request->plan_id);

            $subscription = $this->stripeService->createOrUpdateSubscription(
                $company,
                $plan,
                $request->payment_method_id
            );

            return response()->json([
                'message' => 'Assinatura atualizada com sucesso',
                'subscription' => $subscription->load('plan'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar assinatura',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancelar assinatura
     */
    public function cancelSubscription(Request $request)
    {
        $this->authorizePermission('manage_subscription');

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $company = $request->user()->company;
            $subscription = $company->subscription;

            if (!$subscription) {
                return response()->json(['message' => 'Nenhuma assinatura para cancelar'], 404);
            }

            $this->stripeService->cancelSubscription($subscription, $request->reason);

            return response()->json(['message' => 'Assinatura cancelada com sucesso']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao cancelar assinatura',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Listar faturas da assinatura
     */
    public function invoices(Request $request)
    {
        $this->authorizePermission('view_subscription');

        $company = $request->user()->company;
        $subscription = $company->subscription;

        if (!$subscription) {
            return response()->json([], 200);
        }

        $invoices = $subscription->invoices()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($invoices);
    }

    /**
     * Checkout - Fluxo completo de assinatura
     * Recebe plan_id e payment_method_id e finaliza a assinatura
     */
    public function checkout(Request $request)
    {
        //$this->authorizePermission('manage_subscription');

        $request->validate([
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'sometimes|in:month,year',
        ]);

        $plan         = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->input('billing_cycle', 'month');
        $company      = $request->user()->company;
        $frontendUrl  = env('FRONTEND_URL', 'http://localhost:3000');

        // Seleciona o price_id correto conforme ciclo
        $stripePriceId = $billingCycle === 'year'
            ? $plan->stripe_annual_price_id
            : $plan->stripe_price_id;

        if (!$stripePriceId) {
            return response()->json([
                'message' => 'Plano não disponível para compra neste ciclo',
                'code'    => 'PLAN_NOT_AVAILABLE',
            ], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            // Criar Stripe Checkout Session
            $session = $stripe->checkout->sessions->create([
                'customer_email' => $company->email,
                'line_items' => [
                    [
                        'price'    => $stripePriceId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'allow_promotion_codes' => true,
                'success_url' => $frontendUrl . '/#/subscription/success?session_id={CHECKOUT_SESSION_ID}&company_id=' . $company->id,
                'cancel_url' => $frontendUrl . '/#/subscription/canceled',
                'metadata' => [
                    'company_id' => $company->id,
                    'user_id' => $request->user()->id,
                    'plan_id' => $plan->id,
                ],
            ]);

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'trial_days' => $plan->trial_days,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Checkout Error', [
                'user_id' => $request->user()->id,
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao gerar checkout',
                'error' => $e->getMessage(),
            ], 400);
        }
    }


    public function cancel(Request $request)
    {
        $this->authorizePermission('manage_subscription');

        $company = $request->user()->company;
        $subscription = $company->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'Nenhuma assinatura para cancelar',
                'code' => 'SUBSCRIPTION_NOT_FOUND',
            ], 404);
        }

        try {
            // Cancelar subscription no Stripe
            $this->stripeService->cancelSubscription($subscription, null);

            return response()->json([
                'message' => 'Assinatura cancelada com sucesso',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao cancelar assinatura',
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function resume(Request $request)
    {
        $this->authorizePermission('manage_subscription');

        $company = $request->user()->company;
        $subscription = $company->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'Nenhuma assinatura cancelada para retomar',
                'code' => 'SUBSCRIPTION_NOT_FOUND',
            ], 404);
        }

        try {
            // Retomar subscription no Stripe
            $this->stripe->subscriptions->update(
                $subscription->stripe_subscription_id,
                ['cancel_at_period_end' => false]
            );

            $subscription->update(['cancel_at_period_end' => false]);

            return response()->json([
                'message' => 'Assinatura retomada com sucesso',
                'status' => $subscription->status,
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'error' => 'Erro ao retomar assinatura',
                'code' => 'STRIPE_ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Confirmar subscription após checkout do Stripe
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            // Recuperar session do Stripe
            $session = $this->stripe->checkout->sessions->retrieve($request->session_id);

            // Validar metadata
            $companyId = $session->metadata->company_id ?? null;
            $planId = $session->metadata->plan_id ?? null;

            if (!$companyId || !$planId) {
                return response()->json([
                    'error' => 'Invalid session metadata',
                    'code' => 'INVALID_METADATA',
                ], 400);
            }

            // Aceita 'paid' (pagamento imediato) ou 'no_payment_required' (trial)
            if (!in_array($session->payment_status, ['paid', 'no_payment_required'])) {
                return response()->json([
                    'error' => 'Payment not completed',
                    'code' => 'PAYMENT_NOT_COMPLETED',
                ], 400);
            }

            // Recuperar subscription do Stripe
            $stripeSubscription = $this->stripe->subscriptions->retrieve($session->subscription);

            // Encontrar plano
            $plan = Plan::findOrFail($planId);

            // Encontrar company
            $company = \App\Models\Company::findOrFail($companyId);

            // Atualizar customer ID da company se não existir
            if (!$company->stripe_customer_id) {
                $company->update(['stripe_customer_id' => $session->customer]);
            }

            // Criar ou atualizar subscription
            $subscription = Subscription::updateOrCreate(
                ['company_id' => $companyId],
                [
                    'plan_id' => $planId,
                    'stripe_customer_id' => $session->customer,
                    'stripe_subscription_id' => $session->subscription,
                    'status' => $stripeSubscription->status,
                    'current_period_start' => $stripeSubscription->current_period_start
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)
                        : null,
                    'current_period_end' => $stripeSubscription->current_period_end
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                        : null,
                    'trial_starts_at' => $stripeSubscription->trial_start
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_start)
                        : null,
                    'trial_ends_at' => $stripeSubscription->trial_end
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end)
                        : null,
                ]
            );

            return response()->json([
                'message' => 'Subscription confirmed successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'status' => $subscription->status,
                ],
            ], 201);
        } catch (ApiErrorException $e) {
            return response()->json([
                'error' => 'Failed to confirm subscription',
                'code' => 'STRIPE_ERROR',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Subscription Confirm Error', [
                'session_id' => $request->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to confirm subscription',
                'code' => 'ERROR',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle canceled checkout session
     */
    public function checkoutCanceled(Request $request)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return redirect($frontendUrl . '/subscription/canceled');
    }



    /**
     * Obter status da assinatura da empresa
     */
    public function status(Request $request)
    {
        $this->authorizePermission('view_subscription');

        $company = $request->user()->company;
        $subscription = $company->subscription;

        if (!$subscription) {
            return response()->json([
                'active' => false,
                'message' => 'Nenhuma assinatura ativa',
            ]);
        }

        $subscription->load('plan');

        return response()->json([
            'active' => $subscription->isActive(),
            'on_trial' => $subscription->isOnTrial(),
            'status' => $subscription->status,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'price' => $subscription->plan->price,
                'interval' => $subscription->plan->interval,
            ],
            'current_period_start' => $subscription->current_period_start,
            'current_period_end' => $subscription->current_period_end,
            'trial_ends_at' => $subscription->trial_ends_at,
            'trial_days_remaining' => $subscription->trialEndsIn(),
        ]);
    }

}
