<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data em que a OS foi concluída. A receita das OS passa a ser contabilizada
 * por esta data, e não mais por updated_at (que muda a cada edição).
 * As OS já concluídas recebem o updated_at atual como melhor aproximação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_services', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('orders_status_id');
            $table->index(['company_id', 'orders_status_id', 'completed_at'], 'order_services_company_status_completed_index');
        });

        $completedIds = DB::table('orders_status')->whereIn('status', ['Concluído', 'CONCLUIDO'])->pluck('id');
        if ($completedIds->isNotEmpty()) {
            DB::table('order_services')
                ->whereIn('orders_status_id', $completedIds)
                ->whereNull('completed_at')
                ->update(['completed_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('order_services', function (Blueprint $table) {
            $table->dropIndex('order_services_company_status_completed_index');
            $table->dropColumn('completed_at');
        });
    }
};
