<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbacatePayService
{
    public function createPix(int $amountCents, string $externalId, ?string $description = null): array
    {
        try {
            $payload = [
                'amount' => $amountCents,
                'reference_id' => $externalId,
                'description' => $description ?? 'Pagamento de Assinatura',
            ];

            $response = Http::baseUrl(config('services.abacatepay.base_url'))
                ->withToken(config('services.abacatepay.api_key'))
                ->acceptJson()
                ->post('/transparents/create', $payload)
                ->throw();

            $data = $response->json();

            return [
                'gateway_payment_id' => $data['id'] ?? null,
                'br_code' => $data['br_code'] ?? null,
                'br_code_base64' => $data['br_code_base64'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('AbacatePay createPix error', [
                'message' => $e->getMessage(),
                'external_id' => $externalId,
            ]);
            throw $e;
        }
    }

    public function verifyPayment(string $externalId): array
    {
        try {
            $response = Http::baseUrl(config('services.abacatepay.base_url'))
                ->withToken(config('services.abacatepay.api_key'))
                ->acceptJson()
                ->get("/transparents/{$externalId}")
                ->throw();

            return $response->json();
        } catch (\Exception $e) {
            Log::error('AbacatePay verifyPayment error', [
                'message' => $e->getMessage(),
                'external_id' => $externalId,
            ]);
            throw $e;
        }
    }
}