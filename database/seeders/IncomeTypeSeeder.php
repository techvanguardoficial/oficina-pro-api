<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncomeType;

class IncomeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Serviços',
            'Peças',
            'Comissão',
            'Venda de Veículo',
            'Outros',
        ];

        foreach ($types as $type) {
            IncomeType::create(['type' => $type]);
        }
    }
}
