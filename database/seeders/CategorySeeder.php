<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Motor',                            'description' => 'Pistões, bielas, virabrequim, cabeçote, válvulas, correias'],
            ['name' => 'Distribuição',                     'description' => 'Correia dentada, corrente de distribuição, tensor, bomba d\'água'],
            ['name' => 'Injeção eletrônica',               'description' => 'Bicos injetores, corpo de borboleta, sensor MAP, sensor O2'],
            ['name' => 'Alimentação de combustível',       'description' => 'Bomba de combustível, filtro de combustível, tanque, mangueiras'],
            ['name' => 'Arrefecimento',                    'description' => 'Radiador, bomba d\'água, válvula termostática, mangueiras'],
            ['name' => 'Lubrificação',                     'description' => 'Bomba de óleo, filtro de óleo, cárter, retentores'],
            ['name' => 'Embreagem',                        'description' => 'Disco de embreagem, platô, rolamento de embreagem, cabo'],
            ['name' => 'Câmbio',                           'description' => 'Caixa de câmbio, sincronizadores, garfos, rolamentos'],
            ['name' => 'Transmissão',                      'description' => 'Eixo cardã, junta homocinética, semi-eixo, rolamentos de roda'],
            ['name' => 'Diferencial',                      'description' => 'Coroa, pinhão, satélites, carcaça do diferencial'],
            ['name' => 'Suspensão',                        'description' => 'Amortecedores, molas, bandejas, pivôs, buchas, coxins'],
            ['name' => 'Direção',                          'description' => 'Caixa de direção, terminais, barra de direção, bomba hidráulica'],
            ['name' => 'Freios',                           'description' => 'Pastilhas, discos, lonas, tambores, cilindro mestre, ABS'],
            ['name' => 'Sistema elétrico',                 'description' => 'Bateria, alternador, motor de partida, chicotes, fusíveis'],
            ['name' => 'Ignição',                          'description' => 'Velas, bobina, cabos de vela, distribuidor'],
            ['name' => 'Filtros',                          'description' => 'Filtro de ar, óleo, combustível, cabine'],
            ['name' => 'Escapamento',                      'description' => 'Catalisador, silencioso, tubos, ponteiras, juntas'],
            ['name' => 'Emissões',                         'description' => 'Sonda lambda, válvula EGR, filtro de partículas, cânister'],
            ['name' => 'Ar-condicionado',                  'description' => 'Compressor, condensador, evaporador, filtro secador, válvula de expansão'],
            ['name' => 'Climatização',                     'description' => 'Resistência do ventilador, motor do ventilador, caixa evaporadora'],
            ['name' => 'Iluminação',                       'description' => 'Faróis, lanternas, lâmpadas, relés, chicote de iluminação'],
            ['name' => 'Carroceria',                       'description' => 'Para-choques, capô, portas, paralamas, teto'],
            ['name' => 'Funilaria',                        'description' => 'Chapas, solda, massa, lixa, primer'],
            ['name' => 'Portas e fechaduras',              'description' => 'Maçanetas, travas, dobradiças, limitadores de porta'],
            ['name' => 'Vidros',                           'description' => 'Para-brisa, vidros laterais, traseiros, elevavidros'],
            ['name' => 'Retrovisores',                     'description' => 'Espelhos externos, internos, suportes, motores elétricos'],
            ['name' => 'Interior e acabamento',            'description' => 'Tapetes, revestimentos, frisos, borrachas de vedação'],
            ['name' => 'Painel e instrumentação',          'description' => 'Painel de instrumentos, velocímetro, sensores, módulos'],
            ['name' => 'Segurança automotiva',             'description' => 'Air bags, cinto de segurança, módulo de controle'],
            ['name' => 'Pneus',                            'description' => 'Pneus radiais, pneus de carga, pneu estepe'],
            ['name' => 'Rodas',                            'description' => 'Rodas de liga, rodas de aço, calotas, parafusos'],
            ['name' => 'Limpadores e lavadores',           'description' => 'Palhetas, motor do limpador, bomba do lavador, reservatório'],
            ['name' => 'Engate e reboque',                 'description' => 'Engate, barra de reboque, tomada elétrica 7 vias'],
            ['name' => 'Acessórios automotivos',           'description' => 'Acessórios em geral para veículos'],
            ['name' => 'Som e multimídia',                 'description' => 'Rádio, alto-falantes, amplificadores, central multimídia'],
            ['name' => 'Produtos de limpeza automotiva',   'description' => 'Shampoo, cera, polish, limpa-estofados'],
            ['name' => 'Fluidos e químicos',               'description' => 'Óleos, fluidos de freio, aditivo de arrefecimento, lubrificantes'],
            ['name' => 'Ferramentas automotivas',          'description' => 'Ferramentas e equipamentos para oficina'],
            ['name' => 'Peças para motocicletas',          'description' => 'Peças específicas para motos'],
            ['name' => 'Peças para caminhões e ônibus',    'description' => 'Peças específicas para veículos pesados'],
            ['name' => 'Peças para máquinas agrícolas',    'description' => 'Peças específicas para tratores e máquinas agrícolas'],
            ['name' => 'Fixação e vedação',                'description' => 'Parafusos, porcas, arruelas, juntas, retentores, o\'rings'],
            ['name' => 'Peças de desgaste',                'description' => 'Peças com vida útil definida sujeitas à substituição periódica'],
            ['name' => 'Peças originais',                  'description' => 'Peças genuínas do fabricante do veículo'],
            ['name' => 'Peças paralelas',                  'description' => 'Peças fabricadas por terceiros sem vínculo com a montadora'],
            ['name' => 'Peças recondicionadas',            'description' => 'Peças usadas restauradas a especificações de fábrica'],
            ['name' => 'Peças usadas',                     'description' => 'Peças retiradas de veículos e vendidas no estado'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insertOrIgnore([
                'name'        => $cat['name'],
                'description' => $cat['description'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
