<?php

namespace App\Console\Commands;

use App\Models\EtlError;
use App\Models\EtlRun;
use App\Models\Province;
use App\Services\EventUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Artisan;

class EtlImportKulturklik extends Command
{
    protected $signature = 'etl:import 
        {source=kulturklik : Fuente a importar}
        {--from=1 : Página inicial (_page)}
        {--to= : Página final (por defecto, hasta totalPages)}
        {--max= : Máximo de páginas a importar}
        {--elements=50 : Tamaño de página (_elements)}
        {--timeout=20 : Timeout HTTP en segundos}
        {--retries=3 : Reintentos por petición}
        {--sleep=500 : Backoff base en ms (exponencial)}
        {--mode=60d : Modo: 60d (byMonth hoy→+59), upcoming (endpoint clásico), days (byDate hoy→+N)}
        {--days=7 : Número de días a importar desde hoy en modo days}';

    protected $description = 'Importa eventos desde Kulturklik (Euskadi API) con paginación, retry/backoff y registro en etl_runs/etl_errors';
    protected array $provinceMap = []; // province_id => name_es
    protected array $outdoorTypeCodes = [8,13,15,16]; // feria(8), festival(13), otro(15), fiestas(16)
    // Configurable: tipos excluidos, hora límite y palabras negativas
    protected array $excludedTypeCodes = [];
    protected int $latestKidFriendlyMinutes = 21 * 60;
    protected array $negativeKeywordsEs = [];
    protected array $negativeKeywordsEu = [];
    protected array $negativePlaces = [];
    
