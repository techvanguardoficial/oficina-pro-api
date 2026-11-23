<?php

namespace Tests\Feature;

use App\Models\ClientAppUser;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Perfil do Portal: trocar senha ou e-mail exige a senha atual. */
class ClientPortalProfileSecurityTest extends TestCase
{
    private ClientAppUser $appUser;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appUser = ClientAppUser::create([
            'name' => 'Motorista Teste', 'email' => 'motorista@example.com',
            'phone' => '11900000000', 'password' => Hash::make('senha-atual'),
            'onboarding_completed' => true,
        ]);
        $this->token = $this->appUser->createToken('client-portal', ['client'])->plainTextToken;
    }

    private function updateMe(array $data)
    {
        return $this->withToken($this->token)->putJson('/api/v1/client/me', $data);
    }

    public function test_password_change_without_current_password_is_rejected(): void
    {
        $this->updateMe(['password' => 'senha-nova', 'password_confirmation' => 'senha-nova'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password'], 'details');

        $this->updateMe([
            'password' => 'senha-nova', 'password_confirmation' => 'senha-nova', 'current_password' => 'errada',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('senha-atual', $this->appUser->fresh()->password));
    }

    public function test_password_change_with_current_password_revokes_other_sessions(): void
    {
        $this->appUser->createToken('outro-celular', ['client']);

        $this->updateMe([
            'password' => 'senha-nova', 'password_confirmation' => 'senha-nova', 'current_password' => 'senha-atual',
        ])->assertStatus(200);

        $this->assertTrue(Hash::check('senha-nova', $this->appUser->fresh()->password));
        $this->assertSame(1, $this->appUser->tokens()->count());
        $this->assertSame('client-portal', $this->appUser->tokens()->first()->name);
    }

    public function test_email_change_requires_current_password(): void
    {
        $this->updateMe(['email' => 'novo@example.com'])->assertStatus(422);
        $this->assertSame('motorista@example.com', $this->appUser->fresh()->email);

        $this->updateMe(['email' => 'novo@example.com', 'current_password' => 'senha-atual'])->assertStatus(200);
        $this->assertSame('novo@example.com', $this->appUser->fresh()->email);
    }

    public function test_other_fields_do_not_need_the_password(): void
    {
        $this->updateMe(['name' => 'Nome Novo', 'phone' => '11911112222', 'email' => 'motorista@example.com'])
            ->assertStatus(200);

        $this->assertSame('Nome Novo', $this->appUser->fresh()->name);
    }
}
