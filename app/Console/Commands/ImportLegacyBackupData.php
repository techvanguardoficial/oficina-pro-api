<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa os dados "reais" do backup legado (backup_servcar_07_06_2026.sql)
 * direto do arquivo .sql para o schema multi-tenant atual.
 *
 * USO:
 *   php artisan legacy:import-backup /caminho/para/backup_servcar_07_06_2026.sql --dry-run
 *   php artisan legacy:import-backup /caminho/para/backup_servcar_07_06_2026.sql
 *
 * O comando lê os blocos `INSERT INTO `tabela` (...) VALUES (...), (...);`
 * do arquivo, monta os arrays automaticamente (sem precisar copiar nada à
 * mão) e insere no banco atual:
 *   - adicionando `company_id` (definido em COMPANY_ID) onde for exigido;
 *   - traduzindo `expenses.expense` (texto livre) para `expense_types_id`
 *     via $expenseTextMap (ou criando o tipo "Outros" como fallback);
 *   - traduzindo `car_models_id`, `orders_types_id`, `orders_status_id`
 *     antigos para os ids atuais via os mapas $carModelMap / $orderTypeMap /
 *     $orderStatusMap.
 *
 * IMPORTANTE — leia antes de rodar:
 *
 * O backup que sobrou não tem mais `car_models`, `car_makers`,
 * `orders_types`, `orders_status` (você removeu essas linhas), então os
 * IDs antigos referenciados em `vehicles.car_models_id` e
 * `order_services.{orders_types_id,orders_status_id}` não têm como ser
 * resolvidos automaticamente — é preciso informar o mapeamento
 * "id antigo => id novo" manualmente nos arrays abaixo (consultando o
 * arquivo original ou sua memória de quais modelos/tipos eram).
 *
 * Referência do banco ATUAL (para te ajudar a montar os mapas):
 *   orders_types:  1=ORÇAMENTO, 2=ORDEM DE SERVIÇO
 *   orders_status: 1=Concluído, 2=Em andamento, 3=Cancelado, 4=Aprovado,
 *                  5=Em Espera, 6=Aberto, 7=Aguardando Peça
 *   expense_types: 1=Aluguel, 2=Salários, 3=Fornecedores, 4=Impostos,
 *                  5=Água/Luz/Internet, 6=Manutenção, 7=Combustível,
 *                  8=Material de Escritório, 9=Outros
 *
 * Linhas cujo `car_models_id` / `orders_types_id` / `orders_status_id` não
 * tiverem mapeamento são PULADAS (e reportadas) — assim você pode rodar
 * em etapas, completando os mapas aos poucos.
 */
class ImportLegacyBackupData extends Command
{
    protected $signature = 'legacy:import-backup {file : Caminho do arquivo .sql} {--dry-run : Apenas valida/mostra, não grava nada}';
    protected $description = 'Importa dados do backup_servcar_07_06_2026.sql (lido diretamente do arquivo) para o schema multi-tenant atual';

    /** Empresa de destino de todos os registros importados. */
    private const COMPANY_ID = 1;

    // ------------------------------------------------------------------
    // MAPAS DE FK — preencha "id_antigo => id_novo" (consulte o backup
    // original / sua memória para saber a que modelo/tipo cada id antigo
    // correspondia, e o id atual em car_models / orders_types / orders_status).
    // ------------------------------------------------------------------
    /**
     * Os seeders atuais (CarModelSeeder, OrderTypeSeeder, OrderStatusSeeder)
     * preservam os mesmos IDs do banco legado (confirmado: car_models id 1972
     * = ONIX, 729 = VOYAGE, 303 = CIVIC etc., idênticos ao legado). Por isso
     * os mapas abaixo são identidade (id antigo = id novo). Se encontrar
     * algum id antigo que NÃO exista na tabela atual, sobrescreva aqui com
     * o id correto "id_antigo => id_novo".
     */
    private array $carModelMap = [];

    private array $orderTypeMap = [];

    private array $orderStatusMap = [];

    /** Quando true, ids ausentes do mapa acima caem na identidade (antigo = novo)
     *  desde que existam na tabela de destino; senão, a linha é pulada. */
    private const FK_IDENTITY_FALLBACK = true;

    /**
     * Tradução do texto livre `expenses.expense` (ex.: "suporte", "Aluguel")
     * para o id de `expense_types` no banco atual. Chaves em minúsculo;
     * comparação é case-insensitive. Quem não bater cai no FALLBACK abaixo.
     */
    private array $expenseTextMap = [
        'aluguel'                 => 1,
        'salario'                 => 2,
        'salário'                 => 2,
        'salarios'                => 2,
        'salários'                => 2,
        'fornecedor'              => 3,
        'fornecedores'            => 3,
        'imposto'                 => 4,
        'impostos'                => 4,
        'agua'                    => 5,
        'água'                    => 5,
        'luz'                     => 5,
        'internet'                => 5,
        'manutencao'              => 6,
        'manutenção'              => 6,
        'combustivel'             => 7,
        'combustível'             => 7,
        'material de escritorio'  => 8,
        'material de escritório'  => 8,
    ];

