<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

/**
 * Fabricantes e modelos: cadastro compartilhado entre todas as oficinas.
 * Qualquer oficina cadastra; não há edição nem exclusão pela API.
 */
class CarMakerModelTest extends TestCase
{
    public function test_creates_maker_normalized_and_rejects_duplicates(): void
    {
        $this->authenticateUser();

        $this->postJson('/api/v1/car-makers', ['manufacturer' => '  great   wall '])
            ->assertStatus(201)
            ->assertJsonPath('manufacturer', 'GREAT WALL')
            ->assertJsonPath('models', []);

        // Mesmo nome com outra caixa/espaços é duplicata
        $this->postJson('/api/v1/car-makers', ['manufacturer' => 'Great Wall'])
            ->assertStatus(422)
            ->assertJsonPath('details.manufacturer.0', 'Essa fabricante já está cadastrada.');
        $this->postJson('/api/v1/car-makers', ['manufacturer' => '   '])->assertStatus(422);
        $this->postJson('/api/v1/car-makers', ['manufacturer' => str_repeat('A', 101)])->assertStatus(422);

        $this->assertSame(1, CarMaker::where('manufacturer', 'GREAT WALL')->count());

        // Fica registrado quem cadastrou
        $this->assertTrue(Audit::where('auditable_type', CarMaker::class)
            ->where('action', 'created')->where('user_id', $this->user->id)->exists());
    }

    public function test_deleted_maker_name_can_be_registered_again(): void
    {
        $this->authenticateUser();
        CarMaker::factory()->create(['manufacturer' => 'LADA'])->delete();

        $this->postJson('/api/v1/car-makers', ['manufacturer' => 'lada'])->assertStatus(201);
    }

    public function test_creates_model_unique_per_maker(): void
    {
        $this->authenticateUser();
        $kia = CarMaker::factory()->create(['manufacturer' => 'KIA']);
        $hyundai = CarMaker::factory()->create(['manufacturer' => 'HYUNDAI']);

        $this->postJson("/api/v1/car-makers/{$kia->id}/models", ['model' => ' sportage '])
            ->assertStatus(201)
            ->assertJsonPath('model', 'SPORTAGE')
            ->assertJsonPath('car_makers_id', $kia->id);

        $this->postJson("/api/v1/car-makers/{$kia->id}/models", ['model' => 'Sportage'])
            ->assertStatus(422)
            ->assertJsonPath('details.model.0', 'Esse modelo já está cadastrado em KIA.');

        // Mesmo nome em outra fabricante pode
        $this->postJson("/api/v1/car-makers/{$hyundai->id}/models", ['model' => 'SPORTAGE'])->assertStatus(201);
        $this->postJson('/api/v1/car-makers/999999/models', ['model' => 'X'])->assertStatus(404);

        $this->getJson("/api/v1/car-makers/{$kia->id}/models")
            ->assertStatus(200)
            ->assertJsonCount(1, 'models')
            ->assertJsonPath('models.0.model', 'SPORTAGE');
    }

    public function test_registration_is_visible_to_other_companies(): void
    {
        $this->authenticateUser();
        $maker = $this->postJson('/api/v1/car-makers', ['manufacturer' => 'BYD'])->json();
        $this->postJson("/api/v1/car-makers/{$maker['id']}/models", ['model' => 'DOLPHIN'])->assertStatus(201);

        $other = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->subscription->replicate()->fill(['company_id' => $other->company_id])->save();
        $this->actingAs($other, 'sanctum');

        $byd = collect($this->getJson('/api/v1/car-makers')->assertStatus(200)->json())
            ->firstWhere('manufacturer', 'BYD');
        $this->assertNotNull($byd);
        $this->assertSame(['DOLPHIN'], array_column($byd['models'], 'model'));
    }

    public function test_no_edit_or_delete_and_requires_login(): void
    {
        $maker = CarMaker::factory()->create();
        $model = CarModel::factory()->create(['car_makers_id' => $maker->id]);

        $this->postJson('/api/v1/car-makers', ['manufacturer' => 'X'])->assertStatus(401);

        $this->authenticateUser();
        $this->putJson("/api/v1/car-makers/{$maker->id}", ['manufacturer' => 'Y'])->assertStatus(405);
        $this->deleteJson("/api/v1/car-makers/{$maker->id}")->assertStatus(405);
        $this->deleteJson("/api/v1/car-models/{$model->id}")->assertStatus(405);
    }
}
