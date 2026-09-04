<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_vehicle_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_app_user_id')->constrained('client_app_users')->cascadeOnDelete();
            $table->string('placa', 10);
            $table->string('photo_path');
            $table->timestamps();

            $table->unique(['client_app_user_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_vehicle_photos');
    }
};
