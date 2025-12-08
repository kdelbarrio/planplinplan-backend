<?php

return [

    'jobs' => [

         // Variante “kulturklik proximo 1 dia”
        'kulturklik-1d' => [
            'name'        => 'Kulturklik (próximo día)',
            'source'      => 'Kulturklik',
            'description' => 'Importa los eventos culturales del próximo día',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'days',
                '--days'  => 1,
            ],
        ],
        // Variante “kulturklik proximo 2 dias”
        /*
        'kulturklik-2d' => [
            'name'        => 'Kulturklik (próximos 2 día)',
            'source'      => 'Kulturklik',
            'description' => 'Modo por dias: próximos 2 días',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'days',
                '--days'  => 2,
            ],
        ],
        // Variante “kulturklik proximos 7 dias”
        'kulturklik-7d' => [
            'name'        => 'Kulturklik (próximos 7 días)',
            'source'      => 'Kulturklik',
            'description' => 'Modo por dias: próximos 7 días',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'days',
                '--days'  => 7,
            ],
        ],

        // Variante “kulturklik proximos 14 dias”
        'kulturklik-14d' => [
            'name'        => 'Kulturklik (próximos 14 días)',
            'source'      => 'Kulturklik',
            'description' => 'Modo por dias: próximos 14 días',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'days',
                '--days'  => 14,
            ],
        ],
        */
        
        // Variante “kulturklik-60d”
        'kulturklik-30d' => [
            'name'        => 'Kulturklik (30 dias)',
            'source'      => 'Euskadi - Kulturklik',
            'description' => 'Importa los eventos culturales de los próximos 30 dias',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => '30d',
            ],
        ],


        'experiencias' => [
            'name'        => 'Experiencias',
            'source'      => 'Turismo Euskadi',
            'description' => 'Importa experiencias y las vuelca como eventos.',
            'command'     => 'etl:import-experiences',
            'params'      => [],
        ],

            'rutas' => [
            'name'        => 'Importar Rutas y paseos',
            'source'      => 'Turismo Euskadi',
            'description' => 'Importa rutas y paseos (solo children=1), las marca como tipo "Ruta" y outdoor.',
            'command'     => 'etl:import-routes',
            'params'      => [],
        ],
    ],

];
