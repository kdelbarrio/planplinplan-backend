<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EventUpserter;

class EtlDemoImport extends Command
{
    protected $signature = 'etl:demo-import';
    protected $description = 'Importa 3 eventos de prueba usando EventUpserter (idempotente)';

    public function handle(EventUpserter $upserter): int
    {
        // 3 DTOs estilo Kulturklik → DTO normalizado mínimo que entiende el Upserter
        $dtos = [
            [
                'source'      => 'kulturklik',
                'source_id'   => '2025012313325496',
                'checksum'    => null,
                'title'       => 'BEN MAZUÉ',
                'description' => 'Concierto (demo) con iframe YouTube en origen',
                'starts_at'   => '2025-10-15T20:00:00+00:00', // UTC; puedes ajustar TZ en presentación
                'ends_at'     => '2025-10-15T22:00:00+00:00',
                'venue_name'  => 'Atabal',
                'municipality'=> 'Biarritz',
                'territory'   => 'Lapurdi', // si no lo conoces aún, déjalo null
                'price_min'   => null,
                'price_desc'  => '32 / 50 € (AGOTADO)',
                'organizer'   => 'atabal-biarritz.fr',
                'source_url'  => 'https://www.atabal-biarritz.fr/agenda/concerts/ben-mazue...',
                'image_url'   => 'https://opendata.euskadi.eus/.../52.jpg',
                'is_canceled' => false,
                'last_source_at' => '2025-01-23T00:00:00+00:00',
            ],
            [
                'source'      => 'kulturklik',
                'source_id'   => '2025041510110120',
                'checksum'    => null,
                'title'       => 'DROPKICK + Los Nuevos Hobbies',
                'description' => 'Exquisitez pop desde Escocia (demo)',
                'starts_at'   => '2025-10-15T20:00:00+00:00',
                'ends_at'     => '2025-10-15T22:00:00+00:00',
                'venue_name'  => 'Dabadaba',
                'municipality'=> 'Donostia / San Sebastián',
                'territory'   => 'Gipuzkoa',
                'price_min'   => null,
                'price_desc'  => '12 / 15 €',
                'organizer'   => 'dabadabass.com',
                'source_url'  => 'https://dabadabass.com/eu_ES/event/...',
                'image_url'   => 'https://opendata.euskadi.eus/.../28.jpg',
                'is_canceled' => false,
                'last_source_at' => '2025-09-26T12:30:56+00:00',
            ],
            [
                // Ejemplo SIN source_id → usa checksum (título|fecha|venue)
                'source'      => 'kulturklik',
                'source_id'   => null,
                'checksum'    => sha1('Concierto Demo|2025-11-20|Kafe Antzokia'),
                'title'       => 'Concierto Demo',
                'description' => 'Evento sin source_id, deduplicado por checksum',
                'starts_at'   => '2025-11-20T19:30:00+00:00',
                'ends_at'     => '2025-11-20T21:00:00+00:00',
                'venue_name'  => 'Kafe Antzokia',
                'municipality'=> 'Bilbao',
                'territory'   => 'Bizkaia',
                'price_min'   => 12.50,
                'price_desc'  => '12–20 €',
                'organizer'   => 'antxokia.eus',
                'source_url'  => 'https://example.com/evento-demo',
                'image_url'   => null,
                'is_canceled' => false,
                'last_source_at' => '2025-10-01T10:00:00+00:00',
            ],
        ];

        $inserted = 0; $updated = 0; $unchanged = 0;

        foreach ($dtos as $dto) {
            $event = $upserter->upsert($dto);
            $this->line(sprintf(
                '→ [%s] %s (%s) import_status=%s visible=%s',
                $event->source,
                $event->title_cur ?: $event->title_src,
                $event->starts_at?->toDateTimeString() ?? 'sin fecha',
                $event->import_status,
                $event->visible ? 'true' : 'false'
            ));

            if ($event->import_status === 'new') $inserted++;
            elseif ($event->import_status === 'updated') $updated++;
            else $unchanged++;
        }

        $this->info("Resumen: inserted={$inserted} updated={$updated} unchanged={$unchanged}");

        return self::SUCCESS;
    }
}
