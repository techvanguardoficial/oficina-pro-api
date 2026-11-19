<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para as consultas mais frequentes (financeiro, vínculo do portal
 * e histórico do veículo). Apenas índices, nenhum dado é alterado.
 */
return new class extends Migration
{
    private array $indexes = [
        'incomes'        => ['incomes_company_date_index'          => ['company_id', 'date']],
        'expenses'       => ['expenses_company_date_index'         => ['company_id', 'date']],
        'clients'        => ['clients_cpf_cnpj_index'              => ['cpf_cnpj'],
                             'clients_email_index'                 => ['email']],
        'phones'         => ['phones_phone_one_index'              => ['phone_one']],
        'order_services' => ['order_services_company_vehicle_index' => ['company_id', 'vehicle_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $name => $columns) {
                    $t->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach (array_keys($indexes) as $name) {
                    $t->dropIndex($name);
                }
            });
        }
    }
};
