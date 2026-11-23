<?php

namespace Tests\Feature;

use App\Models\ClientAppUser;
use App\Notifications\ClientResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** "Esqueci minha senha" do Portal do Cliente. */
class ClientPortalPasswordResetTest extends TestCase
{
    private ClientAppUser $appUser;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->appUser = ClientAppUser::create([
            'name' => 'Motorista Teste', 'email' => 'motorista@example.com',
            'phone' => '11900000000', 'password' => Hash::make('senha-antiga'),
            'onboarding_completed' => true,
        ]);
    }

    public function test_forgot_password_sends_link_to_the_portal(): void
    {
        $this->postJson('/api/v1/client/auth/forgot-password', ['email' => 'motorista@example.com'])
            ->assertStatus(200);

        Notification::assertSentTo($this->appUser, ClientResetPasswordNotification::class, function ($n) {
            $url = $n->toMail($this->appUser)->viewData['url'];
            return str_starts_with($url, rtrim(config('app.client_portal_url'), '/') . '/reset-password?token=')
                && str_contains($url, 'email=motorista%40example.com');
        });
    }

    public function test_unknown_email_gets_the_same_answer_and_no_email(): void
    {
        $known = $this->postJson('/api/v1/client/auth/forgot-password', ['email' => 'motorista@example.com'])->json('message');
        $unknown = $this->postJson('/api/v1/client/auth/forgot-password', ['email' => 'ninguem@example.com'])
            ->assertStatus(200)
            ->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTimes(ClientResetPasswordNotification::class, 1);
    }

    public function test_reset_sets_new_password_revokes_sessions_and_logs_in(): void
    {
        $this->appUser->createToken('celular-antigo', ['client']);
        $token = null;
        $this->postJson('/api/v1/client/auth/forgot-password', ['email' => 'motorista@example.com']);
        Notification::assertSentTo($this->appUser, ClientResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;
            return true;
        });

        $response = $this->postJson('/api/v1/client/auth/reset-password', [
            'token' => $token, 'email' => 'motorista@example.com',
            'password' => 'senha-nova', 'password_confirmation' => 'senha-nova',
        ])->assertStatus(200);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertSame(1, $this->appUser->tokens()->count()); // só a sessão nova
        $this->assertTrue(Hash::check('senha-nova', $this->appUser->fresh()->password));

        // O mesmo link não serve duas vezes
        $this->postJson('/api/v1/client/auth/reset-password', [
            'token' => $token, 'email' => 'motorista@example.com',
            'password' => 'outra-senha', 'password_confirmation' => 'outra-senha',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_RESET_TOKEN');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/v1/client/auth/reset-password', [
            'token' => 'invalido', 'email' => 'motorista@example.com',
            'password' => 'senha-nova', 'password_confirmation' => 'senha-nova',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('senha-antiga', $this->appUser->fresh()->password));
    }
}
