<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTypeAlias;

class EventTypeResolver
{
    /**
     * Asigna event_type_id a eventos con event_type_id NULL y type_code_src presente,
     * usando event_type_aliases(source, source_code) activos y con confidence >= $minConfidence.
     * Devuelve [affected => N].
     */
    public function resolveBySourceCode(string $source = 'kulturklik', int $minConfidence = 90, int $chunk = 1000): array
    {
        // Cachea alias activos por source_code => event_type_id
        $aliasMap = EventTypeAlias::query()
            ->where('source', $source)
            ->where('is_active', true)
            ->where('confidence', '>=', $minConfidence)
            ->pluck('event_type_id', 'source_code')   // ['1' => 5, '2' => 7, ...]
            ->toArray();

        if (empty($aliasMap)) {
            return ['affected' => 0];
        }

        $affected = 0;

        Event::query()
            ->whereNull('event_type_id')
            ->whereNotNull('type_code_src')
            ->where('source', $source) // solo Kulturklik
            ->select('id','type_code_src','event_type_id')
            ->orderBy('id')
            ->chunkById($chunk, function ($events) use ($aliasMap, &$affected) {
                foreach ($events as $e) {
                    $code = (string) $e->type_code_src;
                    if (isset($aliasMap[$code])) {
                        Event::whereKey($e->id)->update(['event_type_id' => $aliasMap[$code]]);
                        $affected++;
                    }
                }
            });

        return ['affected' => $affected];
    }
}
