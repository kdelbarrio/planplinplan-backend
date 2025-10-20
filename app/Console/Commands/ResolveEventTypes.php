<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EventTypeResolver;

class ResolveEventTypes extends Command
{
    protected $signature = 'etl:resolve-event-types 
        {--source=kulturklik : Fuente a resolver}
        {--min-confidence=90 : Mínimo de confianza del alias}
        {--chunk=1000 : Tamaño de lote para procesar}';

    protected $description = 'Asigna event_type_id a eventos usando event_type_aliases';

    public function handle(EventTypeResolver $resolver): int
    {
        $source = (string) $this->option('source');
        $min    = (int) $this->option('min-confidence');
        $chunk  = (int) $this->option('chunk');

        $res = $resolver->resolveBySourceCode($source, $min, $chunk);
        $this->info("Tipos resueltos: {$res['affected']}");

        return self::SUCCESS;
    }
}
