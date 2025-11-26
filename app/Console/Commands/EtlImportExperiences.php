<?php

namespace App\Console\Commands;

use App\Models\EtlRun;
use App\Models\EtlError;
use App\Models\EventType;
use App\Models\EventTypeAlias;
use App\Services\EventUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

class EtlImportExperiences extends Command
{
    protected $signature = 'etl:import-experiences
        {--url=https://opendata.euskadi.eus/contenidos/ds_recursos_turisticos/planes_experiencias_euskadi/opendata/planes.json}
        {--timeout=25}
        {--retries=3}
        {--sleep=500 : backoff base en ms}';

    protected $description = 'Importa Experiencias (Turismo Euskadi) y resuelve tipología por alias';

    public function handle(EventUpserter $upserter): int
    {
        $url      = (string) $this->option('url');
        $timeout  = (int) $this->option('timeout');
        $retries  = (int) $this->option('retries');
        $sleepMs  = (int) $this->option('sleep');
        $source   = 'experiencias'; // identificador de esta fuente

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
            $payload = $this->fetchJsonWithRetry($url, $timeout, $retries, $sleepMs);
            if (!is_array($payload)) {
                $this->logError($run->id, $source, null, is_string($payload) ? $payload : '[no json]', 'Respuesta inválida');
                $this->error('No se pudo obtener JSON válido del endpoint');
                return self::FAILURE;
            }

            $items = $payload; // el endpoint devuelve array de objetos
            $inserted = $updated = $errors = 0;
            $total = 0;

            foreach ($items as $item) {
                // Solo niños: "children" === "1"
                if (Arr::get($item, 'children') !== '1') {
                    continue;
                }
                $total++;

                try {
                    // 1) Asegura/crea el alias para esta tipología (templateType)
                    $templateType = trim((string) Arr::get($item, 'templateType', ''));
                    $eventTypeId = $this->ensureAlias($source, $templateType);

                    // 2) Construye DTO para tu EventUpserter
                    $dto = $this->mapItemToDto($item, $source, $templateType);

                    // 3) Upsert idempotente
                    $event = $upserter->upsert($dto);

                    if (($event->wasRecentlyCreated || is_null($event->age_min)) && $event->age_min !== 3) {
                        $event->age_min = 3;
                        $event->save();
                    }

                    if ($event->import_status === 'new')      $inserted++;
                    elseif ($event->import_status === 'updated') $updated++;

                    if ($this->output->isVerbose()) {
                    $this->line(sprintf('✓ [%s] %s → %s',
                        $source,
                        $event->title_cur ?: $event->title_src,
                        $event->import_status
                    ));
                }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->logError($run->id, $source, null, $this->excerpt($item), $e->getMessage());
                    $this->warn('✗ Error item: '.$e->getMessage());
                }
            }

            // 4) Resuelve event_type_id a partir de los alias (solo esta fuente)
            Artisan::call('etl:resolve-event-types', [
                '--source' => $source,
                '--min-confidence' => 90
            ]);
            //$this->info('Resolver de tipos ejecutado: '.trim(Artisan::output()));

            // Actualiza contadores
            $run->total    = $total;
            $run->inserted = $inserted;
            $run->updated  = $updated;
            $run->errors   = $errors;

           // $this->line("Resumen → total: {$total} | inserted: {$inserted} | updated: {$updated} | errors: {$errors}");
           $this->info("Importación completada ✅  total={$total} inserted={$inserted} updated={$updated} errors={$errors}");

           return self::SUCCESS;

        } finally {
            $run->finished_at = now();
            $run->save();
        }
    }

    protected function fetchJsonWithRetry(string $url, int $timeout, int $retries, int $sleepMs)
    {
        $attempt = 0;
        $lastErr = null;
        while ($attempt < $retries) {
            $attempt++;
            try {
                $resp = Http::timeout($timeout)->acceptJson()->get($url);
                if ($resp->successful()) {
                    return $resp->json();
                }
                $lastErr = "HTTP {$resp->status()}: ".$resp->body();
            } catch (\Throwable $e) {
                $lastErr = $e->getMessage();
            }
            usleep((int)($sleepMs * (2 ** ($attempt - 1))) * 1000);
        }
        return $lastErr ?: 'Unknown error';
    }

