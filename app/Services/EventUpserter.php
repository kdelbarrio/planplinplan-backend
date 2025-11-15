<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class EventUpserter
{
    /**
     * Inserta/actualiza de forma idempotente un evento a partir de un DTO normalizado.
     * - Busca por (source, source_id) o por (source, checksum) si no hay source_id.
     * - Actualiza SOLO los campos de origen (*_src y metadatos de origen).
     * - NO toca campos curados (*_cur), moderation, visible (salvo autoVisible si sigue pending).
     */
    public function upsert(array $dto): Event
    {
        // 1) Localiza por identidad
        $query = Event::query()->where('source', $dto['source']);

        if (!empty($dto['source_id'])) {
            $query->where('source_id', $dto['source_id']);
        } else {
            $query->where('checksum', $dto['checksum']);
        }

        return DB::transaction(function () use ($query, $dto) {
            /** @var Event|null $event */
            $event = $query->lockForUpdate()->first();

            // Campos de ORIGEN que sí actualizamos
            $toCarbon = function ($v) {
                if (empty($v)) return null;
                // Acepta '2025-10-15T20:00:00+00:00', '2025-10-15 20:00:00', etc.
                return Carbon::parse($v)->utc();
            };
            $srcData = [
                'title_src'        => Arr::get($dto,'title'),
                'description_src'  => Arr::get($dto,'description'),
                'starts_at'        => $toCarbon(Arr::get($dto,'starts_at')),
                'ends_at'          => $toCarbon(Arr::get($dto,'ends_at')),
                'venue_name_src'   => Arr::get($dto,'venue_name'),
                'municipality_src' => Arr::get($dto,'municipality'),
                'territory_src'    => Arr::get($dto,'territory'),
                'price_min_src'    => Arr::get($dto,'price_min'),
                'price_desc_src'   => Arr::get($dto,'price_desc'),
                'organizer_src'    => Arr::get($dto,'organizer'),
                'source_url'       => Arr::get($dto,'source_url'),
                'image_url'        => Arr::get($dto,'image_url'),
                'is_canceled'      => (bool) Arr::get($dto,'is_canceled', false),
                'last_source_at'   => $toCarbon(Arr::get($dto,'last_source_at')),
                'type_src'      => Arr::get($dto,'type_src'),
                'type_code_src' => Arr::get($dto,'type_code_src'),
                'is_indoor'        => (bool) Arr::get($dto,'is_indoor', false),
                'opening_hours' => Arr::get($dto,'opening_hours'), 
            ];

            if (!$event) {
                // INSERT
                $event = new Event;
                $event->forceFill ([
                    'source'        => $dto['source'],
                    'source_id'     => $dto['source_id'] ?? null,
                    'checksum'      => $dto['checksum']   ?? null,
                    'moderation'    => 'pendiente',   // entra pendiente
                    'visible'       => false,
                    'import_status' => 'new',
                ] + $srcData);

                // Regla automática (opcional) si sigue pending
                if ($this->autoVisible($event)) {
                    $event->visible = true;
                }

                $event->save();
                $event->refresh();
            } else {
                // UPDATE — solo *_src
                $changed = false;
                foreach ($srcData as $k => $v) {
                    if ($event->$k !== $v) {
                        $event->$k = $v;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $event->import_status = 'updated';

                    if ($event->moderation === 'pendiente' && $this->autoVisible($event)) {
                        $event->visible = true;
                    }

                    $event->save();
                } else {
                    // Sin cambios efectivos
                    if ($event->import_status !== 'new') {
                        $event->import_status = 'unchanged';
                        $event->save();
                    }
                }
            }

            return $event;
        });
    }

    protected function autoVisible(Event $e): bool
    {
        // visible = true si tiene fecha y municipio/territorio (src o cur) y no está cancelado
        $hasWhere = $e->municipality_cur || $e->territory_cur || $e->municipality_src || $e->territory_src;
        return !empty($e->starts_at) && $hasWhere && !$e->is_canceled;
    }
}