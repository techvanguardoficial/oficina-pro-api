<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Passo 4/4 — CLEANUP.
 *
 * Estado atual do banco após as migrations 1-3 + backfill:
 *   - vehicles: sem PK (foi dropada), `id` é AUTO_INCREMENT + UNIQUE KEY separado,
 *     `placa` tem UNIQUE KEY próprio e NÃO é mais PK.
 *   - order_services: tem vehicle_placa (VARCHAR) + vehicle_id (BIGINT, preenchido pelo backfill, ainda nullable)
 *   - car_mileages: tem vehicles_placa (VARCHAR) + vehicle_id (BIGINT, preenchido, ainda nullable)
 *   - FKs legadas (placa → vehicles.placa) já foram removidas automaticamente.
 *
 * Esta migration:
 *   1. Promove `id` de UNIQUE KEY para PRIMARY KEY em vehicles
 *   2. Substitui UNIQUE(placa) por UNIQUE(company_id, placa)
 *   3. Remove colunas legadas vehicle_placa / vehicles_placa
 *   4. Torna vehicle_id NOT NULL nas tabelas dependentes
 */
return new class extends Migration
{
    public function up(): void
    {
        // Validação de segurança — aborta se ainda houver vehicle_id nulo
        $osOrphans = DB::table('order_services')->whereNull('vehicle_id')->count();
        $cmOrphans = DB::table('car_mileages')->whereNull('vehicle_id')->count();

        if ($osOrphans > 0 || $cmOrphans > 0) {
            throw new \RuntimeException(
                "Backfill incompleto! order_services com vehicle_id NULL: {$osOrphans}; " .
                "car_mileages: {$cmOrphans}. " .
                "Rode: php artisan vehicles:migrate-fk-to-id"
            );
        }

        // 1. vehicles: promover id para PRIMARY KEY.
        //    MySQL não permite dropar o UNIQUE de uma coluna AUTO_INCREMENT
        //    em statements separados — o AUTO_INCREMENT precisa sempre estar
        //    vinculado a uma chave. Fazemos tudo em um único ALTER TABLE.
        DB::statement('ALTER TABLE vehicles DROP INDEX `id`, ADD PRIMARY KEY (`id`)');
        DB::statement('ALTER TABLE vehicles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // 2. vehicles: substituir UNIQUE(placa) por UNIQUE(company_id, placa)
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_placa_unique');
            $table->unique(['company_id', 'placa'], 'vehicles_company_id_placa_unique');
        });

        // 3. order_services: remover coluna legada + tornar vehicle_id NOT NULL
        Schema::table('order_services', function (Blueprint $table) {
            if (Schema::hasColumn('order_services', 'vehicle_placa')) {
                $table->dropColumn('vehicle_placa');
            }
        });
        DB::statement('ALTER TABLE order_services MODIFY vehicle_id BIGINT UNSIGNED NOT NULL');

        // 4. car_mileages: idem
        Schema::table('car_mileages', function (Blueprint $table) {
            if (Schema::hasColumn('car_mileages', 'vehicles_placa')) {
                $table->dropColumn('vehicles_placa');
            }
        });
        DB::statement('ALTER TABLE car_mileages MODIFY vehicle_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        // Reversão não é segura sem backup.
        // Restaurar via: /tmp/pre_vehicle_pk_migration_backup.sql
    }
};