    /**
     * Crea/actualiza EventType + Alias (source=experiencias, source_code=templateType).
     * Devuelve el event_type_id canónico (por si quieres usarlo).
     */
    protected function ensureAlias(string $source, string $templateType): ?int
    {
        if ($templateType === '') return null;

        $slug = Str::slug($templateType);               // canónico simple por ahora
        $etype = EventType::firstOrCreate(
            ['slug' => $slug],
            ['name' => $templateType, 'is_active' => true]
        );

        $alias = EventTypeAlias::updateOrCreate(
            ['source' => $source, 'source_code' => $templateType],
            [
                'event_type_id' => $etype->id,
                'source_label'  => $templateType,
                'confidence'    => 100,
                'is_active'     => true,
            ]
        );

        return $etype->id;
    }

    /**
     * Mapea un registro de Experiencias → DTO esperado por EventUpserter (solo *_src).
     */
    protected function mapItemToDto(array $item, string $source, string $templateType): array
    {
        // id de fuente: extrae un número largo de cualquiera de las URLs; si no, usa checksum
        $sourceId = $this->extractIdFromUrls($item);
        $title = Arr::get($item, 'documentName', '');
        $desc  = Arr::get($item, 'documentDescription', '');

        // URL de referencia (preferencia: friendlyUrl > webpage > physicalUrl)
        $sourceUrl = Arr::get($item, 'friendlyUrl')
            ?: Arr::get($item, 'webpage')
            ?: Arr::get($item, 'physicalUrl');

        // Municipio / territorio primer valor “inteligente”
        $municipalityRaw = Arr::get($item, 'municipality') ?: Arr::get($item, 'locality');
        $territoryRaw    = Arr::get($item, 'territory');

        $municipality = $this->firstValueSmart((string) $municipalityRaw);
        $territory    = $this->firstValueSmart((string) $territoryRaw);

        // Fechas por defecto (objetos Carbon en UTC)
        //$defaultStartsAt = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
        //$defaultEndsAt   = Carbon::create(2035, 12, 31, 23, 59, 59, 'UTC');
        $defaultStartsAt = '';
        $defaultEndsAt   = '';

        // Imagen: el dataset no trae un campo de imagen directa; le asignams una por defecto
        $imageUrl = '/assets/images/default-experiences.png';

        
        return [
            'source'        => $source,
            'source_id'     => $sourceId,
            'checksum'      => $sourceId ? null : hash('sha256', $title.'|'.$municipality.'|'.$sourceUrl),

            'title'         => $title,
            'description'   => $desc,
            'starts_at'     => $defaultStartsAt,
            'ends_at'       => $defaultEndsAt,

            'venue_name'    => Arr::get($item, 'placename') ?: Arr::get($item, 'address'),
            'municipality'  => $municipality,
            'territory'     => $territory,

            'price_min'     => null,
            'price_desc'    => null,
            'organizer'     => null,

            'source_url'    => $sourceUrl,
            'image_url'     => $imageUrl,
            'is_canceled'   => false,
            'last_source_at'=> now()->toIso8601String(),

            // Tipología de origen: templateType
            'type_src'      => $templateType ?: null,
            'type_code_src' => $templateType ?: null,
        ];
    }

    protected function extractIdFromUrls(array $item): ?string
    {
        $candidates = [
            Arr::get($item, 'dataXML'),
            Arr::get($item, 'physicalUrl'),
            Arr::get($item, 'zipFile'),
            Arr::get($item, 'friendlyUrl'),
        ];
        foreach ($candidates as $u) {
            if (!$u) continue;
            if (preg_match('/(\d{5,})/', $u, $m)) {
                return $m[1]; // primer número largo
            }
        }
        return null;
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

    protected function firstValueSmart(?string $value): ?string

    {
    if (!is_string($value)) return null;
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if ($value === '') return null;

    // Excepción: capturar "San X" como segundo token (p.ej. "Donostia / San Sebastián")
    if (preg_match('/^(\S+)\s*(\/|-|\bde\b)\s*(San(?:\s+\S+))/iu', $value, $m)) {
        $first  = $m[1];
        $sep    = $m[2];
        $second = $m[3];

        $sepFormatted = $sep === '/' ? ' / ' : ($sep === '-' ? ' - ' : ' de ');
        return $first . $sepFormatted . $second;
    }

    // Si el primer token va seguido de separador (/ - de) devolvemos "primero SEP segundo"
    if (preg_match('/^(\S+)\s*(\/|-|\bde\b)\s*(\S+)/iu', $value, $m)) {
        $first  = $m[1];
        $sep    = $m[2];
        $second = $m[3];

        $sepFormatted = $sep === '/' ? ' / ' : ($sep === '-' ? ' - ' : ' de ');
        return $first . $sepFormatted . $second;
    }

    // En el resto de casos, devolver sólo la primera palabra
    $parts = preg_split('/\s+/', $value);
    return $parts[0] ?? $value;
}
}
