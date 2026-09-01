<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add "Agendado" status if it doesn't exist yet
        if (!DB::table('orders_status')->where('status', 'Agendado')->exists()) {
            DB::table('orders_status')->insert(['status' => 'Agendado']);
        }

        // Add "Garantia" type if only 2 types exist (ORÇAMENTO + ORDEM DE SERVIÇO)
        if (!DB::table('orders_types')->where('type', 'GARANTIA')->exists()) {
            DB::table('orders_types')->insert(['type' => 'GARANTIA']);
        }
    }

    public function down(): void
    {
        DB::table('orders_status')->where('status', 'Agendado')->delete();
        DB::table('orders_types')->where('type', 'GARANTIA')->delete();
    }
};
