<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Extract INSERT statement from SQL file
        $sqlFile = database_path('/../script_db/clients.sql');

        if (!file_exists($sqlFile)) {
            $this->command->error("SQL file not found: $sqlFile");
            return;
        }

        $sql = file_get_contents($sqlFile);

        // Extract the VALUES part
        if (preg_match('/INSERT INTO `clients`[^V]*VALUES(.*);/s', $sql, $matches)) {
            $valuesString = trim($matches[1]);

            // Remove newlines and extra spaces
            $valuesString = preg_replace('/\s+/', ' ', $valuesString);

            // Remove line breaks after commas
            $valuesString = str_replace("),\n", "), ", $valuesString);
            $valuesString = str_replace("),\r\n", "), ", $valuesString);

            // Execute raw insert
            try {
                $query = "INSERT IGNORE INTO `clients` (`id`, `name`, `lastname`, `email`, `cpf_cnpj`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES $valuesString";
                \DB::statement($query);

                $count = \DB::table('clients')->count();
                $this->command->info("✓ Clientes carregados com sucesso! Total: $count");
            } catch (\Exception $e) {
                $this->command->error("Erro ao inserir clientes: " . $e->getMessage());
                $this->command->error($e->getMessage());
            }
        } else {
            $this->command->error("Não foi possível extrair os dados do SQL");
        }
    }
}
