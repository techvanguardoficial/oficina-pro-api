<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lista completa de estados brasileiros
        $states = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
            'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
            'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];

        // Prepara dados para insert em massa
        $data = collect($states)->map(function ($uf) {
            return [
                'uf' => $uf,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert em massa (muito mais rápido)
        DB::table('states')->insert($data);

        $this->command->info('27 estados cadastrados com sucesso!');
    }
}
