<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvincesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['province_id' => -2, 'name_es' => 'Online',         'name_eu' => 'Online'],
            ['province_id' =>  1, 'name_es' => 'Araba/Álava',    'name_eu' => 'Araba/Álava'],
            ['province_id' => 48, 'name_es' => 'Bizkaia',        'name_eu' => 'Bizkaia'],
            ['province_id' => 20, 'name_es' => 'Gipuzkoa',       'name_eu' => 'Gipuzkoa'],
            ['province_id' => 31, 'name_es' => 'Navarra',        'name_eu' => 'Nafarroa'],
            ['province_id' => -3, 'name_es' => 'Iparralde',      'name_eu' => 'Iparralde'],
        ];

        foreach ($rows as $r) {
            Province::updateOrCreate(
                ['province_id' => $r['province_id']],
                ['name_es' => $r['name_es'], 'name_eu' => $r['name_eu']]
            );
        }
    }
}