    /** expense_types_id usado quando o texto não casa com nada do mapa acima. */
    private const EXPENSE_FALLBACK_TYPE_ID = 9; // "Outros"

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");
            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $dryRun = (bool) $this->option('dry-run');
        $companyId = self::COMPANY_ID;

        if (!DB::table('companies')->where('id', $companyId)->exists()) {
            $this->error("Company id {$companyId} não existe. Ajuste a constante COMPANY_ID no comando.");
            return self::FAILURE;
        }

        $tables = [
            'clients', 'addresses', 'phones', 'vehicles',
            'order_services', 'services', 'parts', 'car_mileages', 'expenses',
        ];

        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = $this->parseInserts($sql, $table);
            $this->line(sprintf('Lidas do arquivo: %-16s %d linha(s)', $table, count($rows[$table])));
        }

        // Conjuntos de ids válidos nas tabelas de destino, para o fallback de identidade
        $validCarModelIds = DB::table('car_models')->pluck('id')->all();
        $validOrderTypeIds = DB::table('orders_types')->pluck('id')->all();
        $validOrderStatusIds = DB::table('orders_status')->pluck('id')->all();

        // ---- pré-processamento / tradução de FKs ----
        $skipped = [];

        foreach ($rows['vehicles'] as &$row) {
            $old = $row['car_models_id'];
            $new = $this->resolveFk($old, $this->carModelMap, $validCarModelIds);
            if ($new === null) {
                $skipped[] = "vehicles placa={$row['placa']}: sem mapeamento/correspondência para car_models_id antigo={$old}";
                $row = null;
                continue;
            }
            $row['car_models_id'] = $new;
            $row['company_id'] = $companyId;
        }
        unset($row);
        $rows['vehicles'] = array_values(array_filter($rows['vehicles']));

        foreach ($rows['order_services'] as &$row) {
            $newType = $this->resolveFk($row['orders_types_id'], $this->orderTypeMap, $validOrderTypeIds);
            $newStatus = $this->resolveFk($row['orders_status_id'], $this->orderStatusMap, $validOrderStatusIds);
            if ($newType === null) {
                $skipped[] = "order_services id={$row['id']}: sem mapeamento/correspondência para orders_types_id antigo={$row['orders_types_id']}";
                $row = null;
                continue;
            }
            if ($newStatus === null) {
                $skipped[] = "order_services id={$row['id']}: sem mapeamento/correspondência para orders_status_id antigo={$row['orders_status_id']}";
                $row = null;
                continue;
            }
            $row['orders_types_id'] = $newType;
            $row['orders_status_id'] = $newStatus;
            $row['company_id'] = $companyId;
        }
        unset($row);
        $rows['order_services'] = array_values(array_filter($rows['order_services']));

        // expenses: troca coluna `expense` (texto) por `expense_types_id`
        foreach ($rows['expenses'] as &$row) {
            $text = mb_strtolower(trim((string) ($row['expense'] ?? '')));
            $row['expense_types_id'] = $this->expenseTextMap[$text] ?? self::EXPENSE_FALLBACK_TYPE_ID;
            unset($row['expense']);
            $row['company_id'] = $companyId;
        }
        unset($row);

        // company_id simples nas demais
        foreach (['clients'] as $t) {
            foreach ($rows[$t] as &$row) {
                $row['company_id'] = $companyId;
            }
            unset($row);
        }
        foreach (['services', 'parts', 'car_mileages'] as $t) {
            foreach ($rows[$t] as &$row) {
                $row['company_id'] = $companyId;
            }
            unset($row);
        }

        // ---- checagem de colisão de chave ----
        $collisions = [];
        foreach ($rows as $table => $list) {
            $keyCol = $table === 'vehicles' ? 'placa' : 'id';
            foreach ($list as $row) {
                $key = $row[$keyCol] ?? null;
                if ($key !== null && DB::table($table)->where($keyCol, $key)->exists()) {
                    $collisions[] = "{$table}.{$keyCol}={$key} já existe no banco atual";
                }
            }
        }

        if ($skipped) {
            $this->warn('Linhas que serão PULADAS por falta de mapeamento de FK:');
            foreach (array_slice($skipped, 0, 30) as $s) {
                $this->line(" - {$s}");
            }
            if (count($skipped) > 30) {
                $this->line('   ... e mais ' . (count($skipped) - 30) . ' linha(s).');
            }
        }
        if ($collisions) {
            $this->error('Colisões de chave primária detectadas (import abortado):');
            foreach (array_slice($collisions, 0, 30) as $c) {
                $this->line(" - {$c}");
            }
            return self::FAILURE;
        }

        $this->info('Resumo do que seria gravado:');
        foreach ($rows as $table => $list) {
            $this->line(" - {$table}: " . count($list));
        }

        if ($dryRun) {
            $this->info('--dry-run: nada foi gravado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            // respeita a ordem de dependência das FKs
            foreach (['clients', 'addresses', 'phones', 'vehicles', 'order_services', 'services', 'parts', 'car_mileages', 'expenses'] as $table) {
                foreach ($rows[$table] as $row) {
                    DB::table($table)->insert($row);
                }
            }
        });

        $this->info('Importação concluída com sucesso.');
        return self::SUCCESS;
    }

    /**
     * Resolve um id antigo de FK para o id novo:
     *   1) se existir no mapa explícito, usa o valor mapeado;
     *   2) senão, se FK_IDENTITY_FALLBACK estiver ligado e o id antigo
     *      existir na tabela de destino, assume identidade (antigo = novo);
     *   3) senão, retorna null (linha será pulada).
     */
    private function resolveFk(mixed $oldId, array $map, array $validIds): ?int
    {
        if ($oldId === null) {
            return null;
        }
        if (array_key_exists($oldId, $map)) {
            return $map[$oldId];
        }
        if (self::FK_IDENTITY_FALLBACK && in_array($oldId, $validIds, true)) {
            return (int) $oldId;
        }
        return null;
    }

    /**
     * Faz o parse de todos os blocos `INSERT INTO `$table` (cols...) VALUES (...), (...);`
     * do dump e devolve um array de arrays associativos [coluna => valor].
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseInserts(string $sql, string $table): array
    {
        $result = [];

        // Captura cada bloco "INSERT INTO `table` (`c1`, `c2`, ...) VALUES\n(...),(...);"
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s*(.*?);/is';
        if (!preg_match_all($pattern, $sql, $blocks, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($blocks as $block) {
            $columns = array_map(
                fn ($c) => trim($c, " `\t\r\n"),
                explode(',', $block[1])
            );

            foreach ($this->splitTuples($block[2]) as $tuple) {
                $values = $this->parseTuple($tuple);
                if (count($values) !== count($columns)) {
                    // linha mal formada / não capturada corretamente — ignora com segurança
                    continue;
                }
                $result[] = array_combine($columns, $values);
            }
        }

        return $result;
    }

    /**
     * Divide "(...), (...), (...)" respeitando aspas e parênteses aninhados,
     * devolvendo o conteúdo interno de cada tupla (sem os parênteses externos).
     *
     * @return array<int, string>
     */
    private function splitTuples(string $valuesBlob): array
    {
        $tuples = [];
        $depth = 0;
        $inString = false;
        $current = '';
        $len = strlen($valuesBlob);

        for ($i = 0; $i < $len; $i++) {
            $ch = $valuesBlob[$i];

            if ($inString) {
                $current .= $ch;
                if ($ch === '\\') {
                    // mantém o caractere escapado junto, sem mudar estado
                    if ($i + 1 < $len) {
                        $current .= $valuesBlob[++$i];
                    }
                } elseif ($ch === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($ch === "'") {
                $inString = true;
                $current .= $ch;
                continue;
            }

            if ($ch === '(') {
                $depth++;
                if ($depth === 1) {
                    $current = '';
                    continue;
                }
            }

            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $current;
                    continue;
                }
            }

            if ($depth >= 1) {
                $current .= $ch;
            }
        }

        return $tuples;
    }

    /**
     * Faz o parse do conteúdo de uma tupla "1, 'texto', NULL, 10.50" em
     * valores PHP (int|float|string|null), respeitando aspas e escapes SQL.
     *
     * @return array<int, mixed>
     */
    private function parseTuple(string $tuple): array
    {
        $values = [];
        $inString = false;
        $current = '';
        $len = strlen($tuple);

        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];

            if ($inString) {
                if ($ch === '\\' && $i + 1 < $len) {
                    $next = $tuple[$i + 1];
                    $current .= match ($next) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $next,
                    };
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $inString = false;
                    continue;
                }
                $current .= $ch;
                continue;
            }

            if ($ch === "'") {
                $inString = true;
                continue;
            }

            if ($ch === ',') {
                $values[] = $this->castScalar(trim($current));
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        $values[] = $this->castScalar(trim($current));

        return $values;
    }

    private function castScalar(string $raw): mixed
    {
        if ($raw === '' ) {
            return null;
        }
        if (strcasecmp($raw, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }
        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return (float) $raw;
        }
        return $raw;
    }
}
