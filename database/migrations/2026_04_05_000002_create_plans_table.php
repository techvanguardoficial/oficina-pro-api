<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Basic", "Pro", "Enterprise"
            $table->string('slug')->unique(); // "basic", "pro", "enterprise"
            $table->text('description')->nullable();
            $table->integer('price'); // em centavos (ex: 2999 = R$ 29,99)
            $table->string('interval')->default('month'); // 'month', 'year'
            $table->string('stripe_product_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable()->unique();
            $table->integer('trial_days')->default(0); // dias de trial gratuito
            $table->integer('max_users')->nullable(); // limitar quantidade de usuários (-1 = ilimitado)
            $table->integer('max_vehicles')->nullable();
            $table->integer('max_orders')->nullable();
            $table->integer('max_clients')->nullable();
            $table->integer('sort_order')->default(0); // para ordenação na UI
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
