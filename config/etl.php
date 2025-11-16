<?php

return [

    'jobs' => [

         // Variante “kulturklik proximos 7 dias”
        'kulturklik-7d' => [
            'name'        => 'Kulturklik (próximos 7 días)',
            'source'      => 'Kulturklik',
            'description' => 'Modo por dias: próximos 7 días',
            'command'     => 'etl:import --mode:days --days=7',
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
            'command'     => 'etl:import --mode:days --days=14',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'days',
                '--days'  => 14,
            ],
        ],

        
        // Variante “kulturklik-100”
        'kulturklik-100' => [
            'name'        => 'Kulturklik (2 páginas próximos eventos)',
            'source'      => 'Euskadi - Kulturklik',
            'description' => 'Modo upcoming, 2 páginas (100 eventos aprox.)',
            'command'     => 'etl:import --mode:upcoming --max=2',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'upcoming',
                '--max'   => 2,
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
