<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembrete conta a partir da última vez que o serviço foi feito (data e km),
 * e não da última visita à oficina: antes todo lembrete novo já nascia "Vencido".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->date('last_done_at')->nullable()->after('interval_months');
            $table->unsignedInteger('last_done_km')->nullable()->after('last_done_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->dropColumn(['last_done_at', 'last_done_km']);
        });
    }
};
