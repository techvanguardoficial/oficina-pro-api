<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbacatePayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();
            $eventName = $payload['event'] ?? null;
            $externalId = $payload['reference_id'] ?? null;

            Log::info('AbacatePay Webhook Received', [
                'event' => $eventName,
                'reference_id' => $externalId,
            ]);

            if (!$eventName || !$externalId) {
                return response()->json(['status' => 'invalid'], 400);
            }

            return DB::transaction(function () use ($payload, $eventName, $externalId) {
                $webhookEvent = WebhookEvent::create([
                    'provider' => 'abacatepay',
                    'event_name' => $eventName,
                    'external_id' => $externalId,
                    'payload' => $payload,
                    'status' => 'received',
                ]);

                match ($eventName) {
                    'payment_confirmed' => $this->handlePaymentConfirmed($payload, $webhookEvent),
                    'payment_expired' => $this->handlePaymentExpired($payload, $webhookEvent),
                    'payment_failed' => $this->handlePaymentFailed($payload, $webhookEvent),
                    default => Log::info('Unknown AbacatePay event', ['event' => $eventName]),
                };

                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                return response()->json(['status' => 'received'], 200);
            });
        } catch (\Exception $e) {
            Log::error('AbacatePay Webhook Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    private function handlePaymentConfirmed(array $payload, WebhookEvent $webhookEvent): void
    {
        $externalId = $payload['reference_id'];

        $payment = Payment::where('external_id', $externalId)->first();

        if (!$payment) {
            Log::warning('Payment not found for confirmed webhook', [
                'external_id' => $externalId,
            ]);
            return;
        }

        if ($payment->status === 'paid') {
            Log::info('Payment already processed', ['payment_id' => $payment->id]);
            return;
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->createSubscriptionFromPayment($payment);

        Log::info('Payment Confirmed', [
            'payment_id' => $payment->id,
            'company_id' => $payment->company_id,
        ]);
    }

    private function handlePaymentExpired(array $payload, WebhookEvent $webhookEvent): void
    {
        $externalId = $payload['reference_id'];

        $payment = Payment::where('external_id', $externalId)
            ->whereIn('status', ['pending', 'received'])
            ->first();

        if ($payment) {
            $payment->update(['status' => 'expired']);

            Log::info('Payment Expired', [
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
            ]);
        }
    }

    private function handlePaymentFailed(array $payload, WebhookEvent $webhookEvent): void
    {
        $externalId = $payload['reference_id'];

        $payment = Payment::where('external_id', $externalId)
            ->whereIn('status', ['pending', 'received'])
            ->first();

        if ($payment) {
            $payment->update(['status' => 'failed']);

            Log::info('Payment Failed', [
                'payment_id' => $payment->id,
                'company_id' => $payment->company_id,
            ]);
        }
    }

    private function createSubscriptionFromPayment(Payment $payment): void
    {
        $existingSubscription = Subscription::where('company_id', $payment->company_id)
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        if ($existingSubscription) {
            Log::info('Company already has active subscription', [
                'company_id' => $payment->company_id,
            ]);
            return;
        }

        $plan = $payment->plan;

        $subscription = Subscription::create([
            'company_id' => $payment->company_id,
            'plan_id' => $plan->id,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'stripe_price_id' => null,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'trial_starts_at' => null,
            'trial_ends_at' => null,
        ]);

        Log::info('Subscription Created from AbacatePay Payment', [
            'subscription_id' => $subscription->id,
            'company_id' => $payment->company_id,
            'plan_id' => $plan->id,
        ]);
    }
}
