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
     *  - per_page (int, 10..100)
     *  - include_past (bool, por defecto false → solo futuros)
     */
    public function index(Request $request)
    {
        $q           = trim((string) $request->query('q', ''));
        $territory   = $request->query('territory');
        $municipality= $request->query('municipality');
        $from        = $request->query('from'); // YYYY-MM-DD (local)
        $to          = $request->query('to');   // YYYY-MM-DD (local)
        $includePast = filter_var($request->query('include_past', false), FILTER_VALIDATE_BOOL);
        $perPage     = min(max((int) $request->query('per_page', 20), 1), 100);

        $events = Event::query()
            // públicos por defecto
            ->where('visible', true)
            ->where('is_canceled', false)
            ->when(!$includePast, function ($q) {
            $startLocal = \Illuminate\Support\Carbon::now('Europe/Madrid')->startOfDay()->utc();
            $q->where('ends_at', '>=', $startLocal);
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
            // rango de fechas (inclusive)
            ->when($from, function ($q) use ($from) {
                $q->where('starts_at', '>=', \Illuminate\Support\Carbon::parse($from.' 00:00:00', 'Europe/Madrid')->utc());
            })
            ->when($to, function ($q) use ($to) {
                $q->where('starts_at', '<=', \Illuminate\Support\Carbon::parse($to.' 23:59:59', 'Europe/Madrid')->utc());
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
            })
            ->orderBy('starts_at')
            ->select([
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
            ])
            ->paginate($perPage);

        return EventResource::collection($events);
    }

    /**
     * GET /api/events/{id}
     */
    public function show(Event $event)
    {
        // Para la API pública, puedes bloquear acceso a eventos no visibles:
        abort_unless($event->visible && !$event->is_canceled, 404);

        return new EventResource($event);
    }
}
