<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Payment;
use App\Services\AbacatePayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Exception;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Criar Payment Intent para checkout
     * Usado para Preview do pagamento antes de assinar
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        try {
            $plan = Plan::findOrFail($request->plan_id);

            $paymentIntent = PaymentIntent::create([
                'amount' => $plan->price,
                'currency' => strtolower(config('services.stripe.currency', 'brl')),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                ],
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $plan->price,
                'currency' => config('services.stripe.currency', 'brl'),
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar Payment Intent',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Criar PIX para checkout via AbacatePay
     */
    public function checkoutPix(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        try {
            $user = $request->user();
            $company = $user->company;
            $plan = Plan::findOrFail($request->plan_id);

            $externalId = Str::uuid()->toString();

            $abacatePayService = new AbacatePayService();
            $pixData = $abacatePayService->createPix(
                $plan->price,
                $externalId,
                "Assinatura {$plan->name}"
            );

            $payment = Payment::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'gateway' => 'abacatepay',
                'payment_method' => 'pix',
                'gateway_payment_id' => $pixData['gateway_payment_id'],
                'external_id' => $externalId,
                'amount' => $plan->price,
                'currency' => 'BRL',
                'status' => 'pending',
                'expires_at' => $pixData['expires_at'],
                'metadata' => [
                    'br_code' => $pixData['br_code'],
                    'br_code_base64' => $pixData['br_code_base64'],
                ],
            ]);

            return response()->json([
                'payment_id' => $payment->id,
                'external_id' => $externalId,
                'br_code' => $pixData['br_code'],
                'br_code_base64' => $pixData['br_code_base64'],
                'amount' => $plan->price,
                'currency' => 'BRL',
                'expires_at' => $pixData['expires_at'],
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                ],
            ]);
        } catch (Exception $e) {
            \Log::error('PIX Checkout Error', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'Erro ao gerar PIX',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Listar Payment Methods salvos do usuário
     */
    public function listPaymentMethods(Request $request)
    {
        try {
            $company = $request->user()->company;
            $subscription = $company->subscription;

            if (!$subscription || !$subscription->stripe_customer_id) {
                return response()->json([], 200);
            }

            $customer = \Stripe\Customer::retrieve($subscription->stripe_customer_id);

            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customer->id,
                'type' => 'card',
            ]);

            return response()->json([
                'payment_methods' => $paymentMethods->data,
                'has_default' => !empty($customer->default_source) || !empty($customer->invoice_settings?->default_payment_method),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar Payment Methods',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Deletar Payment Method salvo
     */
    public function deletePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        try {
            \Stripe\PaymentMethod::retrieve($request->payment_method_id)->detach();

            return response()->json([
                'message' => 'Método de pagamento removido com sucesso',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover Payment Method',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Definir Payment Method como padrão
     */
    public function setDefaultPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        try {
            $company = $request->user()->company;
            $subscription = $company->subscription;

            if (!$subscription || !$subscription->stripe_customer_id) {
                return response()->json([
                    'message' => 'Nenhuma assinatura encontrada',
                ], 404);
            }

            $customer = \Stripe\Customer::retrieve($subscription->stripe_customer_id);

            $customer->invoice_settings = [
                'default_payment_method' => $request->payment_method_id,
            ];
            $customer->save();

            return response()->json([
                'message' => 'Método de pagamento definido como padrão',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao definir Payment Method padrão',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Webhook do Stripe
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('stripe-signature');
        $body = $request->getContent();

        try {
            $event = \Stripe\Webhook::constructEvent(
                $body,
                $signature,
                config('services.stripe.webhook_secret')
            );

            // Log do evento
            \Log::info('Stripe Webhook Event', [
                'type' => $event['type'],
                'id' => $event['id'],
            ]);

            // Processar eventos
            switch ($event['type']) {
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event);
                    break;

                case 'invoice.created':
                    $this->handleInvoiceCreated($event);
                    break;
            }

            return response()->json(['status' => 'received']);
        } catch (\Exception $e) {
            \Log::error('Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Processar: customer.subscription.created
     */
    private function handleSubscriptionCreated(array $event)
    {
        $stripeSubscription = $event['data']['object'];
        $customerId = $stripeSubscription['customer'];

        // Encontrar empresa pelo Stripe Customer ID
        $company = \App\Models\Company::where('stripe_customer_id', $customerId)->first();

        if (!$company) {
            \Log::warning('Subscription Created but Company not found', [
                'stripe_customer_id' => $customerId,
            ]);
            return;
        }

        // Encontrar o plano pelo stripe_price_id
        $stripePriceId = $stripeSubscription['items']['data'][0]['price']['id'];
        $plan = \App\Models\Plan::where('stripe_price_id', $stripePriceId)->first();

        if (!$plan) {
            \Log::warning('Subscription Created but Plan not found', [
                'stripe_price_id' => $stripePriceId,
            ]);
            return;
        }

        // Criar ou atualizar subscription
        $subscription = \App\Models\Subscription::updateOrCreate(
            ['stripe_subscription_id' => $stripeSubscription['id']],
            [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'stripe_customer_id' => $customerId,
                'status' => $stripeSubscription['status'],
                'current_period_start' => \Carbon\Carbon::createFromTimestamp(
                    $stripeSubscription['current_period_start']
                ),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp(
                    $stripeSubscription['current_period_end']
                ),
                'trial_starts_at' => $stripeSubscription['trial_start'] ?
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_start']) :
                    null,
                'trial_ends_at' => $stripeSubscription['trial_end'] ?
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end']) :
                    null,
            ]
        );

        \Log::info('Subscription Created', [
            'subscription_id' => $subscription->id,
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $stripeSubscription['id'],
            'status' => $stripeSubscription['status'],
        ]);
    }

    /**
     * Processar: customer.subscription.updated
     */
    private function handleSubscriptionUpdated(array $event)
    {
        $stripeSubscription = $event['data']['object'];

        $subscription = \App\Models\Subscription::where(
            'stripe_subscription_id',
            $stripeSubscription['id']
        )->first();

        if ($subscription) {
            $subscription->update([
                'status' => $stripeSubscription['status'],
                'current_period_start' => \Carbon\Carbon::createFromTimestamp(
                    $stripeSubscription['current_period_start']
                ),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp(
                    $stripeSubscription['current_period_end']
                ),
            ]);

            \Log::info('Subscription Updated', [
                'subscription_id' => $subscription->id,
                'status' => $stripeSubscription['status'],
            ]);
        }
    }

    /**
     * Processar: customer.subscription.deleted
     */
    private function handleSubscriptionDeleted(array $event)
    {
        $stripeSubscription = $event['data']['object'];

        $subscription = \App\Models\Subscription::where(
            'stripe_subscription_id',
            $stripeSubscription['id']
        )->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'ended_at' => \Carbon\Carbon::createFromTimestamp(
                    $stripeSubscription['ended_at']
                ),
            ]);

            \Log::info('Subscription Deleted', [
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Processar: invoice.payment_succeeded
     */
    private function handleInvoicePaymentSucceeded(array $event)
    {
        $stripeInvoice = $event['data']['object'];

        $subscription = \App\Models\Subscription::where(
            'stripe_subscription_id',
            $stripeInvoice['subscription']
        )->first();

        if ($subscription) {
            \App\Models\SubscriptionInvoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoice['id']],
                [
                    'subscription_id' => $subscription->id,
                    'amount' => $stripeInvoice['amount_paid'],
                    'currency' => strtoupper($stripeInvoice['currency']),
                    'status' => $stripeInvoice['status'],
                    'description' => $stripeInvoice['description'],
                    'paid_at' => $stripeInvoice['paid_date'] ?
                        \Carbon\Carbon::createFromTimestamp($stripeInvoice['paid_date']) :
                        null,
                ]
            );

            \Log::info('Invoice Payment Succeeded', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $stripeInvoice['id'],
                'amount' => $stripeInvoice['amount_paid'],
            ]);
        }
    }

    /**
     * Processar: invoice.payment_failed
     */
    private function handleInvoicePaymentFailed(array $event)
    {
        $stripeInvoice = $event['data']['object'];

        $subscription = \App\Models\Subscription::where(
            'stripe_subscription_id',
            $stripeInvoice['subscription']
        )->first();

        if ($subscription) {
            $subscription->update(['status' => 'past_due']);

            // Notificar empresa via email
            $company = $subscription->company;
            \Log::warning('Invoice Payment Failed', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $stripeInvoice['id'],
                'company' => $company->name,
            ]);
        }
    }

    /**
     * Processar: invoice.created
     */
    private function handleInvoiceCreated(array $event)
    {
        $stripeInvoice = $event['data']['object'];

        $subscription = \App\Models\Subscription::where(
            'stripe_subscription_id',
            $stripeInvoice['subscription']
        )->first();

        if ($subscription) {
            \App\Models\SubscriptionInvoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoice['id']],
                [
                    'subscription_id' => $subscription->id,
                    'amount' => $stripeInvoice['total'],
                    'currency' => strtoupper($stripeInvoice['currency']),
                    'status' => $stripeInvoice['status'],
                    'description' => $stripeInvoice['description'],
                    'due_date' => $stripeInvoice['due_date'] ?
                        \Carbon\Carbon::createFromTimestamp($stripeInvoice['due_date']) :
                        null,
                ]
            );

            \Log::info('Invoice Created', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $stripeInvoice['id'],
            ]);
        }
    }
}
