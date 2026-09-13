<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Concluído',
            'Em andamento',
            'Cancelado',
            'Aprovado',
            'Em Espera',
            'Aberto',
            'Aguardando Peça',
            'Agendado',
        ];

        foreach ($statuses as $status) {
            DB::table('orders_status')->insertOrIgnore([
                'status'     => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
