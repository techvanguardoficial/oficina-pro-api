<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_fantasy_name' => 'nullable|string|max:255',
            'company_cnpj' => 'required|string|max:14|unique:companies,cnpj',
            'company_email' => 'required|string|email|max:255|unique:companies,email',
            'company_phone' => 'required|string|max:20',
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|string|email|max:255|unique:users,email',
            'user_phone' => 'required|string|max:20|unique:users,phone',
            'user_password' => 'required|string|confirmed|min:8',
        ]);

        try {
            DB::beginTransaction();

            $company = Company::create([
                'name' => $request->company_name,
                'fantasy_name' => $request->company_fantasy_name,
                'cnpj' => $request->company_cnpj,
                'email' => $request->company_email,
                'phone' => $request->company_phone,
                'status' => '1',
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $request->user_name,
                'email' => $request->user_email,
                'phone' => $request->user_phone,
                'password' => Hash::make($request->user_password),
                'admin' => '1',
            ]);

            try {
                $user->assignRole('admin');
            } catch (\Exception $roleException) {
                \Log::warning('Could not assign admin role: ' . $roleException->getMessage());
            }

            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;

            // Carregar relacionamentos
            $user->load(['company', 'company.subscription.plan']);

            // Preparar dados de assinatura
            $subscriptionData = $this->getSubscriptionData($user);

            return response()->json([
                'token' => $token,
                'user' => $user,
                'subscription' => $subscriptionData,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erro ao registrar empresa e usuário.',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Carregar relacionamentos
        $user->load(['company', 'company.subscription.plan']);

        // Preparar dados de assinatura
        $subscriptionData = $this->getSubscriptionData($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'subscription' => $subscriptionData,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A senha atual está incorreta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['company', 'company.subscription.plan.features']);
        $subscriptionData = $this->getSubscriptionData($user);

        return response()->json([
            'user' => $user,
            'subscription' => $subscriptionData,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Link de recuperação enviado com sucesso.'])
            : response()->json(['message' => 'Não foi possível enviar o link de recuperação.', 'status' => $status], 400);
    }
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(\Illuminate\Support\Str::random(60));

                $user->save();

                event(new \Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Sua senha foi redefinida com sucesso!'])
            : response()->json(['message' => 'Não foi possível redefinir sua senha.', 'status' => $status], 400);
    }

    /**
     * Preparar dados de assinatura da empresa
     */
    private function getSubscriptionData(User $user): array
    {
        $company = $user->company;
        $subscription = $company?->subscription;

        if (!$subscription) {
            return [
                'status' => 'no_subscription',
                'has_active_subscription' => false,
                'plan' => null,
                'trial' => null,
                'current_period' => null,
            ];
        }

        return [
            'status' => $subscription->status,
            'has_active_subscription' => $subscription->isActive(),
            'is_on_trial' => $subscription->isOnTrial(),
            'trial_ends_in' => $subscription->trialEndsIn(),
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'price' => $subscription->plan->price,
                'interval' => $subscription->plan->interval,
                'max_users' => $subscription->plan->max_users,
                'max_vehicles' => $subscription->plan->max_vehicles,
                'max_orders' => $subscription->plan->max_orders,
                'max_clients' => $subscription->plan->max_clients,
            ],
            'current_period' => [
                'start' => $subscription->current_period_start,
                'end' => $subscription->current_period_end,
            ],
            'trial' => $subscription->isOnTrial() ? [
                'ends_at' => $subscription->trial_ends_at,
                'days_remaining' => $subscription->trialEndsIn(),
            ] : null,
            'limits_check' => $subscription->checkLimits(),
        ];
    }
}