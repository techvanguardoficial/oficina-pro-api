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
        Schema::create('car_mileages', function (Blueprint $table) {
            $table->id();
            $table->string('mileage', 50)->nullable();
            $table->string('vehicles_placa', 7);
            $table->foreignId('order_services_id')->constrained('order_services')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vehicles_placa')
                ->references('placa')
                ->on('vehicles')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_mileages');
    }
};
