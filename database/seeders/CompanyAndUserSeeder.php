<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // Empresa
        $companyId = DB::table('companies')->insertGetId([
            'name'         => 'Servcar',
            'fantasy_name' => 'Servcar',
            'cnpj'         => '00000000000000',
            'email'        => 'contato@servcar.com.br',
            'phone'        => '',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->command->info("Empresa criada com ID: {$companyId}");

        // Usuário admin
        $userId = DB::table('users')->insertGetId([
            'name'       => 'Robson Pedreira',
            'email'      => 'masterdba6@gmail.com',
            'password'   => Hash::make('Rm@150917'),
            'admin'      => '1',
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("Usuário admin criado com ID: {$userId}");
        $this->command->info("E-mail: masterdba6@gmail.com / Senha: Rm@150917");
        $this->command->warn("Troque a senha após o primeiro login!");
    }
}
