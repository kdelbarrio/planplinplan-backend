<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\EventType;
use App\Models\EventTypeAlias;

class KulturklikEventTypesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ["id"=>"1","nameEs"=>"Concierto","nameEu"=>"Kontzertua"],
            ["id"=>"2","nameEs"=>"Teatro","nameEu"=>"Antzerkia"],
            ["id"=>"3","nameEs"=>"Exposición","nameEu"=>"Erakusketa"],
            ["id"=>"4","nameEs"=>"Danza","nameEu"=>"Dantza"],
            ["id"=>"6","nameEs"=>"Conferencia","nameEu"=>"Hitzaldia"],
            ["id"=>"7","nameEs"=>"Bertsolarismo","nameEu"=>"Bertsolaritza"],
            ["id"=>"8","nameEs"=>"Feria","nameEu"=>"Azoka"],
            ["id"=>"9","nameEs"=>"Cine y audiovisuales","nameEu"=>"Zinema eta ikus-entzunezkoak"],
            ["id"=>"10","nameEs"=>"Eventos/jornadas","nameEu"=>"Ekitaldiak/jardunaldiak"],
            ["id"=>"11","nameEs"=>"Formación","nameEu"=>"Formakuntza"],
            ["id"=>"12","nameEs"=>"Concurso","nameEu"=>"Lehiaketa"],
            ["id"=>"13","nameEs"=>"Festival","nameEu"=>"Jaialdia"],
            ["id"=>"14","nameEs"=>"Actividad Infantil","nameEu"=>"Haur jarduera"],
            ["id"=>"15","nameEs"=>"Otro","nameEu"=>"Bestelakoa"],
            ["id"=>"16","nameEs"=>"Fiestas","nameEu"=>"Jaiak"],
        ];

        foreach ($rows as $r) {
            $slug = Str::slug($r['nameEs']); // p.ej. "cine-y-audiovisuales"
            $etype = EventType::firstOrCreate(
                ['slug' => $slug],
                ['name' => $r['nameEs'], 'is_active' => true]
            );

            EventTypeAlias::updateOrCreate(
                ['source' => 'kulturklik', 'source_code' => (string)$r['id']],
                [
                    'event_type_id' => $etype->id,
                    'source_label'  => $r['nameEs'].' / '.$r['nameEu'],
                    'confidence'    => 100,
                    'is_active'     => true,
                ]
            );
        }
    }
}
