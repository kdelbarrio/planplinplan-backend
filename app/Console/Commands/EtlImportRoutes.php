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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EtlImportRoutes extends Command
{
    protected $signature = 'etl:import-routes
        {--url=https://opendata.euskadi.eus/contenidos/ds_recursos_turisticos/rutas_paseos_euskadi/opendata/rutas.json}
        {--timeout=25}
        {--retries=3}
        {--sleep=500 : backoff base en ms}';

    protected $description = 'Importa Rutas y paseos por Euskadi (solo children=1) y las normaliza como eventos tipo "Ruta".';

    public function handle(EventUpserter $upserter): int
    {
        $url      = (string) $this->option('url');
        $timeout  = (int) $this->option('timeout');
        $retries  = (int) $this->option('retries');
        $sleepMs  = (int) $this->option('sleep');
        $source   = 'rutas'; // identificador de esta fuente

        $run = EtlRun::create([
            'source'      => $source,
            'started_at'  => now(),
            'finished_at' => null,
            'total'       => 0,
            'inserted'    => 0,
            'updated'     => 0,
            'errors'      => 0,
        ]);

        $this->info('ETL rutas → inicio');

        try {
            $payload = $this->fetchJsonWithRetry($url, $timeout, $retries, $sleepMs);
            if (!is_array($payload)) {
                $this->logError($run->id, $source, null, is_string($payload) ? $payload : '[no json]', 'Respuesta inválida');
                $this->error('No se pudo obtener JSON válido del endpoint de rutas');
                return self::FAILURE;
            }

            // Aseguramos el tipo "Ruta" y su alias para esta fuente
            $this->ensureRutaAlias($source);

            $items    = $payload;
            $inserted = $updated = $errors = 0;
            $total    = 0;

            foreach ($items as $item) {
                // Solo rutas marcadas para familias: children === "1"
                if (Arr::get($item, 'children') !== '1') {
                    continue;
                }
                $total++;

                try {
                    // Enriquecemos con datos del XML (descripcion, municipio, provincia)
                    $dataXmlUrl = Arr::get($item, 'dataXML');
                    $xmlExtra   = $this->extractFromXml($dataXmlUrl, $timeout, $retries, $sleepMs);

                    $dto   = $this->mapItemToDto($item, $source, $xmlExtra);
                    $event = $upserter->upsert($dto);

                    // Todas las rutas son outdoor por defecto (is_indoor = 0)
                    if ($event->is_indoor !== 0) {
                        $event->is_indoor = 0;
                        $event->save();
                    }

                    if ($event->import_status === 'new') {
                        $inserted++;
                    } elseif ($event->import_status === 'updated') {
                        $updated++;
                    }

                    if ($this->output->isVerbose()) {
                        $this->line(sprintf('✓ [rutas] %s → %s',
                            $event->title_cur ?: $event->title_src,
                            $event->import_status
                        ));
                    }

                } catch (\Throwable $e) {
                    $errors++;
                    $this->logError($run->id, $source, null, $this->excerpt($item), $e->getMessage());
                    $this->warn('✗ Error item rutas: '.$e->getMessage());
                }
            }

            // Resolver tipología para esta fuente
            Artisan::call('etl:resolve-event-types', [
                '--source'         => $source,
                '--min-confidence' => 90,
            ]);
            $this->info('Resolver de tipos (rutas) ejecutado: '.trim(Artisan::output()));

            $run->total    = $total;
            $run->inserted = $inserted;
            $run->updated  = $updated;
            $run->errors   = $errors;

            $this->info("ETL rutas → resumen: total={$total} inserted={$inserted} updated={$updated} errors={$errors}");

            return self::SUCCESS;

        } finally {
            $run->finished_at = now();
            $run->save();
        }
    }

    /* ---------- HELPERS COMUNES ---------- */

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
     * Asegura EventType "Ruta" + alias para esta fuente.
     */
    protected function ensureRutaAlias(string $source): ?int
    {
        $label = 'Ruta';
        $slug  = Str::slug($label); // "ruta"

        $etype = EventType::firstOrCreate(
            ['slug' => $slug],
            ['name' => $label, 'is_active' => true]
        );

        EventTypeAlias::updateOrCreate(
            ['source' => $source, 'source_code' => $label],
            [
                'event_type_id' => $etype->id,
                'source_label'  => $label,
                'confidence'    => 100,
                'is_active'     => true,
            ]
        );

        return $etype->id;
    }

    /**
     * Carga el XML de dataXML y extrae descripcion, nombreMunicipio y nombreProvincia.
     */
    protected function extractFromXml(?string $url, int $timeout, int $retries, int $sleepMs): array
    {
        $out = [
            'description'  => null,
            'municipality' => null,
            'territory'    => null,
        ];

        if (!$url) {
            return $out;
        }

        $attempt = 0;
        $lastErr = null;

        while ($attempt < $retries) {
            $attempt++;
            try {
                $resp = Http::timeout($timeout)->get($url);
                if ($resp->successful()) {
                    $xmlString = $resp->body();
                    
                    // Simple y directo: respetamos encoding y convertimos CDATA a texto
                $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

                if ($xml === false) {
                    $lastErr = 'XML inválido';
                } else {
                    // Acceso directo a los nodos dentro de <datosGenerales>
                    if (isset($xml->datosGenerales)) {
                        $dg = $xml->datosGenerales;

                        // descripcion
                        if (isset($dg->descripcion)) {
                            $desc = trim((string) $dg->descripcion);
                            if ($desc !== '') {
                                $out['description'] = $desc;
                            }
                        }

                        // nombreMunicipio
                        if (isset($dg->nombreMunicipio)) {
                            $munRaw = trim((string) $dg->nombreMunicipio);
                            if ($munRaw !== '') {
                                $out['municipality'] = $this->firstValueSmart($munRaw);
                            }
                        }

                        // nombreProvincia
                        if (isset($dg->nombreProvincia)) {
                            $terRaw = trim((string) $dg->nombreProvincia);
                            if ($terRaw !== '') {
                                $out['territory'] = $this->firstValueSmart($terRaw);
                            }
                        }
                    }

                    return $out;
                }

                } else {
                    $lastErr = "HTTP {$resp->status()} al leer XML";
                }
            } catch (\Throwable $e) {
                $lastErr = $e->getMessage();
            }

            usleep((int)($sleepMs * (2 ** ($attempt - 1))) * 1000);
        }

        // Si llega aquí, no ha podido leer el XML; devolvemos datos vacíos
        $this->warn('No se pudo leer dataXML (rutas): '.$lastErr);

        return $out;
    }

    /**
     * Mapea un registro de Rutas → DTO esperado por EventUpserter.
     */
    protected function mapItemToDto(array $item, string $source, array $xmlExtra): array
    {
        $sourceId = $this->extractIdFromUrls($item);
        $title    = Arr::get($item, 'documentName', '');

        // Descripción: preferimos la del XML, si no, la del JSON
        $descXml   = $xmlExtra['description'] ?? null;
        $descJson  = Arr::get($item, 'documentDescription', '');
        $descFinal = $descXml !== null && $descXml !== '' ? $descXml : $descJson;

        // URL de referencia (preferencia: friendlyUrl > webpage > physicalUrl)
        $sourceUrl = Arr::get($item, 'friendlyUrl')
            ?: Arr::get($item, 'webpage')
            ?: Arr::get($item, 'physicalUrl');

        // Municipio y territorio: priorizamos lo que venga del XML
        $municipalityRaw = $xmlExtra['municipality']
            ?? (Arr::get($item, 'municipality') ?: Arr::get($item, 'locality'));
        $territoryRaw    = $xmlExtra['territory']
            ?? Arr::get($item, 'territory');

        $municipality = $this->firstValueSmart((string) $municipalityRaw);
        $territory    = $this->firstValueSmart((string) $territoryRaw);

        // Fechas por defecto (como en experiencias)
        $defaultStartsAt = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
        $defaultEndsAt   = Carbon::create(2035, 12, 31, 23, 59, 59, 'UTC');

        // Punto de inicio / lugar
        $venueName = Arr::get($item, 'routeInitPoint')
            ?: Arr::get($item, 'placename')
            ?: Arr::get($item, 'address');

        // Imagen por defecto
        $imageUrl = config('etl.routes_default_image', '/images/default-route.jpg');

        return [
            'source'        => $source,
            'source_id'     => $sourceId,
            'checksum'      => $sourceId
                ? null
                : hash('sha256', $title.'|'.$municipality.'|'.$sourceUrl),

            'title'         => $title,
            'description'   => $descFinal,
            'starts_at'     => $defaultStartsAt,
            'ends_at'       => $defaultEndsAt,

            'venue_name'    => $venueName,
            'municipality'  => $municipality,
            'territory'     => $territory,

            'price_min'     => null,
            'price_desc'    => null,
            'organizer'     => null,

            'source_url'    => $sourceUrl,
            'image_url'     => $imageUrl,
            'is_canceled'   => false,
            'is_indoor'     => 0,
            'last_source_at'=> now()->toIso8601String(),

            // Tipología fija "Ruta"
            'type_src'      => 'Ruta',
            'type_code_src' => 'Ruta',
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
            $this->warn('No se pudo registrar EtlError (rutas): '.$e->getMessage());
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

        // Misma lógica que en experiencias (manejo de "San", "/", "-"…)
        if (preg_match('/^(\S+)\s*(\/|-|\bde\b)\s*(San(?:\s+\S+))/iu', $value, $m)) {
            $first  = $m[1];
            $sep    = $m[2];
            $second = $m[3];

            $sepFormatted = $sep === '/' ? ' / ' : ($sep === '-' ? ' - ' : ' de ');
            return $first . $sepFormatted . $second;
        }

        if (preg_match('/^(\S+)\s*(\/|-|\bde\b)\s*(\S+)/iu', $value, $m)) {
            $first  = $m[1];
            $sep    = $m[2];
            $second = $m[3];

            $sepFormatted = $sep === '/' ? ' / ' : ($sep === '-' ? ' - ' : ' de ');
            return $first . $sepFormatted . $second;
        }

        $parts = preg_split('/\s+/', $value);
        return $parts[0] ?? $value;
    }
}
