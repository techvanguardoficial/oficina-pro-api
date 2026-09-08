<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_car_model', function (Blueprint $table) {
            $table->foreignId('stock_id')->constrained('stocks')->cascadeOnDelete();
            $table->foreignId('car_model_id')->constrained('car_models')->cascadeOnDelete();
            $table->primary(['stock_id', 'car_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_car_model');
    }
};
