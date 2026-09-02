<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAppUser;
use App\Models\ClientMagicToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Gerado pela oficina: cria o token mágico para um cliente específico.
     * Rota protegida pelo guard da oficina (auth:user).
     */
    public function generateMagicLink(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $client    = Client::findOrFail($request->client_id);
        $companyId = auth('user')->user()->company_id;

        // Invalida tokens anteriores não usados para este cliente
        ClientMagicToken::where('client_id', $client->id)
            ->whereNull('used_at')
            ->delete();

        $token = ClientMagicToken::create([
            'token'      => Str::random(64),
            'client_id'  => $client->id,
            'company_id' => $companyId,
            'expires_at' => now()->addDays(7),
        ]);

        $frontendUrl = env('CLIENT_PORTAL_URL', 'http://localhost:3001');
        $link = $frontendUrl . '/access/' . $token->token;

        return response()->json([
            'link'       => $link,
            'expires_at' => $token->expires_at,
            'client'     => [
                'id'    => $client->id,
                'name'  => $client->name,
                'phone' => $client->phone?->phone_one ?? '',
            ],
        ]);
    }

    /**
     * Valida o token mágico e retorna os dados pré-preenchidos do cliente.
     * Rota pública.
     */
    public function verifyMagicToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $magicToken = ClientMagicToken::where('token', $request->token)
            ->with(['client.phone', 'client.address'])
            ->first();

        if (!$magicToken || !$magicToken->isValid()) {
            return response()->json([
                'message' => 'Link inválido ou expirado.',
                'code'    => 'INVALID_TOKEN',
            ], 422);
        }

        $client = $magicToken->client;

        // Verifica se já existe conta vinculada a este telefone ou email
        $existingUser = ClientAppUser::where('phone', $client->phone?->phone_one)
            ->orWhere(function ($q) use ($client) {
                if ($client->email) $q->where('email', $client->email);
            })
            ->first();

        return response()->json([
            'token_valid'      => true,
            'already_has_account' => (bool) $existingUser,
            'prefill' => [
                'name'    => trim($client->name . ' ' . ($client->lastname ?? '')),
                'email'   => $client->email ?? '',
                'phone'   => $client->phone?->phone_one ?? '',
                'cpf'     => $client->cpf_cnpj ?? '',
            ],
        ]);
    }

    /**
     * Completa o onboarding: cria a conta e vincula ao(s) cliente(s) da oficina.
     * Rota pública (chamada após verificar o token mágico).
     */
    public function completeSignup(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'cpf'      => 'nullable|string|max:14',
        ]);

        $magicToken = ClientMagicToken::where('token', $request->token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Evita duplicidade por email
        if (ClientAppUser::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Este e-mail já está em uso.',
                'code'    => 'EMAIL_TAKEN',
            ], 422);
        }

        $appUser = ClientAppUser::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name'                 => $request->name,
                'email'                => $request->email,
                'password'             => Hash::make($request->password),
                'cpf'                  => $request->cpf,
                'onboarding_completed' => true,
                'last_login_at'        => now(),
            ]
        );

        // Se já existia, atualiza dados e marca onboarding completo
        if (!$appUser->wasRecentlyCreated) {
            $appUser->update([
                'name'                 => $request->name,
                'email'                => $request->email,
                'password'             => Hash::make($request->password),
                'onboarding_completed' => true,
                'last_login_at'        => now(),
            ]);
        }

        // Vincula ao cliente da oficina que gerou o token (se ainda não vinculado)
        $appUser->clients()->syncWithoutDetaching([$magicToken->client_id]);

        // Marca token como usado
        $magicToken->update(['used_at' => now()]);

        // Tenta vincular outros clientes com mesmo CPF/telefone em outras oficinas
        $this->linkMatchingClients($appUser);

        $accessToken = $appUser->createToken('client-portal', ['client'])->plainTextToken;

        return response()->json([
            'message'      => 'Conta criada com sucesso!',
            'access_token' => $accessToken,
            'user'         => $this->userResource($appUser),
        ], 201);
    }

    /**
     * Login com email + senha.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $appUser = ClientAppUser::where('email', $request->email)->first();

        if (!$appUser || !Hash::check($request->password, $appUser->password)) {
            return response()->json([
                'message' => 'E-mail ou senha incorretos.',
                'code'    => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (!$appUser->onboarding_completed) {
            return response()->json([
                'message' => 'Complete seu cadastro pelo link enviado pela oficina.',
                'code'    => 'ONBOARDING_PENDING',
            ], 403);
        }

        $appUser->update(['last_login_at' => now()]);

        // Tenta vincular novos clientes que tenham surgido com mesmo CPF/telefone
        $this->linkMatchingClients($appUser);

        $accessToken = $appUser->createToken('client-portal', ['client'])->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'user'         => $this->userResource($appUser),
        ]);
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        $request->user('client')?->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado.']);
    }

    /**
     * Vincula automaticamente clientes de outras oficinas com mesmo CPF ou telefone.
     */
    private function linkMatchingClients(ClientAppUser $appUser): void
    {
        $matchIds = Client::withoutGlobalScopes()
            ->where(function ($q) use ($appUser) {
                if ($appUser->cpf) {
                    $q->where('cpf_cnpj', $appUser->cpf);
                }
            })
            ->orWhereHas('phone', fn($q) => $q->where('phone_one', $appUser->phone))
            ->pluck('id')
            ->toArray();

        if (!empty($matchIds)) {
            $appUser->clients()->syncWithoutDetaching($matchIds);
        }
    }

    private function userResource(ClientAppUser $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'cpf'                  => $user->cpf,
            'onboarding_completed' => $user->onboarding_completed,
        ];
    }
}
