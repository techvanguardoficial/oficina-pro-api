<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $instance;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution_api.url'), '/');
        $this->apiKey = config('services.evolution_api.key');
        $this->instance = config('services.evolution_api.instance');
    }

    public function sendOtp(string $phone, string $code): bool
    {
        $message = "Seu código de acesso: *{$code}*\nVálido por 10 minutos.\n\n_Não compartilhe este código._";
        return $this->sendText($phone, $message);
    }

    public function sendText(string $phone, string $text): bool
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/message/sendText/{$this->instance}", [
                        'number' => $phone,
                        'text' => $text,
                    ]);

            if (!$response->successful()) {
                Log::warning('EvolutionAPI send failed', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('EvolutionAPI exception', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
