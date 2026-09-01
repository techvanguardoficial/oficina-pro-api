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
        // Em banco limpo `placa` ainda é PK; em banco migrado a PK já foi dropada.
        // Verificamos antes de tentar dropar para evitar erro.
        $hasPrimaryKey = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vehicles'
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ")[0]->cnt > 0;

        $hasIdIndex = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vehicles'
              AND INDEX_NAME = 'id'
        ")[0]->cnt > 0;

        $dropParts = [];
        if ($hasPrimaryKey) $dropParts[] = 'DROP PRIMARY KEY';
        if ($hasIdIndex)    $dropParts[] = 'DROP INDEX `id`';
        $dropSql = $dropParts ? implode(', ', $dropParts) . ', ' : '';

        DB::statement("ALTER TABLE vehicles {$dropSql}ADD PRIMARY KEY (`id`)");
        DB::statement('ALTER TABLE vehicles MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // 2. Dropar FKs legadas (placa → vehicles.placa) que impedem remover o índice.
        //    Em banco fresh essas FKs existem; em banco já migrado podem não existir.
        $this->dropForeignIfExists('order_services', 'order_services_vehicle_placa_foreign');
        $this->dropForeignIfExists('car_mileages',   'car_mileages_vehicles_placa_foreign');

        // 3. vehicles: substituir UNIQUE(placa) por UNIQUE(company_id, placa)
        $hasPlacaUnique = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
              AND INDEX_NAME = 'vehicles_placa_unique'
        ")[0]->cnt > 0;

        $hasCompanyPlacaUnique = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
              AND INDEX_NAME = 'vehicles_company_id_placa_unique'
        ")[0]->cnt > 0;

        Schema::table('vehicles', function (Blueprint $table) use ($hasPlacaUnique, $hasCompanyPlacaUnique) {
            if ($hasPlacaUnique)        $table->dropUnique('vehicles_placa_unique');
            if (!$hasCompanyPlacaUnique) $table->unique(['company_id', 'placa'], 'vehicles_company_id_placa_unique');
        });

        // 4. order_services: remover coluna legada + tornar vehicle_id NOT NULL
        Schema::table('order_services', function (Blueprint $table) {
            if (Schema::hasColumn('order_services', 'vehicle_placa')) {
                $table->dropColumn('vehicle_placa');
            }
        });
        DB::statement('ALTER TABLE order_services MODIFY vehicle_id BIGINT UNSIGNED NOT NULL');

        // 5. car_mileages: idem
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
    }

    private function dropForeignIfExists(string $table, string $fkName): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $fkName])[0]->cnt > 0;

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }
};