    public function handle(EventUpserter $upserter): int
    {
        $this->provinceMap = Province::query()
            ->get()
            ->pluck('name_es', 'province_id')
            ->toArray();
        
        // Cargar configuración de filtros familiares
        $config = config('kulturklik_family', []);

        $this->outdoorTypeCodes = $config['outdoor_type_codes'] ?? $this->outdoorTypeCodes;
        $this->excludedTypeCodes = $config['excluded_type_codes'] ?? [];

        $this->latestKidFriendlyMinutes = $this->parseHourToMinutes(
            $config['latest_kid_friendly_hour'] ?? '21:00'
        );

        $this->negativeKeywordsEs = array_map('mb_strtolower', $config['negative_keywords_es'] ?? []);
        $this->negativeKeywordsEu = array_map('mb_strtolower', $config['negative_keywords_eu'] ?? []);
        $this->negativePlaces = array_map('mb_strtolower', $config['negative_places'] ?? []);


        $source    = (string) $this->argument('source'); // "kulturklik"
        $fromPage  = (int) $this->option('from');
        $toPageOpt = $this->option('to');
        $maxPages  = $this->option('max') ? (int) $this->option('max') : null;
        $elements  = (int) $this->option('elements');
        $timeout   = (int) $this->option('timeout');
        $retries   = (int) $this->option('retries');
        $sleepMs   = (int) $this->option('sleep');
        $mode      = (string) $this->option('mode');

        // Ventana de importación (UTC)
        //$winStart = Carbon::today('UTC');
        //$winEnd   = Carbon::today('UTC')->addDays(59);
       
        $daysOpt = (int) $this->option('days');

        // Ventana de importación (UTC)
        if ($mode === 'days') {
            // Ej: hoy → hoy + (days-1)
            $days = $daysOpt > 0 ? $daysOpt : 7;
            $winStart = Carbon::today('UTC')->startOfDay();
            $winEnd   = $winStart->copy()->addDays($days - 1)->endOfDay();
        } else {
            // Comportamiento actual: 60 días (2 meses aprox.)
            $winStart = Carbon::today('UTC');
            $winEnd   = Carbon::today('UTC')->addDays(59);
        }


        $run = EtlRun::create([
            'source'      => $source,
            'started_at'  => now(),
            'finished_at' => null,
            'total'       => 0,
            'inserted'    => 0,
            'updated'     => 0,
            'errors'      => 0,
        ]);

        try {
            if ($mode === 'upcoming') {
                // ====== MODO UPCOMING  ======
                $baseUrl = rtrim(env('KULTURKLIK_API', 'https://api.euskadi.eus/culture/events/v1.0/events/upcoming'), '/');

                // Primera página para leer totalPages
                $page = max(1, $fromPage);
                $first = $this->fetchPage($baseUrl, $page, $elements, $timeout, $retries, $sleepMs);

                if (!$first['ok']) {
                    $this->logError($run->id, $source, null, $first['body'], $first['error']);
                    $this->error("Fallo en página {$page}: {$first['error']}");
                    return self::FAILURE;
                }

                $payload    = $first['json'];
                $totalPages = (int) Arr::get($payload, 'totalPages', 1);
                $toPage     = $toPageOpt ? (int) $toPageOpt : $totalPages;

                if ($maxPages) {
                    $toPage = min($toPage, $page + $maxPages - 1);
                }

                $this->info("Importando {$source} [upcoming] páginas {$page} → {$toPage} (totalPages={$totalPages}, _elements={$elements})");

                // Pág. 1
                $counts = $this->processItems($payload, $source, $upserter, $winStart, $winEnd);
                $run->total    += $counts['total'];
                $run->inserted += $counts['inserted'];
                $run->updated  += $counts['updated'];
                $run->errors   += $counts['errors'];

                // Pág. 2..N
                for ($p = $page + 1; $p <= $toPage; $p++) {
                    $resp = $this->fetchPage($baseUrl, $p, $elements, $timeout, $retries, $sleepMs);
                    if (!$resp['ok']) {
                        $this->logError($run->id, $source, null, $resp['body'], $resp['error']);
                        $this->warn("Página {$p}: {$resp['error']}");
                        continue;
                    }
                    $counts = $this->processItems($resp['json'], $source, $upserter, $winStart, $winEnd);
                    $run->total    += $counts['total'];
                    $run->inserted += $counts['inserted'];
                    $run->updated  += $counts['updated'];
                    $run->errors   += $counts['errors'];
                }
                
            /*
            } elseif ($mode === 'days') {
             */
            } else {
                // ====== MODO: DÍAS USANDO byDate ======
                $days = (int) $this->option('days') ?: 7;
                if ($days < 1) {
                    $days = 1;
                }

                $this->info(sprintf(
                    "Importando %s [byDate %d días] ventana %s → %s (UTC) (_elements=%d)",
                    $source,
                    $days,
                    $winStart->toDateString(),
                    $winEnd->toDateString(),
                    $elements
                ));

                // Recorremos día a día dentro de la ventana
                $cursor = $winStart->copy();
                while ($cursor->lte($winEnd)) {
                    $y = $cursor->year;
                    $m = $cursor->month;
                    $d = $cursor->day;

                    // Endpoint byDate:
                    // https://api.euskadi.eus/culture/events/v1.0/events/byDate/{Y}/{m}/{d}
                    $baseUrl = sprintf(
                        'https://api.euskadi.eus/culture/events/v1.0/events/byDate/%04d/%02d/%02d',
                        $y,
                        $m,
                        $d
                    );

                    $page  = max(1, $fromPage);
                    $first = $this->fetchPage($baseUrl, $page, $elements, $timeout, $retries, $sleepMs);

                    if (!$first['ok']) {
                        $this->logError($run->id, $source, null, $first['body'], $first['error']);
                        $this->warn(sprintf(
                            'Día %s — fallo en página %d: %s',
                            $cursor->toDateString(),
                            $page,
                            $first['error']
                        ));
                        $cursor->addDay();
                        continue;
                    }

                    $payload    = $first['json'];
                    $totalPages = (int) Arr::get($payload, 'totalPages', 0);
                    $toPage     = $toPageOpt ? (int) $toPageOpt : ($totalPages > 0 ? $totalPages : PHP_INT_MAX);

                    if ($maxPages) {
                        $toPage = min($toPage, $page + $maxPages - 1);
                    }

                    $this->info(sprintf(
                        'Día %s: páginas %d → %s',
                        $cursor->toDateString(),
                        $page,
                        $toPage === PHP_INT_MAX ? '¿?' : $toPage
                    ));

                    // Pág. 1
                    $counts = $this->processItems($payload, $source, $upserter, $winStart, $winEnd);
                    $run->total    += $counts['total'];
                    $run->inserted += $counts['inserted'];
                    $run->updated  += $counts['updated'];
                    $run->errors   += $counts['errors'];

                    // Pág. 2..N
                    $stopByShortPage = ($totalPages === 0);
                    for ($p = $page + 1; $p <= $toPage; $p++) {
                        $resp = $this->fetchPage($baseUrl, $p, $elements, $timeout, $retries, $sleepMs);
                        if (!$resp['ok']) {
                            $this->logError($run->id, $source, null, $resp['body'], $resp['error']);
                            $this->warn(sprintf(
                                'Día %s — página %d: %s',
                                $cursor->toDateString(),
                                $p,
                                $resp['error']
                            ));
                            continue;
                        }

                        $counts = $this->processItems($resp['json'], $source, $upserter, $winStart, $winEnd);
                        $run->total    += $counts['total'];
                        $run->inserted += $counts['inserted'];
                        $run->updated  += $counts['updated'];
                        $run->errors   += $counts['errors'];

                        if ($stopByShortPage) {
                            $itemsCount = count(Arr::get($resp['json'], 'items', []));
                            if ($itemsCount < $elements) {
                                break;
                            }
                        }
                    }

                    $cursor->addDay();
                }
            
            }
  /*          
            } else {
                // ====== MODO: 60 DÍAS USANDO byMonth ======
                $this->info(sprintf(
                    "Importando %s [byMonth 60d] ventana %s → %s (UTC) (_elements=%d)",
                    $source, $winStart->toDateString(), $winEnd->toDateString(), $elements
                ));

                // Meses a cubrir (2–3 normalmente, manejando cambio de año)
                foreach ($this->monthsCovering($winStart, $winEnd) as [$y, $m]) {
                    $baseUrl = sprintf('https://api.euskadi.eus/culture/events/v1.0/events/byMonth/%04d/%02d', $y, $m);

                    // Pide 1ª página para conocer totalPages (si existe); si no, seguimos hasta que una página < elements
                    $page = max(1, $fromPage);
                    $first = $this->fetchPage($baseUrl, $page, $elements, $timeout, $retries, $sleepMs);

                    if (!$first['ok']) {
                        $this->logError($run->id, $source, null, $first['body'], $first['error']);
                        $this->warn("Mes {$y}-{$m} — fallo en página {$page}: {$first['error']}");
                        continue; // pasa al siguiente mes
                    }

                    $payload    = $first['json'];
                    $totalPages = (int) Arr::get($payload, 'totalPages', 0); // algunos endpoints no lo devuelven
                    $toPage     = $toPageOpt ? (int) $toPageOpt : ($totalPages > 0 ? $totalPages : PHP_INT_MAX);

                    if ($maxPages) {
                        $toPage = min($toPage, $page + $maxPages - 1);
                    }

                    $this->info("Mes {$y}-{$m}: páginas {$page} → ".($toPage === PHP_INT_MAX ? '¿?' : $toPage));

                    // Pág. 1
                    $counts = $this->processItems($payload, $source, $upserter, $winStart, $winEnd);
                    $run->total    += $counts['total'];
                    $run->inserted += $counts['inserted'];
                    $run->updated  += $counts['updated'];
                    $run->errors   += $counts['errors'];

                    // Pág. 2..N (si conocemos totalPages, iteramos hasta ahí; si no, cortamos cuando items < elements)
                    $stopByShortPage = ($totalPages === 0);
                    for ($p = $page + 1; $p <= $toPage; $p++) {
                        $resp = $this->fetchPage($baseUrl, $p, $elements, $timeout, $retries, $sleepMs);
                        if (!$resp['ok']) {
                            $this->logError($run->id, $source, null, $resp['body'], $resp['error']);
                            $this->warn("Mes {$y}-{$m} — página {$p}: {$resp['error']}");
                            continue;
                        }

                        $counts = $this->processItems($resp['json'], $source, $upserter, $winStart, $winEnd);
                        $run->total    += $counts['total'];
                        $run->inserted += $counts['inserted'];
                        $run->updated  += $counts['updated'];
                        $run->errors   += $counts['errors'];

                        if ($stopByShortPage) {
                            $items = Arr::get($resp['json'], 'items', []);
                            if (!is_array($items) || count($items) < $elements) {
                                break; // última página alcanzada
                            }
                        }
                    }
                }
            }
*/
            //$this->line("Resumen → total: {$run->total} | inserted: {$run->inserted} | updated: {$run->updated} | errors: {$run->errors}");
            $this->info("Importación completada ✅  total={$run->total} inserted={$run->inserted} updated={$run->updated} errors={$run->errors}");


            Artisan::call('etl:resolve-event-types', [
            '--source' => 'kulturklik',
            '--min-confidence' => 90,
            '--chunk' => 1000,
            ]);
            $this->info(trim(Artisan::output()));
            return self::SUCCESS;

        } finally {
            $run->finished_at = now();
            $run->save();
        }
    }

