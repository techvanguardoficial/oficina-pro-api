<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseType;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Aluguel',
            'Salários',
            'Fornecedores',
            'Impostos',
            'Água / Luz / Internet',
            'Manutenção',
            'Combustível',
            'Material de Escritório',
            'Outros',
        ];

        foreach ($types as $type) {
            ExpenseType::create(['type' => $type]);
        }
    }
}
