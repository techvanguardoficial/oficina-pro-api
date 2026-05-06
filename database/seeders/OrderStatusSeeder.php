<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OrderStatus;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrderStatus::create([
            'status' => 'Concluído',
        ]);
        OrderStatus::create([
            'status' => 'Em andamento',
        ]);
        OrderStatus::create([
            'status' => 'Cancelado',
        ]);
        OrderStatus::create([
            'status' => 'Aprovado',
        ]);
        OrderStatus::create([
            'status' => 'Em Espera',
        ]);
        OrderStatus::create([
            'status' => 'Aberto',
        ]);
        OrderStatus::create([
            'status' => 'Aguardando Peça',
        ]);
    }
}
