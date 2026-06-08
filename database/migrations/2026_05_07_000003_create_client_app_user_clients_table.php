<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_app_user_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_app_user_id')->constrained('client_app_users')->onDelete('cascade');
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['client_app_user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_app_user_clients');
    }
};
