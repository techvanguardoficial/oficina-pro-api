<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos (abatimentos) de uma OS. A OS pode ser paga em partes:
 * o saldo é o total da OS menos a soma destes pagamentos, e a receita da
 * oficina passa a contar na data de cada pagamento (paid_at).
 *
 * payment_due_date na OS: data combinada para o cliente pagar o restante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_service_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('order_service_id')->constrained('order_services')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->string('method', 20)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'paid_at']);
        });

        Schema::table('order_services', function (Blueprint $table) {
            $table->date('payment_due_date')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_services', function (Blueprint $table) {
            $table->dropColumn('payment_due_date');
        });

        Schema::dropIfExists('order_service_payments');
    }
};
