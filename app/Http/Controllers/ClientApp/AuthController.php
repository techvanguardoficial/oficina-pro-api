<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAppUser;
use App\Models\ClientMagicToken;
use App\Models\ClientPortalCode;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return $this->createAccount($request, $magicToken->client_id, $magicToken);
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
     * Gerado pela oficina: cria um código de 6 dígitos alfanumérico para o cliente.
     * Rota protegida pelo guard da oficina (auth:user).
     */
    public function generatePortalCode(Request $request)
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $client    = Client::findOrFail($request->client_id);
        $companyId = auth('user')->user()->company_id;

        // Invalida códigos anteriores não usados deste cliente nesta empresa
        ClientPortalCode::where('client_id', $client->id)
            ->where('company_id', $companyId)
            ->whereNull('used_at')
            ->delete();

        // Gera código único de 6 dígitos alfanumérico (ex: A3K9P2)
        do {
            $code = strtoupper(Str::random(6));
        } while (ClientPortalCode::where('code', $code)->exists());

        $portalCode = ClientPortalCode::create([
            'client_id'  => $client->id,
            'company_id' => $companyId,
            'code'       => $code,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'code'       => $portalCode->code,
            'expires_at' => $portalCode->expires_at,
            'client'     => [
                'id'   => $client->id,
                'name' => $client->name . ' ' . ($client->lastname ?? ''),
            ],
        ]);
    }

    /**
     * Portal: valida o código e retorna os dados pré-preenchidos do cliente.
     * Rota pública.
     */
    public function verifyPortalCode(Request $request)
    {
        $request->validate(['code' => 'required|string|max:8']);

        $portalCode = ClientPortalCode::where('code', strtoupper(trim($request->code)))
            ->with(['client.phone', 'client.address'])
            ->first();

        if (!$portalCode || !$portalCode->isValid()) {
            return response()->json([
                'message' => 'Código inválido ou expirado.',
                'code'    => 'INVALID_CODE',
            ], 422);
        }

        $client = $portalCode->client;

        $existingUser = ClientAppUser::where('phone', $client->phone?->phone_one)
            ->orWhere(function ($q) use ($client) {
                if ($client->email) $q->where('email', $client->email);
            })
            ->first();

        return response()->json([
            'code_valid'          => true,
            'already_has_account' => (bool) $existingUser,
            'prefill' => [
                'name'  => trim($client->name . ' ' . ($client->lastname ?? '')),
                'email' => $client->email ?? '',
                'phone' => $client->phone?->phone_one ?? '',
                'cpf'   => $client->cpf_cnpj ?? '',
            ],
        ]);
    }

    /**
     * Portal: completa signup via código (análogo ao completeSignup via token).
     * Rota pública.
     */
    public function completeSignupByCode(Request $request)
    {
        $request->validate([
            'code'     => 'required|string|max:8',
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'cpf'      => 'nullable|string|max:14',
        ]);

        $portalCode = ClientPortalCode::where('code', strtoupper(trim($request->code)))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $this->createAccount($request, $portalCode->client_id, $portalCode);
    }

    /**
     * Portal (usuário logado): adiciona a oficina que gerou o código à conta.
     */
    public function redeemCode(Request $request)
    {
        $request->validate(['code' => 'required|string|max:8']);

        $portalCode = ClientPortalCode::where('code', strtoupper(trim($request->code)))->first();

        if (!$portalCode || !$portalCode->isValid()) {
            return response()->json([
                'message' => 'Código inválido ou expirado.',
                'code'    => 'INVALID_CODE',
            ], 422);
        }

        $appUser = auth('client')->user();
        $client  = Client::withoutGlobalScopes()->findOrFail($portalCode->client_id);

        DB::transaction(function () use ($appUser, $client, $portalCode) {
            $appUser->clients()->syncWithoutDetaching([$client->id]);
            $this->linkClientsMatching($appUser, $client);
            $portalCode->update(['used_at' => now()]);
        });

        $company = Company::find($client->company_id);

        return response()->json([
            'message'  => 'Oficina adicionada com sucesso!',
            'workshop' => $company?->fantasy_name ?: $company?->name,
        ]);
    }

    /**
     * Cria a conta do portal a partir de um código/token emitido pela oficina.
     * Nunca altera uma conta existente: quem já tem conta deve fazer login
     * e usar "Adicionar oficina".
     */
    private function createAccount(Request $request, int $clientId, ClientMagicToken|ClientPortalCode $credential)
    {
        $exists = ClientAppUser::where('phone', $request->phone)
            ->orWhere('email', $request->email)
            ->when($request->filled('cpf'), fn($q) => $q->orWhere('cpf', $request->cpf))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Você já tem uma conta. Faça login e use o código em "Adicionar oficina".',
                'code'    => 'ACCOUNT_EXISTS',
            ], 409);
        }

        $client = Client::withoutGlobalScopes()->findOrFail($clientId);

        $appUser = DB::transaction(function () use ($request, $client, $credential) {
            $appUser = ClientAppUser::create([
                'name'                 => $request->name,
                'email'                => $request->email,
                'phone'                => $request->phone,
                'password'             => Hash::make($request->password),
                'cpf'                  => $request->cpf,
                'onboarding_completed' => true,
                'last_login_at'        => now(),
            ]);

            $appUser->clients()->attach($client->id);
            $this->linkClientsMatching($appUser, $client);
            $credential->update(['used_at' => now()]);

            return $appUser;
        });

        $accessToken = $appUser->createToken('client-portal', ['client'])->plainTextToken;

        return response()->json([
            'message'      => 'Conta criada com sucesso!',
            'access_token' => $accessToken,
            'user'         => $this->userResource($appUser),
        ], 201);
    }

    /**
     * Vincula clientes de outras oficinas com o mesmo CPF, e-mail ou telefone
     * do cliente cadastrado pela oficina — nunca com dados digitados no portal,
     * que não são verificados.
     */
    private function linkClientsMatching(ClientAppUser $appUser, Client $source): void
    {
        $cpf   = $source->cpf_cnpj;
        $email = $source->email;
        $phone = $source->phone()->value('phone_one');

        if (!$cpf && !$email && !$phone) {
            return;
        }

        $matchIds = Client::withoutGlobalScopes()
            ->where('id', '!=', $source->id)
            ->where(function ($q) use ($cpf, $email, $phone) {
                if ($cpf)   $q->orWhere('cpf_cnpj', $cpf);
                if ($email) $q->orWhere('email', $email);
                if ($phone) $q->orWhereHas('phone', fn($p) => $p->where('phone_one', $phone));
            })
            ->pluck('id')
            ->all();

        if ($matchIds) {
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
