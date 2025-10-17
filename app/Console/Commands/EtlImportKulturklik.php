<?php

namespace App\Console\Commands;

use App\Models\EtlError;
use App\Models\EtlRun;
use App\Models\Province;
use App\Services\EventUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class EtlImportKulturklik extends Command
{
    protected $signature = 'etl:import 
        {source=kulturklik : Fuente a importar}
        {--from=1 : Página inicial (_page)}
        {--to= : Página final (por defecto, hasta totalPages)}
        {--max= : Máximo de páginas a importar}
        {--elements=20 : Tamaño de página (_elements)}
        {--timeout=20 : Timeout HTTP en segundos}
        {--retries=3 : Reintentos por petición}
        {--sleep=500 : Backoff base en ms (exponencial)}';

    protected $description = 'Importa eventos desde Kulturklik (Euskadi API) con paginación, retry/backoff y registro en etl_runs/etl_errors';
    protected array $provinceMap = []; // province_id => name_es

    public function handle(EventUpserter $upserter): int
    {
        $this->provinceMap = Province::query()
            ->get()
            ->pluck('name_es', 'province_id')
            ->toArray();
        $source    = (string) $this->argument('source'); // "kulturklik"
        $fromPage  = (int) $this->option('from');
        $toPageOpt = $this->option('to');
        $maxPages  = $this->option('max') ? (int) $this->option('max') : null;
        $elements  = (int) $this->option('elements');
        $timeout   = (int) $this->option('timeout');
        $retries   = (int) $this->option('retries');
        $sleepMs   = (int) $this->option('sleep');

        // URL oficial (puedes sobreescribirla con .env KULTURKLIK_API)
        $baseUrl = rtrim(env('KULTURKLIK_API', 'https://api.euskadi.eus/culture/events/v1.0/events/upcoming'), '/');

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
            // Primera página: para leer totalPages
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

            $this->info("Importando {$source} páginas {$page} → {$toPage} (totalPages={$totalPages}, _elements={$elements})");

            // Procesa la página inicial
            $counts = $this->processItems($payload, $source, $upserter);
            $run->total    += $counts['total'];
            $run->inserted += $counts['inserted'];
            $run->updated  += $counts['updated'];
            $run->errors   += $counts['errors'];

            // Resto de páginas
            for ($p = $page + 1; $p <= $toPage; $p++) {
                $resp = $this->fetchPage($baseUrl, $p, $elements, $timeout, $retries, $sleepMs);
                if (!$resp['ok']) {
                    $this->logError($run->id, $source, null, $resp['body'], $resp['error']);
                    $this->warn("Página {$p}: {$resp['error']}");
                    continue;
                }

                $counts = $this->processItems($resp['json'], $source, $upserter);
                $run->total    += $counts['total'];
                $run->inserted += $counts['inserted'];
                $run->updated  += $counts['updated'];
                $run->errors   += $counts['errors'];
            }

            $this->line("Resumen → total: {$run->total} | inserted: {$run->inserted} | updated: {$run->updated} | errors: {$run->errors}");
            return self::SUCCESS;

        } finally {
            $run->finished_at = now();
            $run->save();
        }
    }

    /**
     * GET página: usa _elements y _page, con retry/backoff exponencial.
     */
    protected function fetchPage(string $baseUrl, int $page, int $elements, int $timeout, int $retries, int $sleepMs): array
    {
        // Ej.: https://api.euskadi.eus/.../upcoming?_elements=20&_page=1
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
     * Procesa y upsertea los items de una página.
     */
    protected function processItems(array $payload, string $source, EventUpserter $upserter): array
    {
        $items = Arr::get($payload, 'items', []);
        $inserted = 0; $updated = 0; $errors = 0;

        foreach ($items as $item) {
            try {
                $dto = $this->mapKulturklikItemToDto($item, $source);
                $event = $upserter->upsert($dto); // tu servicio ya convierte fechas a Carbon/UTC y respeta *_cur

                if ($event->import_status === 'new') $inserted++;
                elseif ($event->import_status === 'updated') $updated++;

                $whenTxt = $event->starts_at
                    ? (is_object($event->starts_at) && method_exists($event->starts_at, 'toDateTimeString')
                        ? $event->starts_at->toDateTimeString()
                        : (string)$event->starts_at)
                    : 'sin fecha';

                $this->line(sprintf('✓ [%s] %s (%s) → %s',
                    $source,
                    $event->title_cur ?: $event->title_src,
                    $whenTxt,
                    $event->import_status
                ));
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

        // Si llega a medianoche y hay openingHoursEs "HH:mm", combínalo
        $start = Arr::get($item, 'startDate');
        $opening = Arr::get($item, 'openingHoursEs');
        if ($start && str_ends_with($start, 'T00:00:00Z') && $opening && preg_match('/^\d{2}:\d{2}$/', $opening)) {
            $date = substr($start, 0, 10); // YYYY-MM-DD
            $start = "{$date}T{$opening}:00Z";
        }

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
            'last_source_at'=> Arr::get($item, 'publicationDate'),
        ];
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
