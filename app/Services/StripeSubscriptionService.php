<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Exception;

class StripeSubscriptionService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Criar ou atualizar assinatura Stripe para uma empresa
     */
    public function createOrUpdateSubscription(Company $company, Plan $plan, ?string $paymentMethodId = null): Subscription
    {
        try {
            // Obter ou criar customer Stripe
            $stripeCustomer = $this->getOrCreateCustomer($company);

            $subscriptionData = [
                'customer' => $stripeCustomer->id,
                'items' => [
                    ['price' => $plan->stripe_price_id],
                ],
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ];

            if ($plan->trial_days > 0) {
                $subscriptionData['trial_period_days'] = $plan->trial_days;
            }

            if ($paymentMethodId) {
                $subscriptionData['default_payment_method'] = $paymentMethodId;
                $subscriptionData['payment_behavior'] = 'allow_incomplete';
            }

            // Criar ou atualizar assinatura
            if ($company->subscription?->stripe_subscription_id) {
                $stripeSubscription = StripeSubscription::retrieve($company->subscription->stripe_subscription_id);
                $stripeSubscription->items->data[0]->delete();
                $stripeSubscription->addItem([
                    'price' => $plan->stripe_price_id,
                ]);
            } else {
                $stripeSubscription = StripeSubscription::create($subscriptionData);
            }

            // Salvar ou atualizar assinatura no banco de dados
            $subscription = $company->subscriptions()->updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscription->id],
                [
                    'plan_id' => $plan->id,
                    'stripe_customer_id' => $stripeCustomer->id,
                    'status' => $stripeSubscription->status,
                    'trial_ends_at' => $stripeSubscription->trial_end ? now()->setTimestamp($stripeSubscription->trial_end) : null,
                    'current_period_start' => now()->setTimestamp($stripeSubscription->current_period_start),
                    'current_period_end' => now()->setTimestamp($stripeSubscription->current_period_end),
                ]
            );

            return $subscription;
        } catch (Exception $e) {
            throw new Exception('Erro ao criar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar assinatura
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): void
    {
        try {
            if ($subscription->stripe_subscription_id) {
                StripeSubscription::retrieve($subscription->stripe_subscription_id)->cancel();
            }

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_reason' => $reason,
            ]);
        } catch (Exception $e) {
            throw new Exception('Erro ao cancelar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar dados da assinatura com Stripe
     */
    public function syncSubscriptionFromStripe(Subscription $subscription): void
    {
        try {
            if (!$subscription->stripe_subscription_id) {
                return;
            }

            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

            $subscription->update([
                'status' => $stripeSubscription->status,
                'trial_ends_at' => $stripeSubscription->trial_end ? now()->setTimestamp($stripeSubscription->trial_end) : null,
                'current_period_start' => now()->setTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => now()->setTimestamp($stripeSubscription->current_period_end),
                'ended_at' => $stripeSubscription->ended_at ? now()->setTimestamp($stripeSubscription->ended_at) : null,
            ]);
        } catch (Exception $e) {
            throw new Exception('Erro ao sincronizar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Processar webhook de fatura
     */
    public function handleInvoiceEvent(array $eventData): void
    {
        try {
            $stripeInvoice = $eventData['data']['object'];

            // Encontrar assinatura relacionada
            $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice['subscription'])
                ->firstOrFail();

            // Atualizar ou criar fatura
            SubscriptionInvoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoice['id']],
                [
                    'subscription_id' => $subscription->id,
                    'amount' => $stripeInvoice['amount_paid'],
                    'currency' => strtoupper($stripeInvoice['currency']),
                    'status' => $stripeInvoice['status'],
                    'description' => $stripeInvoice['description'],
                    'paid_at' => $stripeInvoice['paid'] ? now()->setTimestamp($stripeInvoice['paid_date']) : null,
                    'due_date' => $stripeInvoice['due_date'] ? now()->setTimestamp($stripeInvoice['due_date']) : null,
                ]
            );
        } catch (Exception $e) {
            throw new Exception('Erro ao processar fatura: ' . $e->getMessage());
        }
    }

    /**
     * Obter ou criar customer no Stripe
     */
    private function getOrCreateCustomer(Company $company): StripeCustomer
    {
        $subscription = $company->subscription;

        if ($subscription?->stripe_customer_id) {
            return StripeCustomer::retrieve($subscription->stripe_customer_id);
        }

        return StripeCustomer::create([
            'email' => $company->email,
            'name' => $company->name,
            'phone' => $company->phone,
            'description' => "Company: {$company->name} (CNPJ: {$company->cnpj})",
        ]);
    }
}
