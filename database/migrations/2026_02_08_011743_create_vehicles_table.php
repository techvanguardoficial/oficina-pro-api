<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->string('placa', 7)->primary();
            $table->longText('info')->nullable();
            $table->string('chassis', 100)->nullable();
            $table->string('year', 4)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('km', 10)->nullable();
            $table->foreignId('car_models_id')->constrained('car_models');
            $table->foreignId('clients_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('placa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
