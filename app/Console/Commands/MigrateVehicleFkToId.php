<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill de `vehicle_id` em `order_services` e `car_mileages`, a partir do
 * par (company_id, placa) — passo intermediário da migração que troca a PK
 * de `vehicles` de `placa` (string, global) para `id` (autoincrement),
 * permitindo que a mesma placa exista em oficinas (companies) diferentes.
 *
 * PRÉ-REQUISITO: rodar `php artisan migrate` até a migration
 *   2026_06_15_000003_add_vehicle_id_to_car_mileages_table
 * (que cria as colunas `vehicle_id` nullable e a coluna `vehicles.id`).
 *
 * APÓS este comando confirmar 0 registros órfãos, rode novamente
 * `php artisan migrate` para aplicar a migration de cleanup
 *   2026_06_15_000004_drop_legacy_placa_keys_from_vehicles_table
 * que remove as colunas antigas e troca a PK definitivamente.
 *
 * USO:
 *   php artisan vehicles:migrate-fk-to-id --dry-run
 *   php artisan vehicles:migrate-fk-to-id
 */
class MigrateVehicleFkToId extends Command
{
    protected $signature = 'vehicles:migrate-fk-to-id {--dry-run : Apenas relata o que seria feito, sem alterar dados}';

    protected $description = 'Preenche order_services.vehicle_id e car_mileages.vehicle_id a partir de (company_id, placa)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!Schema::hasColumn('vehicles', 'id') || !Schema::hasColumn('order_services', 'vehicle_id') || !Schema::hasColumn('car_mileages', 'vehicle_id')) {
            $this->error('Colunas necessárias não encontradas. Rode "php artisan migrate" até o passo 3 antes de executar este comando.');
            return self::FAILURE;
        }

        $this->info($dryRun ? 'Modo DRY-RUN — nenhuma alteração será gravada.' : 'Executando backfill...');

        // --- order_services -------------------------------------------------
        $osPending = DB::table('order_services')->whereNull('vehicle_id')->count();
        $this->line("order_services pendentes (vehicle_id NULL): {$osPending}");

        if (!$dryRun && $osPending > 0) {
            $updated = DB::update('
                UPDATE order_services os
                JOIN vehicles v
                  ON v.placa = os.vehicle_placa
                 AND v.company_id = os.company_id
                SET os.vehicle_id = v.id
                WHERE os.vehicle_id IS NULL
            ');
            $this->line("order_services atualizados: {$updated}");
        }

        // --- car_mileages ----------------------------------------------------
        $cmPending = DB::table('car_mileages')->whereNull('vehicle_id')->count();
        $this->line("car_mileages pendentes (vehicle_id NULL): {$cmPending}");

        if (!$dryRun && $cmPending > 0) {
            $updated = DB::update('
                UPDATE car_mileages cm
                JOIN vehicles v
                  ON v.placa = cm.vehicles_placa
                 AND v.company_id = cm.company_id
                SET cm.vehicle_id = v.id
                WHERE cm.vehicle_id IS NULL
            ');
            $this->line("car_mileages atualizados: {$updated}");
        }

        // --- validação --------------------------------------------------------
        $osOrphans = DB::table('order_services')->whereNull('vehicle_id')->count();
        $cmOrphans = DB::table('car_mileages')->whereNull('vehicle_id')->count();

        $this->newLine();
        $this->line("order_services com vehicle_id ainda NULL: {$osOrphans}");
        $this->line("car_mileages com vehicle_id ainda NULL: {$cmOrphans}");

        if ($dryRun) {
            $this->comment('Dry-run concluído. Rode sem --dry-run para aplicar.');
            return self::SUCCESS;
        }

        if ($osOrphans > 0 || $cmOrphans > 0) {
            $this->error('Existem registros órfãos (sem vehicle correspondente em vehicles por company_id+placa).');
            $this->error('Investigue antes de rodar a migration de cleanup — ela vai falhar (NOT NULL) se prosseguir assim.');
            $this->dumpOrphanSamples();
            return self::FAILURE;
        }

        $this->info('Backfill concluído com sucesso — todos os registros foram vinculados a um vehicle_id.');
        $this->info('Agora rode: php artisan migrate   (para aplicar a migration de cleanup 2026_06_15_000004_...)');

        return self::SUCCESS;
    }

    private function dumpOrphanSamples(): void
    {
        $osSamples = DB::table('order_services')
            ->whereNull('vehicle_id')
            ->select('id', 'company_id', 'vehicle_placa')
            ->limit(5)
            ->get();

        $cmSamples = DB::table('car_mileages')
            ->whereNull('vehicle_id')
            ->select('id', 'company_id', 'vehicles_placa')
            ->limit(5)
            ->get();

        if ($osSamples->isNotEmpty()) {
            $this->warn('Exemplos órfãos em order_services:');
            $this->table(['id', 'company_id', 'vehicle_placa'], $osSamples->map(fn ($r) => [$r->id, $r->company_id, $r->vehicle_placa])->toArray());
        }

        if ($cmSamples->isNotEmpty()) {
            $this->warn('Exemplos órfãos em car_mileages:');
            $this->table(['id', 'company_id', 'vehicles_placa'], $cmSamples->map(fn ($r) => [$r->id, $r->company_id, $r->vehicles_placa])->toArray());
        }
    }
}
