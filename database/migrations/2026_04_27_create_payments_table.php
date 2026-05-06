<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('gateway'); // 'stripe', 'abacatepay'
            $table->string('payment_method'); // 'card', 'pix'
            $table->string('gateway_payment_id')->nullable()->unique(); // ID da transação no gateway
            $table->string('external_id')->unique(); // UUID interno
            $table->integer('amount'); // em centavos
            $table->string('currency')->default('BRL');
            $table->string('status')->default('pending'); // pending, paid, expired, failed, cancelled
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable(); // brCode, brCodeBase64, etc
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('gateway');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
