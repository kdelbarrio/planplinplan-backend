<?php

return [

    'jobs' => [

        // Variante “kulturklik-100”
        'kulturklik-100' => [
            'name'        => 'Kulturklik (100 eventos)',
            'source'      => 'Euskadi - Kulturklik',
            'description' => 'Modo upcoming con 2 páginas.',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'upcoming',
                '--max'   => 2,
            ],
        ],

        // Variante “kulturklik-200”
        'kulturklik-200' => [
            'name'        => 'Kulturklik (200 eventos)',
            'source'      => 'Euskadi - Kulturklik',
            'description' => 'Modo upcoming con 4 páginas.',
            'command'     => 'etl:import',
            'params'      => [
                'source'  => 'kulturklik',
                '--mode'  => 'upcoming',
                '--max'   => 4,
            ],
        ],

        'experiencias' => [
            'name'        => 'Experiencias',
            'source'      => 'Turismo Euskadi',
            'description' => 'Importa experiencias y las vuelca como eventos.',
            'command'     => 'etl:import-experiences',
            'params'      => [],
        ],
    ],

];
