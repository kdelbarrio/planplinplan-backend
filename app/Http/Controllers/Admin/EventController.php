<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending|approved|rejected
        $q      = trim((string)$request->query('q', ''));
        $per    = min(max((int)$request->query('per_page', 20), 1), 100);

        $events = Event::query()
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
            ->orderByDesc('updated_at')
            ->paginate($per)
            ->appends($request->query());

        return view('admin.events.index', compact('events', 'status', 'q'));
    }

    public function edit(Event $event)
    {
        return view('admin.events.edit', compact('event'));
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
            'moderation'       => ['required','in:pending,approved,rejected'],
            'visible'          => ['required','boolean'],
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
        'action' => ['required','in:approve,publish,approve_publish,hide'],
        'ids'    => ['required','array','min:1'],
        'ids.*'  => ['integer','exists:events,id'],
    ]);

    $q = \App\Models\Event::query()->whereIn('id', $data['ids']);

    $updates = [];
    switch ($data['action']) {
        case 'approve':
            $updates = ['moderation' => 'approved'];
            break;
        case 'publish':
            $updates = ['visible' => true];
            break;
        case 'approve_publish':
            $updates = ['moderation' => 'approved', 'visible' => true];
            break;
        case 'hide':
            $updates = ['visible' => false];
            break;
    }

    $affected = 0;
    if (!empty($updates)) {
        $affected = $q->update($updates);
    }

    return redirect()
        ->route('admin.events.index', $request->only('status','q','per_page','page'))
        ->with('ok', "Acción '{$data['action']}' aplicada a {$affected} eventos");
    }

}
