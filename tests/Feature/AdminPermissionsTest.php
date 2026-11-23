<?php

namespace Tests\Feature;

use App\Models\IncomeType;
use App\Models\User;
use Tests\TestCase;

/**
 * O administrador da oficina (users.admin = '1') tem todas as permissões, mesmo sem
 * papel (user_role) atribuído. Regressão: lançar receita respondia 403 para o dono da oficina.
 * Receitas seguem a mesma regra das despesas: qualquer usuário da oficina lança e edita,
 * sempre restrito à própria oficina.
 */
class AdminPermissionsTest extends TestCase
{
    private function incomeData(): array
    {
        return [
            'info' => 'Serviço avulso',
            'value' => 150.5,
            'date' => now()->toDateString(),
            'income_types_id' => IncomeType::create(['type' => 'Serviços'])->id,
        ];
    }

    public function test_admin_without_role_can_create_income(): void
    {
        $this->assertSame(0, $this->user->roles()->count());
        $this->authenticateUser();

        $response = $this->postJson('/api/v1/incomes', $this->incomeData());

        $response->assertStatus(201)
            ->assertJsonFragment(['info' => 'Serviço avulso']);

        $this->assertDatabaseHas('incomes', [
            'info' => 'Serviço avulso',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_without_role_can_view_subscription(): void
    {
        $this->authenticateUser();

        $this->getJson('/api/v1/subscription')->assertStatus(200);
    }

    public function test_employee_can_create_and_edit_income_like_expenses(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->company->id,
            'admin' => '0',
        ]);
        $this->authenticateUser($employee);

        $created = $this->postJson('/api/v1/incomes', $this->incomeData())
            ->assertStatus(201);

        $this->putJson("/api/v1/incomes/{$created->json('id')}", ['info' => 'Serviço corrigido'])
            ->assertStatus(200);

        $this->assertDatabaseHas('incomes', [
            'id' => $created->json('id'),
            'info' => 'Serviço corrigido',
        ]);
    }

    public function test_cannot_edit_income_of_another_company(): void
    {
        $this->authenticateUser();
        $incomeId = $this->postJson('/api/v1/incomes', $this->incomeData())->json('id');

        $outsider = User::factory()->create(['admin' => '1']); // outra oficina
        $this->authenticateUser($outsider);

        // O escopo por oficina nem encontra a receita: 404, sem revelar que ela existe
        $this->putJson("/api/v1/incomes/{$incomeId}", ['info' => 'Invasão'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('incomes', ['id' => $incomeId, 'info' => 'Invasão']);
    }
}
