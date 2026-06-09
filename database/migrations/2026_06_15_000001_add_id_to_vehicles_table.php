<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Passo 1/4 da migração que substitui `placa` por `id` como chave primária
 * de `vehicles`, permitindo que a mesma placa exista em oficinas (companies)
 * diferentes — hoje `placa` é PK global, então um veículo só pode pertencer
 * a UMA empresa em todo o sistema.
 *
 * Esta migration é puramente aditiva: cria a coluna `id` autoincrement e a
 * preenche para as linhas já existentes, SEM remover a PK antiga ainda
 * (isso só acontece na migration de cleanup, depois do backfill das FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        // bigIncrements não pode ser adicionado diretamente como PK em uma
        // tabela que já tem PK (placa). Por isso criamos como coluna comum
        // com auto_increment e UNIQUE — a troca de PK acontece no cleanup.
        if (!Schema::hasColumn('vehicles', 'id')) {
            DB::statement('ALTER TABLE vehicles ADD COLUMN id BIGINT UNSIGNED AUTO_INCREMENT UNIQUE FIRST');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('id');
            });
        }
    }
};
