<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/events
     * Filtros:
     *  - territory (string, ej. "Bizkaia")
     *  - municipality (string, ej. "Donostia / San Sebastián")
     *  - from (YYYY-MM-DD)
     *  - to (YYYY-MM-DD)
     *  - q (texto: busca en título y descripción cur/src)
     *  - per_page (int, 1..400)
     *  - include_past (bool, por defecto false → solo futuros)
     */
    public function index(Request $request)
    {
        $q            = trim((string) $request->query('q', ''));
        $territory    = $request->query('territory');
        $municipality = $request->query('municipality');
        $from         = $request->query('from'); // YYYY-MM-DD (local)
        $to           = $request->query('to');   // YYYY-MM-DD (local)
        $includePast  = filter_var($request->query('include_past', false), FILTER_VALIDATE_BOOL);

        
        // nuevos filtros
        $typeSrc           = $request->query('type_src'); // texto
        $typeSlug          = $request->query('type_slug') ?? $request->query('typeSlug'); // slug de EventType
        $ageMin            = $request->query('age_min');  // número (int)
        $ageMax            = $request->query('age_max');  // número (int)
        $accessibilityTags = trim((string) $request->query('accessibility_tags', '')); // texto, puede ser CSV
        $isIndoorRaw       = $request->query('is_indoor', null); // bool (true/false) o null
        $isIndoor          = is_null($isIndoorRaw) ? null : filter_var($isIndoorRaw, FILTER_VALIDATE_BOOL);

        // date_first: por defecto true => mostrar primero eventos con fecha
        $dateFirst = filter_var($request->query('date_first', true), FILTER_VALIDATE_BOOL);      


        // Permitir hasta 400 por página (con mínimo 1)
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 400));

        $events = Event::query()
            // públicos por defecto
            ->where('visible', true)
            ->where('is_canceled', false)
            ->when(!$includePast, function ($q) {
                $startLocal = \Illuminate\Support\Carbon::now('Europe/Madrid')->startOfDay()->utc();
                // incluir eventos con ends_at >= hoy OR ends_at IS NULL
                $q->where(function ($qq) use ($startLocal) {
                    $qq->where('ends_at', '>=', $startLocal)
                       ->orWhereNull('ends_at');
                });
            })
            // filtros territoriales
            ->when($territory, function ($q) use ($territory) {
                $q->where(function ($qq) use ($territory) {
                    $qq->where('territory_cur', $territory)
                       ->orWhere('territory_src', $territory);
                });
            })
            ->when($municipality, function ($q) use ($municipality) {
                $q->where(function ($qq) use ($municipality) {
                    $qq->where('municipality_cur', $municipality)
                       ->orWhere('municipality_src', $municipality);
                });
            })
            // rango de fechas (inclusive, timezone local → UTC)
            ->when($from, function ($q) use ($from) {
                $q->where('starts_at', '>=', \Illuminate\Support\Carbon::parse($from.' 00:00:00', 'Europe/Madrid')->utc());
            })
            ->when($to, function ($q) use ($to) {
                $q->where('starts_at', '<=', \Illuminate\Support\Carbon::parse($to.' 23:59:59', 'Europe/Madrid')->utc());
            })

            // filtros nuevos: tipo, edad, accesibilidad, interior/exterior
           ->when($typeSrc, function ($q) use ($typeSrc) {
               $q->where('type_src', $typeSrc);
           })
            ->when($typeSlug, function ($q) use ($typeSlug) {
                $q->whereHas('eventType', function ($qq) use ($typeSlug) {
                     $qq->where('slug', $typeSlug);
                });
              })
           ->when($ageMin !== null && $ageMin !== '', function ($q) use ($ageMin) {
               // filtra eventos con age_min >= ageMin 
               $q->where('age_min', '>=', (int) $ageMin);
           })
           ->when($ageMax !== null && $ageMax !== '', function ($q) use ($ageMax) {
               // filtra eventos con age_max <= ageMax 
               $q->where('age_max', '<=', (int) $ageMax);
           })
           ->when($accessibilityTags === 'a11y', function ($q) {
               $q->whereNotNull('accessibility_tags')
                 ->where('accessibility_tags', '!=', '');
           })
           ->when($accessibilityTags && $accessibilityTags !== 'a11y', function ($q) use ($accessibilityTags) {
               $tags = array_filter(array_map('trim', explode(',', $accessibilityTags)));
               $q->where(function ($qq) use ($tags) {
                   foreach ($tags as $tag) {
                       $qq->orWhere('accessibility_tags', 'like', "%{$tag}%");
                   }
               });
           })
           ->when(!is_null($isIndoor), function ($q) use ($isIndoor) {
               $q->where('is_indoor', $isIndoor);
           })


            // búsqueda de texto en cur/src
            ->when($q, function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('title_cur', 'like', "%{$q}%")
                       ->orWhere('title_src', 'like', "%{$q}%")
                       ->orWhere('description_cur', 'like', "%{$q}%")
                       ->orWhere('description_src', 'like', "%{$q}%")
                       ->orWhere('venue_name_cur', 'like', "%{$q}%")
                       ->orWhere('venue_name_src', 'like', "%{$q}%")
                       ->orWhere('municipality_cur', 'like', "%{$q}%")
                       ->orWhere('municipality_src', 'like', "%{$q}%")
                       ->orWhere('territory_cur', 'like', "%{$q}%")
                       ->orWhere('territory_src', 'like', "%{$q}%");
                });
            });
            //->orderBy('starts_at')

            // Ordenamiento: primero eventos con fecha (starts_at NOT NULL) y por starts_at asc,
            // luego por id desc como fallback; si no queremos orden por fecha usamos id desc.
            if ($dateFirst) {
                // 'starts_at IS NULL ASC' -> NOT NULL (0) antes que NULL (1)
                $events = $events->orderByRaw('starts_at IS NULL ASC')
                                ->orderBy('starts_at', 'ASC')
                                ->orderByDesc('id');
            } else {
                $events = $events->orderByDesc('id');
            }    
            
            // select/fields and paginate
            $events = $events->select([
            //->select([
                'id',
                'title_cur','title_src',
                'description_cur','description_src',
                'starts_at','ends_at',
                'venue_name_cur','venue_name_src',
                'municipality_cur','municipality_src',
                'territory_cur','territory_src',
                'image_url','source_url',
                'visible','is_canceled','moderation',
                'source','source_id','last_source_at',
                'created_at','updated_at',
                'type_src',     // string o enum
                'age_min',        // int
                'age_max',        // int
                'is_indoor',      // bool
                'accessibility_tags',  // json | string
                'opening_hours',  // string
            ])
            ->paginate($perPage);

        return EventResource::collection($events);
    }

    /**
     * GET /api/events/{id}
     */
    public function show(Event $event)
    {
        abort_unless($event->visible && !$event->is_canceled, 404);
        return new EventResource($event);
    }
}
