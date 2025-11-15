<?php

return [

    // Tipos de evento a excluir SIEMPRE (por código numérico de Kulturklik)
    'excluded_type_codes' => [
        
        6, // Conferencia
        10, // Jornadas
        11, // Formación
        12, // Concurso
        15 // Otros

    ],

    // Hora límite “kid friendly” para openingHoursEs
    // Si openingHoursEs indica una hora posterior a esta, se descarta el evento
    'latest_kid_friendly_hour' => '20:00',

    // Palabras/expresiones que marcan evento claramente adulto (ES)
    'negative_keywords_es' => [
        'solo adultos',
        'para adultos',
        '18+',
        '18 +',
        'mayores de 18',
        'mayores de 16',
        'humor negro',
        'monólogo',
    ],

    // Versión en euskera 
    'negative_keywords_eu' => [
        'helduentzat',      
        '18+',
        '18 +',
        'nagusi',          
        'publiko heldua',   
    ],

    // Lugares/salas a excluir siempre
    'negative_places' => [
         'pub',
         'bar',
    ],

    // Tipos outdoor para determinar is_indoor (0 = outdoor, 1 = indoor)
    'outdoor_type_codes' => [8, 13, 15, 16], // feria, festival, otro, fiestas
];