    /**
     * Calcula los meses [YYYY, MM] que cubren [start, end] (inclusive), en UTC.
     */
    /*
    protected function monthsCovering(Carbon $start, Carbon $end): array
    {
        $out = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $out[] = [$cursor->year, $cursor->month];
            $cursor->addMonthNoOverflow();
        }
        return $out;
    }
    */
    /**
     * GET página: usa _elements y _page, con retry/backoff exponencial.
     * Soporta tanto /upcoming como /byMonth/YYYY/MM (mismo estilo de query string).
     */
    protected function fetchPage(string $baseUrl, int $page, int $elements, int $timeout, int $retries, int $sleepMs): array
    {
        $url = $baseUrl.'?_elements='.$elements.'&_page='.$page;
        $attempt = 0;
        $lastError = null;

        while ($attempt < $retries) {
            $attempt++;
            try {
                $resp = Http::timeout($timeout)->acceptJson()->get($url);

                if ($resp->successful()) {
                    return [
                        'ok'   => true,
                        'json' => $resp->json(),
                        'body' => $resp->body(),
                        'error'=> null,
                    ];
                }

                $lastError = "HTTP {$resp->status()}: ".$resp->body();
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            // backoff exponencial simple: sleepMs, 2x, 4x...
            $sleepCurrent = (int) ($sleepMs * (2 ** ($attempt - 1)));
            usleep($sleepCurrent * 1000);
        }

        return [
            'ok'   => false,
            'json' => null,
            'body' => null,
            'error'=> $lastError ?: 'Unknown error',
        ];
    }

    /**
     * Procesa y upsertea los items de una página. Aplica filtro por ventana [winStart, winEnd] (UTC).
     */
    protected function processItems(array $payload, string $source, EventUpserter $upserter, Carbon $winStart, Carbon $winEnd): array
    {
        $items = Arr::get($payload, 'items', []);
        $inserted = 0; $updated = 0; $errors = 0;

        foreach ($items as $item) {
            try {
                // --- Filtro negativo por TIPO ---
                $typeCode = (int) Arr::get($item, 'type', 0);
                if (in_array($typeCode, $this->excludedTypeCodes, true)) {
                    if ($this->output->isVerbose()) {
                        $this->line(sprintf(
                            '⏭ [%s] %s (tipo %d excluido)',
                            $source,
                            Arr::get($item, 'nameEs') ?? Arr::get($item, 'nameEu'),
                            $typeCode
                        ));
                    }
                    continue;
                }

                // --- Filtro negativo por HORARIO (solo openingHoursEs) ---
                $openingEs = Arr::get($item, 'openingHoursEs');
                if ($this->isTooLateForKids($openingEs)) {
                    if ($this->output->isVerbose()) {
                        $this->line(sprintf(
                            '⏭ [%s] %s (hora %s > límite)',
                            $source,
                            Arr::get($item, 'nameEs') ?? Arr::get($item, 'nameEu'),
                            $openingEs
                        ));
                    }
                    continue;
                }

                // --- Filtro negativo por PALABRAS adultas / lugares ---
                if ($this->hasNegativeSignals($item)) {
                    if ($this->output->isVerbose()) {
                        $this->line(sprintf(
                            '⏭ [%s] %s (descartado por palabras/lugar adulto)',
                            $source,
                            Arr::get($item, 'nameEs') ?? Arr::get($item, 'nameEu')
                        ));
                    }
                    continue;
                }






                // --- Filtro de ventana 60d ---
                $startRaw = Arr::get($item, 'startDate');
                if (!$startRaw) {
                    // Algunos registros podrían no tener startDate; saltamos
                    continue;
                }
                $startUtc = Carbon::parse($startRaw)->utc();
                if ($startUtc->lt($winStart) || $startUtc->gt($winEnd)) {
                    continue;
                }

                $dto = $this->mapKulturklikItemToDto($item, $source);
                //\Log::debug('kulturklik dto', ['id' => $item['id'] ?? null, 'type' => $item['type'] ?? null, 'dto' => $dto]);
                // Asegura last_source_at por si no viene
                if (empty($dto['last_source_at'])) {
                    $dto['last_source_at'] = now()->toIso8601String();
                }

                $event = $upserter->upsert($dto);

                if ($event->import_status === 'new') $inserted++;
                elseif ($event->import_status === 'updated') $updated++;

                $whenTxt = $event->starts_at
                    ? (is_object($event->starts_at) && method_exists($event->starts_at, 'toDateTimeString')
                        ? $event->starts_at->toDateTimeString()
                        : (string)$event->starts_at)
                    : 'sin fecha';

                if ($this->output->isVerbose()) {
                    $this->line(sprintf('✓ [%s] %s (%s) → %s',
                    $source,
                    $event->title_cur ?: $event->title_src,
                    $whenTxt,
                    $event->import_status
                    ));
                }    
            } catch (\Throwable $e) {
                $errors++;
                $externalId = is_array($item) ? (Arr::get($item, 'id') ?? null) : null;
                $this->logError(null, $source, $externalId, $this->excerpt($item), $e->getMessage());
                $this->warn("✗ Error en item {$externalId}: ".$e->getMessage());
            }
        }

        return ['total' => count($items), 'inserted' => $inserted, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Mapea el item de Kulturklik al DTO normalizado que usa el EventUpserter.
     */
    protected function mapKulturklikItemToDto(array $item, string $source): array
    {
        $title = Arr::get($item, 'nameEs') ?? Arr::get($item, 'nameEu');

        $start = Arr::get($item, 'startDate');
        $opening = Arr::get($item, 'openingHoursEs');

        $imageUrl = null;
        $images = Arr::get($item, 'images');
        if (is_array($images) && !empty($images)) {
            $imageUrl = Arr::get($images, '0.imageUrl');
        }

        $provCode = Arr::get($item, 'provinceNoraCode'); // puede venir como string/num
        $provCode = is_null($provCode) ? null : (int)$provCode;
        $territory = $provCode !== null
            ? ($this->provinceMap[$provCode] ?? null)
            : null;

        // Tipo de evento: usar el código numérico para decidir is_indoor
        //$typeCode = (int) Arr::get($item, 'type', 0); // seguro: 0 si no existe
        $typeCode = (int) Arr::get($item, 'type', 0);
        $isIndoor = in_array($typeCode, $this->outdoorTypeCodes, true) ? 0 : 1;
        // Extraer rango de edad si aparece en los textos
        [$ageMin, $ageMax] = $this->extractAgeRangeFromItem($item);

        return [
            'source'        => $source,
            'source_id'     => Arr::get($item, 'id'),
            'checksum'      => null,

            'title'         => $title,
            'description'   => Arr::get($item, 'descriptionEs') ?? Arr::get($item, 'descriptionEu'),
            'starts_at'     => $start,
            'ends_at'       => Arr::get($item, 'endDate'),

            'venue_name'    => Arr::get($item, 'establishmentEs') ?? Arr::get($item, 'establishmentEu'),
            'municipality'  => Arr::get($item, 'municipalityEs') ?? Arr::get($item, 'municipalityEu'),
            'territory'     => $territory,

            'price_min'     => null,
            'price_desc'    => Arr::get($item, 'priceEs') ?? Arr::get($item, 'priceEu'),
            'organizer'     => Arr::get($item, 'sourceNameEs') ?? Arr::get($item, 'sourceNameEu'),

            'source_url'    => Arr::get($item, 'sourceUrlEs') ?? Arr::get($item, 'sourceUrlEu'),
            'image_url'     => $imageUrl,
            'is_canceled'   => false,
            'is_indoor'     => $isIndoor,
            'last_source_at'=> Arr::get($item, 'publicationDate'),

            //  NUEVO: edades si se han detectado
            'age_min'       => $ageMin,
            'age_max'       => $ageMax,

            'type_src'      => Arr::get($item, 'typeEs') ?? Arr::get($item, 'typeEu'),
            //'type_code_src' => (string) Arr::get($item, 'type'),
            'type_code_src' => $typeCode,
            'opening_hours' => $opening,

        ];
    }

    /**
     * Convierte "HH:MM" en minutos desde medianoche.
     */
    protected function parseHourToMinutes(?string $hhmm): int
    {
        if (!$hhmm) {
            return 0;
        }
        if (preg_match('/(\d{1,2}):(\d{2})/', $hhmm, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            return $h * 60 + $min;
        }
        return 0;
    }

    /**
     * Devuelve true si la hora de openingHoursEs es posterior al límite “kid friendly”.
     * Si no hay hora o no se puede parsear, NO aplica el filtro (false).
     */
    protected function isTooLateForKids(?string $openingHoursEs): bool
    {
        if (!$openingHoursEs) {
            return false; // sin hora, no filtramos por esto
        }

        $minutes = $this->parseHourToMinutes($openingHoursEs);
        if ($minutes === 0) {
            return false; // formato raro → no filtramos
        }

        return $minutes > $this->latestKidFriendlyMinutes;
    }

    /**
     * Detecta palabras “adultas” o lugares a evitar en nombre/descr/localización.
     */
    protected function hasNegativeSignals(array $item): bool
    {
        $texts = [
            Arr::get($item, 'nameEs'),
            Arr::get($item, 'nameEu'),
            Arr::get($item, 'descriptionEs'),
            Arr::get($item, 'descriptionEu'),
        ];

        foreach ($texts as $t) {
            if (!is_string($t) || $t === '') {
                continue;
            }

            $lower = mb_strtolower($t);

            foreach ($this->negativeKeywordsEs as $kw) {
                if ($kw !== '' && mb_strpos($lower, $kw) !== false) {
                    return true;
                }
            }

            foreach ($this->negativeKeywordsEu as $kw) {
                if ($kw !== '' && mb_strpos($lower, $kw) !== false) {
                    return true;
                }
            }
        }

        // Lugares “adultos”
        $places = [
            Arr::get($item, 'establishmentEs'),
            Arr::get($item, 'establishmentEu'),
            Arr::get($item, 'municipalityEs'),
            Arr::get($item, 'municipalityEu'),
        ];

        foreach ($places as $p) {
            if (!is_string($p) || $p === '') {
                continue;
            }
            $lower = mb_strtolower($p);
            foreach ($this->negativePlaces as $kw) {
                if ($kw !== '' && mb_strpos($lower, $kw) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extrae un rango de edad (min, max) a partir de textos del item.
     * Si no detecta nada, devuelve [null, null].
     */
    protected function extractAgeRangeFromItem(array $item): array
    {
        $texts = [
            Arr::get($item, 'nameEs'),
            Arr::get($item, 'nameEu'),
            Arr::get($item, 'descriptionEs'),
            Arr::get($item, 'descriptionEu'),
        ];

        $ageMin = null;
        $ageMax = null;

        foreach ($texts as $t) {
            if (!is_string($t) || $t === '') {
                continue;
            }

            // Rango: "3-6 años", "3 a 6 años"
            if (preg_match('/(\d{1,2})\s*(?:-|a|hasta)\s*(\d{1,2})\s*años/iu', $t, $m)) {
                $a = (int) $m[1];
                $b = (int) $m[2];
                $ageMin = min($a, $b);
                $ageMax = max($a, $b);
                return [$ageMin, $ageMax];
            }

            // "a partir de 11 años", "mayores de 11 años"
            if (preg_match('/(?:a partir de|mayores de)\s*(\d{1,2})\s*años/iu', $t, $m)) {
                $ageMin = (int) $m[1];
                $ageMax = null;
                return [$ageMin, $ageMax];
            }
        }

        return [$ageMin, $ageMax];
    }
 


    protected function logError(?int $runId, string $source, ?string $externalId, ?string $payloadExcerpt, string $message): void
    {
        try {
            EtlError::create([
                'etl_run_id'       => $runId,
                'source'           => $source,
                'external_id'      => $externalId,
                'payload_excerpt'  => $payloadExcerpt,
                'error_message'    => $message,
            ]);
        } catch (\Throwable $e) {
            $this->warn('No se pudo registrar EtlError: '.$e->getMessage());
        }
    }

    protected function excerpt($item, int $max = 400): string
    {
        $str = is_string($item) ? $item : json_encode($item, JSON_UNESCAPED_UNICODE);
        if ($str === false) return '[payload no serializable]';
        return mb_strimwidth($str, 0, $max, '…');
    }
}


