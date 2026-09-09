<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_car_model', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_from')->nullable()->after('car_model_id');
            $table->unsignedSmallInteger('year_to')->nullable()->after('year_from');
        });
    }

    public function down(): void
    {
        Schema::table('stock_car_model', function (Blueprint $table) {
            $table->dropColumn(['year_from', 'year_to']);
        });
    }
};
