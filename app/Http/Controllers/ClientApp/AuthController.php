<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\ClientAppUser;
use App\Models\OtpCode;
use App\Models\Phone;
use App\Models\Scopes\CompanyScope;
use App\Models\Vehicle;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function requestOtp(Request $request, EvolutionApiService $evolutionApi)
    {
        $request->validate(['phone' => 'required|string|min:10']);

        $phone = $this->normalizePhone($request->phone);

        // Rate limiting: máximo 3 tentativas por 15 minutos
        $recentCount = OtpCode::where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'error'   => true,
                'message' => 'Muitas tentativas. Aguarde 15 minutos.',
                'code'    => 'TOO_MANY_ATTEMPTS',
            ], 429);
        }

        $clientIds = $this->findAllClientIdsByPhone($phone);

        if (empty($clientIds)) {
            return response()->json([
                'error'   => true,
                'message' => 'Telefone não encontrado. Entre em contato com sua oficina.',
                'code'    => 'PHONE_NOT_FOUND',
            ], 404);
        }

        // Invalida OTPs anteriores não utilizados
        OtpCode::where('phone', $phone)->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone'      => $phone,
            'code'       => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $sent = $evolutionApi->sendOtp($phone, $code);

        if (!$sent) {
            return response()->json([
                'error'   => true,
                'message' => 'Erro ao enviar o código. Tente novamente.',
                'code'    => 'SEND_FAILED',
            ], 500);
        }

        return response()->json(['message' => 'Código enviado via WhatsApp.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10',
            'code'  => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->phone);

        $otp = OtpCode::where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || $otp->code !== $request->code) {
            return response()->json([
                'error'   => true,
                'message' => 'Código inválido ou expirado.',
                'code'    => 'INVALID_OTP',
            ], 422);
        }

        $otp->markAsUsed();

        // Busca todos os client_ids deste telefone em todas as oficinas
        $clientIds = $this->findAllClientIdsByPhone($phone);

        $clientAppUser = ClientAppUser::firstOrCreate(['phone' => $phone]);
        $clientAppUser->update(['last_login_at' => now()]);

        // Vincula todos os registros de cliente sem remover os já existentes
        $clientAppUser->clients()->syncWithoutDetaching($clientIds);

        $token = $clientAppUser->createToken('client-app', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token'           => $token,
            'workshops_count' => count($clientIds),
        ]);
    }

    public function me(Request $request)
    {
        $clientAppUser = $request->user();

        $clients = $clientAppUser->clients()
            ->with('company:id,name,fantasy_name,phone')
            ->get();

        $clientIds = $clients->pluck('id');

        $vehiclesCount = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->distinct('placa')
            ->count('placa');

        return response()->json([
            'phone'          => $clientAppUser->phone,
            'last_login_at'  => $clientAppUser->last_login_at,
            'vehicles_count' => $vehiclesCount,
            'workshops'      => $clients->map(fn($c) => [
                'client_id'    => $c->id,
                'name'         => $c->full_name,
                'email'        => $c->email,
                'company'      => [
                    'id'           => $c->company->id,
                    'name'         => $c->company->name,
                    'fantasy_name' => $c->company->fantasy_name,
                    'phone'        => $c->company->phone,
                ],
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    // Retorna todos os client_ids com aquele telefone em qualquer oficina
    private function findAllClientIdsByPhone(string $phone): array
    {
        $last11 = substr($phone, -11);

        return Phone::where(function ($q) use ($last11) {
            $q->whereRaw("REGEXP_REPLACE(phone_one, '[^0-9]', '') LIKE ?", ["%{$last11}"])
              ->orWhereRaw("REGEXP_REPLACE(phone_two, '[^0-9]', '') LIKE ?", ["%{$last11}"])
              ->orWhereRaw("REGEXP_REPLACE(phone_three, '[^0-9]', '') LIKE ?", ["%{$last11}"]);
        })->pluck('clients_id')->unique()->values()->toArray();
    }

    // Normaliza para formato Evolution API: 5511999999999
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $digits = ltrim($digits, '0');

        if (!str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }

        return $digits;
    }
}
