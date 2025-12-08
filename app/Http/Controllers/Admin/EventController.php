<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // pendiente|aprobado|rechazado
        $q      = trim((string)$request->query('q', ''));
        $per    = min(max((int)$request->query('per_page', 20), 1), 100);

        $eventTypeId = $request->filled('event_type_id') ? (int)$request->integer('event_type_id') : null;
        $typeQ       = trim((string)$request->query('type_q', '')); // opcional: buscar por nombre/alias

        // Ordenamiento
        $sortBy = $request->query('sort_by', 'updated_at');
        $sortDir = $request->query('sort_dir', 'desc');

        // Campos permitidos para evitar inyección
        $allowedFields = ['id', 'title_cur', 'starts_at', 'municipality_cur', 'territory_cur', 'moderation', 'visible', 'updated_at'];
        if (!in_array($sortBy, $allowedFields)) {
            $sortBy = 'updated_at';
        }
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $events = Event::query()
            ->with('eventType')
            ->when($status, fn($q2) => $q2->where('moderation', $status))
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('title_cur', 'like', "%{$q}%")
                      ->orWhere('title_src', 'like', "%{$q}%")
                      ->orWhere('municipality_cur', 'like', "%{$q}%")
                      ->orWhere('municipality_src', 'like', "%{$q}%")
                      ->orWhere('territory_cur', 'like', "%{$q}%")
                      ->orWhere('territory_src', 'like', "%{$q}%");
                });
            })
            
            ->when($eventTypeId, fn($qq) => $qq->where('event_type_id', $eventTypeId))
            // opcional: coincidir por nombre de tipo o alias
            ->when($typeQ !== '', function ($qq) use ($typeQ) {
                $needle = "%{$typeQ}%";
                $qq->where(function ($w) use ($needle) {
                    $w->whereHas('eventType', function ($t) use ($needle) {
                        $t->where('name', 'like', $needle)
                          ->orWhereHas('aliases', fn($a) => $a->where('alias', 'like', $needle));
                    });
                });
            })

            //->orderByDesc('updated_at')
            ->orderBy($sortBy, $sortDir)
            ->paginate($per)
            ->appends($request->query());

        // lista para el <select> de tipos
        $types = EventType::orderBy('name')->get(['id','name']);
        
        return view('admin.events.index', compact('events', 'status', 'q', 'types', 'sortBy', 'sortDir'));
    }

    //public function edit(Event $event)
    //{
    //    return view('admin.events.edit', compact('event'));
    //}
    public function edit(Event $event)
    {
        // para mostrar alias del tipo seleccionado
        $event->load('eventType.aliases');
        
        // lista para el <select> de tipos
        $types = EventType::orderBy('name')->get(['id','name']);

        return view('admin.events.edit', compact('event', 'types'));
    }

    public function update(Request $request, Event $event)
    {
        // validación básica
        $data = $request->validate([
            'title_cur'        => ['nullable','string','max:500'],
            'description_cur'  => ['nullable','string'],
            'venue_name_cur'   => ['nullable','string','max:255'],
            'municipality_cur' => ['nullable','string','max:120'],
            'territory_cur'    => ['nullable','string','max:120'],
            'age_min'          => ['nullable','integer','min:0','max:120'],
            'age_max'          => ['nullable','integer','min:0','max:120'],
            'accessibility_tags' => ['nullable','string'], // se parsea abajo a array
            'moderation'       => ['required','in:pendiente,aprobado,rechazado'],
            'is_indoor'          => ['required','boolean'],
            'visible'          => ['required','boolean'],
            'event_type_id'      => ['nullable','exists:event_types,id'],
        ]);

        // Parsear accessibility_tags: "ramp,subtitles" -> ["ramp","subtitles"]
        $tags = $data['accessibility_tags'] ?? null;
        $tagsArray = null;
        if (!is_null($tags)) {
            $tagsArray = array_values(array_filter(array_map(
                fn($s) => trim($s),
                preg_split('/[,\n;]+/', $tags)
            )));
        }

        // Asegura coherencia edad
        if (isset($data['age_min'], $data['age_max']) && $data['age_min'] > $data['age_max']) {
            [$data['age_min'], $data['age_max']] = [$data['age_max'], $data['age_min']];
        }

        // Solo campos CURADOS (no tocar *_src)
        $event->fill([
            'title_cur'        => Arr::get($data, 'title_cur'),
            'description_cur'  => Arr::get($data, 'description_cur'),
            'venue_name_cur'   => Arr::get($data, 'venue_name_cur'),
            'municipality_cur' => Arr::get($data, 'municipality_cur'),
            'territory_cur'    => Arr::get($data, 'territory_cur'),
            'age_min'          => Arr::get($data, 'age_min'),
            'age_max'          => Arr::get($data, 'age_max'),
            'accessibility_tags' => $tagsArray,
            'moderation'       => $data['moderation'],
            'is_indoor'          => (bool)$data['is_indoor'],
            'visible'          => (bool)$data['visible'],
        ])->save();

        return redirect()
            ->route('admin.events.edit', $event)
            ->with('ok', 'Evento actualizado');
    }

    public function toggleVisible(Request $request, Event $event)
    {
        $event->visible = !$event->visible;
        $event->save();

        return back()->with('ok', 'Visibilidad actualizada');
    }
    
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer', 'exists:events,id'],
            'action' => ['required', 'in:approve,publish,approve_publish,hide,delete'],
        ]);

        $ids = $data['ids'];
        $action = $data['action'];

        $events = Event::whereIn('id', $ids)->get();

        match ($action) {
            'approve' => $events->each(fn($e) => $e->update(['moderation' => 'aprobado'])),
            'publish' => $events->each(fn($e) => $e->update(['visible' => true])),
            'approve_publish' => $events->each(fn($e) => $e->update(['moderation' => 'aprobado', 'visible' => true])),
            'hide' => $events->each(fn($e) => $e->update(['visible' => false])),
            'delete' => $events->each(fn($e) => $e->delete()),
        };

        return back()
            ->with('success', sprintf('%d evento(s) %s correctamente.', 
                count($ids), 
                match($action) {
                    'approve' => 'aprobado(s)',
                    'publish' => 'publicado(s)',
                    'approve_publish' => 'aprobado(s) y publicado(s)',
                    'hide' => 'ocultado(s)',
                    'delete' => 'eliminado(s)',
                }
            ));
    }

}
