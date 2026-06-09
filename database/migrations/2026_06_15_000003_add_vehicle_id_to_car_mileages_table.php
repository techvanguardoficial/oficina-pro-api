<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passo 3/4 — mesmo procedimento da migration anterior, agora para
 * `car_mileages.vehicles_placa` -> `car_mileages.vehicle_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_mileages', function (Blueprint $table) {
            if (!Schema::hasColumn('car_mileages', 'vehicle_id')) {
                $table->unsignedBigInteger('vehicle_id')->nullable()->after('vehicles_placa');
                $table->foreign('vehicle_id')
                    ->references('id')->on('vehicles')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->index('vehicle_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('car_mileages', function (Blueprint $table) {
            if (Schema::hasColumn('car_mileages', 'vehicle_id')) {
                $table->dropForeign(['vehicle_id']);
                $table->dropColumn('vehicle_id');
            }
        });
    }
};
