<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passo 2/4 — adiciona `vehicle_id` (nullable) em `order_services`,
 * referenciando `vehicles.id`. Fica nullable propositalmente: o backfill
 * (comando `vehicles:migrate-fk-to-id`) preenche os valores a partir de
 * `(company_id, vehicle_placa)`; só depois disso a coluna antiga
 * `vehicle_placa` é removida e esta passa a ser NOT NULL (cleanup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_services', function (Blueprint $table) {
            if (!Schema::hasColumn('order_services', 'vehicle_id')) {
                $table->unsignedBigInteger('vehicle_id')->nullable()->after('vehicle_placa');
                $table->foreign('vehicle_id')
                    ->references('id')->on('vehicles')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->index('vehicle_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_services', function (Blueprint $table) {
            if (Schema::hasColumn('order_services', 'vehicle_id')) {
                $table->dropForeign(['vehicle_id']);
                $table->dropColumn('vehicle_id');
            }
        });
    }
};
