<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'Motor',
            'description' => 'Pistões, bielas, virabrequim, cabeçote, válvulas, correias',
        ]);
        Category::create([
            'name' => 'Transmissão',
            'description' => 'Embreagem, câmbio, eixo, junta homocinética',
        ]);
        Category::create([
            'name' => 'Suspensão e direção',
            'description' => 'Amortecedores, molas, bandejas, pivôs, terminais',
        ]);
        Category::create([
            'name' => 'Freios',
            'description' => 'Pastilhas, discos, lonas, tambores, cilindro mestre',
        ]);
        Category::create([
            'name' => 'Elétrica e ignição',
            'description' => 'Bateria, velas, bobina, cabos de vela, motor de arranque, alternador, chicotes',
        ]);
        Category::create([
            'name' => 'Arrefecimento',
            'description' => 'Radiador, bomba d’água, válvula termostática, mangueiras',
        ]);
        Category::create([
            'name' => 'Escapamento',
            'description' => 'Catalisador, silencioso, tubos, ponteiras',
        ]);
        Category::create([
            'name' => 'Fluidos e lubrificantes',
            'description' => 'Óleos, fluidos e lubrificantes',
        ]);
        Category::create([
            'name' => 'Filtros',
            'description' => 'Filtros diversos',
        ]);
        Category::create([
            'name' => 'Pneus e Rodas',
            'description' => 'Pneus, rodas, calotas, estepe',
        ]);
        Category::create([
            'name' => 'Acessórios e acabamento',
            'description' => 'Espelhos, limpadores, tapetes, maçanetas, películas',
        ]);
    }
}
