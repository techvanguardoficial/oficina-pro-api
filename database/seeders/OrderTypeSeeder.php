<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OrderType;

class OrderTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrderType::create([
            'type' => 'ORÇAMENTO',
        ]);
        OrderType::create([
            'type' => 'ORDEM DE SERVIÇO',
        ]);
    }
}
