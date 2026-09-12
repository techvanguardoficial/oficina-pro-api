<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('stock_id')
                ->nullable()
                ->after('orders_id')
                ->constrained('stocks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropForeign(['stock_id']);
            $table->dropColumn('stock_id');
        });
    }
};
