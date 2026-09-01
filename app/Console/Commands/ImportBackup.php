<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa dados do backup do sistema antigo (single-tenant) para o novo schema (multi-tenant).
 *
 * Uso:
 *   php artisan import:backup {company_id}
 *
 * Exemplo:
 *   php artisan import:backup 1
 */
class ImportBackup extends Command
{
    protected $signature   = 'import:backup {company_id : ID da empresa destino} {--file= : Caminho do arquivo SQL (padrão: backup_servcar_31_08_2026.sql)}';
    protected $description = 'Migra dados do backup do sistema antigo para o novo schema multi-tenant';

    private int    $companyId;
    private string $sqlFile;

    // Mapa placa (string) → novo vehicle_id (int), preenchido ao importar veículos
    private array $placaToVehicleId = [];

    public function handle(): int
    {
        $this->companyId = (int) $this->argument('company_id');
        $this->sqlFile   = $this->option('file')
            ?? base_path('backup_servcar_31_08_2026.sql');

        if (!file_exists($this->sqlFile)) {
            $this->error("Arquivo não encontrado: {$this->sqlFile}");
            return 1;
        }

        $this->info("Importando backup para company_id={$this->companyId}");
        $this->info("Arquivo: {$this->sqlFile}");
        $this->newLine();

        $sql = file_get_contents($this->sqlFile);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('SET unique_checks=0');

        try {
            $this->importCarMakers($sql);
            $this->importCarModels($sql);
            $this->importClients($sql);
            $this->importAddresses($sql);
            $this->importPhones($sql);
            $this->importVehicles($sql);
            $this->importOrderServices($sql);
            $this->importParts($sql);
            $this->importServices($sql);
            $this->importCarMileages($sql);
            $this->importExpenses($sql);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET unique_checks=1');
        }

        $this->newLine();
        $this->info('✓ Importação concluída!');
        return 0;
    }

    // -------------------------------------------------------------------------
    // Car Makers & Models (tabelas de referência — sem company_id)
    // -------------------------------------------------------------------------

    private function importCarMakers(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'car_makers',
            ['id', 'manufacturer', 'created_at', 'updated_at', 'deleted_at']);

        $this->batchInsertIgnore('car_makers', $rows, 'car_makers');
    }

    private function importCarModels(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'car_models',
            ['id', 'model', 'created_at', 'updated_at', 'deleted_at', 'car_makers_id']);

        $this->batchInsertIgnore('car_models', $rows, 'car_models');
    }

    // -------------------------------------------------------------------------
    // Clients
    // -------------------------------------------------------------------------

    private function importClients(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'clients',
            ['id', 'name', 'lastname', 'email', 'cpf_cnpj', 'status', 'created_at', 'updated_at', 'deleted_at']);

        $transformed = array_map(fn($r) => array_merge($r, [
            'company_id' => $this->companyId,
        ]), $rows);

        $this->batchInsertIgnore('clients', $transformed, 'clients');
    }

    // -------------------------------------------------------------------------
    // Addresses (sem company_id no novo schema)
    // -------------------------------------------------------------------------

    private function importAddresses(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'addresses',
            ['id', 'address', 'number', 'complement', 'zipcode', 'district', 'city', 'uf', 'clients_id', 'created_at', 'updated_at', 'deleted_at']);

        $this->batchInsertIgnore('addresses', $rows, 'addresses');
    }

    // -------------------------------------------------------------------------
    // Phones (sem company_id no novo schema)
    // -------------------------------------------------------------------------

    private function importPhones(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'phones',
            ['id', 'phone_one', 'phone_two', 'phone_three', 'created_at', 'updated_at', 'deleted_at', 'clients_id']);

        $this->batchInsertIgnore('phones', $rows, 'phones');
    }

    // -------------------------------------------------------------------------
    // Vehicles — PK antiga era `placa` (string); nova é `id` (bigint autoincrement)
    // -------------------------------------------------------------------------

    private function importVehicles(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'vehicles',
            ['placa', 'info', 'chassis', 'year', 'color', 'created_at', 'km', 'updated_at', 'deleted_at', 'car_models_id', 'clients_id']);

        $total = count($rows);
        $this->info("Importando veículos ({$total})...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $placa = $row['placa'];

            // Pula se já existe nesta empresa
            $existing = DB::table('vehicles')
                ->where('company_id', $this->companyId)
                ->where('placa', $placa)
                ->value('id');

            if ($existing) {
                $this->placaToVehicleId[$placa] = $existing;
                $skipped++;
                $bar->advance();
                continue;
            }

            $newId = DB::table('vehicles')->insertGetId([
                'placa'        => $placa,
                'info'         => $row['info'],
                'chassis'      => $row['chassis'],
                'year'         => $row['year'],
                'color'        => $row['color'],
                'km'           => $row['km'] ?? null,
                'car_models_id'=> $row['car_models_id'],
                'clients_id'   => $row['clients_id'],
                'company_id'   => $this->companyId,
                'created_at'   => $row['created_at'],
                'updated_at'   => $row['updated_at'],
                'deleted_at'   => $row['deleted_at'] ?? null,
            ]);

            $this->placaToVehicleId[$placa] = $newId;
            $inserted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Veículos: {$inserted} inseridos, {$skipped} já existiam.");
    }

    // -------------------------------------------------------------------------
    // Order Services — vehicle_placa → vehicle_id
    // -------------------------------------------------------------------------

    // Mapa de status do sistema antigo → novo
    private array $statusMap = [1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
    // Mapa de types do sistema antigo → novo (antigo: 1=ORÇAMENTO, 2=OS; novo: 2=ORÇAMENTO, 3=OS)
    private array $typeMap   = [1 => 2, 2 => 3];

    private function importOrderServices(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'order_services',
            ['id', 'info', 'created_at', 'updated_at', 'deleted_at', 'vehicle_placa', 'orders_types_id', 'orders_status_id']);

        $total = count($rows);
        $this->info("Importando ordens de serviço ({$total})...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0; $skipped = 0; $orphan = 0;
        $batch = [];

        foreach ($rows as $row) {
            $placa     = $row['vehicle_placa'];
            $vehicleId = $this->placaToVehicleId[$placa] ?? null;

            if (!$vehicleId) {
                $orphan++;
                $bar->advance();
                continue;
            }

            // Verifica se já existe
            if (DB::table('order_services')->where('id', $row['id'])->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $batch[] = [
                'id'               => $row['id'],
                'info'             => $row['info'],
                'vehicle_id'       => $vehicleId,
                'company_id'       => $this->companyId,
                'orders_types_id'  => $this->typeMap[$row['orders_types_id']]   ?? $row['orders_types_id'],
                'orders_status_id' => $this->statusMap[$row['orders_status_id']] ?? $row['orders_status_id'],
                'created_at'       => $row['created_at'],
                'updated_at'       => $row['updated_at'],
                'deleted_at'       => $row['deleted_at'] ?? null,
            ];
            $inserted++;
            $bar->advance();

            if (count($batch) >= 500) {
                DB::table('order_services')->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('order_services')->insert($batch);
        }

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ OS: {$inserted} inseridas, {$skipped} já existiam, {$orphan} sem veículo correspondente.");
    }

    // -------------------------------------------------------------------------
    // Parts — remove coluna `price` do schema antigo
    // -------------------------------------------------------------------------

    private function importParts(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'parts',
            ['id', 'description', 'price', 'information', 'quantity', 'unit_price', 'created_at', 'updated_at', 'deleted_at', 'orders_id']);

        $transformed = array_map(fn($r) => [
            'id'          => $r['id'],
            'description' => $r['description'],
            'information' => $r['information'],
            'quantity'    => $r['quantity'],
            'unit_price'  => $r['unit_price'],
            'orders_id'   => $r['orders_id'],
            'company_id'  => $this->companyId,
            'created_at'  => $r['created_at'],
            'updated_at'  => $r['updated_at'],
            'deleted_at'  => $r['deleted_at'] ?? null,
        ], $rows);

        $this->batchInsertIgnore('parts', $transformed, 'parts');
    }

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    private function importServices(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'services',
            ['id', 'description', 'price', 'created_at', 'updated_at', 'deleted_at', 'orders_id', 'information']);

        $transformed = array_map(fn($r) => [
            'id'          => $r['id'],
            'description' => $r['description'],
            'price'       => $r['price'],
            'information' => $r['information'],
            'orders_id'   => $r['orders_id'],
            'company_id'  => $this->companyId,
            'created_at'  => $r['created_at'],
            'updated_at'  => $r['updated_at'],
            'deleted_at'  => $r['deleted_at'] ?? null,
        ], $rows);

        $this->batchInsertIgnore('services', $transformed, 'services');
    }

    // -------------------------------------------------------------------------
    // Car Mileages — vehicles_placa → vehicle_id
    // -------------------------------------------------------------------------

    private function importCarMileages(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'car_mileages',
            ['id', 'mileage', 'created_at', 'updated_at', 'vehicles_placa', 'order_services_id']);

        $transformed = [];
        $orphan = 0;

        foreach ($rows as $row) {
            $placa     = $row['vehicles_placa'];
            $vehicleId = $this->placaToVehicleId[$placa] ?? null;

            if (!$vehicleId) {
                $orphan++;
                continue;
            }

            $transformed[] = [
                'id'                => $row['id'],
                'mileage'           => $row['mileage'],
                'vehicle_id'        => $vehicleId,
                'order_services_id' => $row['order_services_id'],
                'company_id'        => $this->companyId,
                'created_at'        => $row['created_at'],
                'updated_at'        => $row['updated_at'],
            ];
        }

        $this->batchInsertIgnore('car_mileages', $transformed, 'car_mileages');
        if ($orphan > 0) {
            $this->warn("  ⚠ car_mileages: {$orphan} registros sem veículo correspondente ignorados.");
        }
    }

    // -------------------------------------------------------------------------
    // Expenses — schema antigo: expense(nome), info, date, value
    //            schema novo:   expense_types_id, info, date, value, company_id
    // -------------------------------------------------------------------------

    private function importExpenses(string $sql): void
    {
        $rows = $this->parseInserts($sql, 'expenses',
            ['id', 'expense', 'info', 'date', 'value', 'created_at', 'updated_at']);

        // Tenta usar o tipo "Outros" (último da lista do seeder)
        $defaultTypeId = DB::table('expense_types')->where('type', 'like', '%Outros%')->value('id') ?? 1;

        $transformed = array_map(fn($r) => [
            'id'               => $r['id'],
            'expense_types_id' => $defaultTypeId,
            // Preserva o nome antigo concatenado na descrição
            'info'             => trim(($r['expense'] ? "[{$r['expense']}] " : '') . ($r['info'] ?? '')),
            'date'             => $r['date'],
            'value'            => $r['value'],
            'company_id'       => $this->companyId,
            'created_at'       => $r['created_at'],
            'updated_at'       => $r['updated_at'],
        ], $rows);

        $this->batchInsertIgnore('expenses', $transformed, 'expenses');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Parseia os blocos INSERT INTO `table` (...) VALUES (...); do SQL dump.
     * Retorna array de arrays associativos coluna => valor.
     */
    private function parseInserts(string $sql, string $table, array $columns): array
    {
        $rows    = [];
        $escaped = preg_quote($table, '/');

        // Captura cada bloco INSERT completo
        preg_match_all(
            "/INSERT INTO `{$escaped}` \([^)]+\) VALUES\s*([\s\S]+?);/",
            $sql,
            $matches
        );

        if (empty($matches[1])) {
            $this->warn("Nenhum dado encontrado para tabela: {$table}");
            return [];
        }

        foreach ($matches[1] as $valuesBlock) {
            // Divide por linha — cada linha é uma tupla (val1, val2, ...),
            $lines = explode("\n", trim($valuesBlock));

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line === ';') continue;

                // Remove vírgula/ponto-vírgula do final da linha
                $line = rtrim($line, ',;');

                $values = $this->parseTuple($line);

                if (count($values) !== count($columns)) {
                    // Fallback: tenta reconstruir colunas disponíveis
                    $count = min(count($values), count($columns));
                    $row = array_combine(array_slice($columns, 0, $count), array_slice($values, 0, $count));
                } else {
                    $row = array_combine($columns, $values);
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parseia uma tupla SQL: (val1, 'str val', NULL, 123.45)
     * Retorna array de valores PHP.
     */
    private function parseTuple(string $line): array
    {
        // Remove parênteses externos
        $line = preg_replace('/^\s*\(|\)\s*$/', '', $line);

        $values  = [];
        $current = '';
        $inStr   = false;
        $escape  = false;
        $len     = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];

            if ($escape) {
                $current .= $ch;
                $escape   = false;
                continue;
            }

            if ($ch === '\\' && $inStr) {
                $escape = true;
                $current .= $ch;
                continue;
            }

            if ($ch === "'" && !$inStr) {
                $inStr = true;
                continue;
            }

            if ($ch === "'" && $inStr) {
                // Verifica se é aspas duplas (escape MySQL: '')
                if ($i + 1 < $len && $line[$i + 1] === "'") {
                    $current .= "'";
                    $i++;
                    continue;
                }
                $inStr = false;
                continue;
            }

            if ($ch === ',' && !$inStr) {
                $values[] = $this->castValue($current);
                $current  = '';
                continue;
            }

            $current .= $ch;
        }

        if ($current !== '' || !$inStr) {
            $values[] = $this->castValue($current);
        }

        return $values;
    }

    private function castValue(string $raw): mixed
    {
        $raw = trim($raw);

        if (strtoupper($raw) === 'NULL') return null;
        if (is_numeric($raw))            return $raw + 0; // int ou float

        // Remove escapes MySQL restantes
        return stripslashes($raw);
    }

    /**
     * Insere em lotes de 500, ignorando duplicatas (INSERT IGNORE).
     */
    private function batchInsertIgnore(string $table, array $rows, string $label): void
    {
        $total = count($rows);
        $this->info("Importando {$label} ({$total})...");

        if ($total === 0) {
            $this->warn("  (sem registros)");
            return;
        }

        $chunks   = array_chunk($rows, 500);
        $inserted = 0;

        foreach ($chunks as $chunk) {
            // INSERT IGNORE ignora duplicatas de PK
            $cols        = array_keys($chunk[0]);
            $colList     = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $rowPlaceholders = implode(', ', array_fill(0, count($chunk), "({$placeholders})"));

            $bindings = [];
            foreach ($chunk as $row) {
                foreach ($row as $val) {
                    $bindings[] = $val;
                }
            }

            DB::statement("INSERT IGNORE INTO `{$table}` ({$colList}) VALUES {$rowPlaceholders}", $bindings);
            $inserted += count($chunk);
        }

        $this->info("  ✓ {$label}: {$inserted} registros processados.");
    }
}
