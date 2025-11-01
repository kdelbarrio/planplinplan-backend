<x-app-layout>
    <x-slot name="header">
      <h1 class="font-semibold text-xl text-gray-800 leading-tight">API de Eventos - Documentación</h1>
      <p class="mt-2 text-sm text-gray-600">
        Última actualización:
        <time datetime="{{ $lastUpdated->toIso8601String() }}">{{ $lastUpdated->format('d-m-Y H:i') }} (Europe/Madrid)</time>
      </p>
      <div class="mt-4 flex items-center gap-2 text-sm">
        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 font-medium text-indigo-800">
          Base URL: <span class="ml-1 font-mono">{{ $baseApiUrl }}</span>
        </span>
      </div>
    </x-slot>
<div class="py-12">
  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

    {{-- Introducción --}}
    <section class="mb-10">
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-xl font-semibold">Formato</h2>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-gray-700">
          <li><strong>Formato:</strong> JSON</li>
          <li><strong>Charset:</strong> UTF-8</li>
          <li><strong>Fechas:</strong> ISO-8601 UTC (<span class="font-mono">YYYY-MM-DDThh:mm:ssZ</span>)</li>
          <li><strong>Paginación:</strong> estilo Laravel (<span class="font-mono">data</span> + <span class="font-mono">meta</span> + <span class="font-mono">links</span>)</li>
        </ul>
      </div>
    </section>

    {{-- Endpoints --}}
    <section class="mb-10">
      <h2 class="mb-4 text-2xl font-bold">Endpoints</h2>

      {{-- Listado de eventos --}}
      <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-lg font-semibold">Listado de eventos</h3>
          <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">GET {{ $baseApiUrl }}/events</span>
        </div>

        <p class="mt-3 text-gray-700">
          Devuelve una lista paginada de eventos visibles y no cancelados.
        </p>

        <h4 class="mt-5 font-semibold">Parámetros (query)</h4>
        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left font-semibold">Parámetro</th>
                <th class="px-4 py-2 text-left font-semibold">Tipo</th>
                <th class="px-4 py-2 text-left font-semibold">Ejemplo</th>
                <th class="px-4 py-2 text-left font-semibold">Descripción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              @php
                $rows = [
                  ['q','string','q=teatro','Búsqueda en título/descr./lugar/municipio/territorio (cur/src).'],
                  ['territory','string','territory=Gipuzkoa','Filtra por territorio (match exacto en cur/src).'],
                  ['municipality','string','municipality=Donostia%20/%20San%20Sebasti%C3%A1n','Filtra por municipio (match exacto en cur/src).'],
                  ['from','date YYYY-MM-DD','from=2025-11-01','Incluye eventos que empiezan desde esa fecha (00:00 Europe/Madrid).'],
                  ['to','date YYYY-MM-DD','to=2025-11-30','Incluye eventos que empiezan hasta esa fecha (23:59 Europe/Madrid).'],
                  ['type_src','string','type_src=teatro','filtra por tipo de evento'],
                  ['type_slug','string','type_slug=experiencias-top','filtra por slug de tipo de evento'],
                  ['age_min','int','age_min=3','filtra por edad mínima'],
                  ['age_max','int','age_max=12','filtra por edad máxima'],
                  ['accessibility_tags','string','accessibility_tags=rampa,baño%20adaptado','filtra por etiquetas de accesibilidad'],
                  ['is_indoor','bool (0/1)','is_indoor=1','filtra por eventos en interior (1) o exterior (0)'],
                  ['per_page','int (1..400)','per_page=200','Elementos por página. Por defecto 20, máximo 400.'],
                  ['page','int','page=2','Página a recuperar.'],
                  ['include_past','bool (0/1)','include_past=1','Por defecto false → solo eventos con ends_at ≥ hoy (Europe/Madrid).'],
                  ['per_page','int (1..400)','per_page=200','Elementos por página. Por defecto 20, máximo 400.'],
                  ['page','int','page=2','Página a recuperar.'],
                ];
              @endphp
              @foreach ($rows as [$p,$t,$e,$d])
                <tr>
                  <td class="px-4 py-2 font-mono text-indigo-700">{{ $p }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ $t }}</td>
                  <td class="px-4 py-2 font-mono text-gray-700">{{ $e }}</td>
                  <td class="px-4 py-2 text-gray-700">{{ $d }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <h4 class="mt-6 font-semibold">Ejemplos</h4>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
          <div class="rounded-xl bg-gray-900 p-4 text-gray-100">
            <div class="mb-2 text-xs uppercase tracking-wide text-gray-400">Búsqueda básica</div>
            <pre class="text-xs"><code>curl "{{ $baseApiUrl }}/events?q=ciencia&per_page=400"</code></pre>
          </div>
          <div class="rounded-xl bg-gray-900 p-4 text-gray-100">
            <div class="mb-2 text-xs uppercase tracking-wide text-gray-400">Por municipio</div>
            <pre class="text-xs"><code>curl "{{ $baseApiUrl }}/events?municipality=Bilbao"</code></pre>
          </div>
          <div class="rounded-xl bg-gray-900 p-4 text-gray-100">
            <div class="mb-2 text-xs uppercase tracking-wide text-gray-400">Rango de fechas</div>
            <pre class="text-xs"><code>curl "{{ $baseApiUrl }}/events?from=2025-11-01&to=2025-11-30"</code></pre>
          </div>
        </div>

        <h4 class="mt-6 font-semibold">Respuesta (resumen)</h4>
        <pre class="mt-2 rounded-xl bg-gray-900 p-4 text-xs text-gray-100"><code>{
  "data": [
    {
      "id": 123,
      "title": { "cur": "Taller de ciencia", "src": "Science workshop" },
      "description": { "cur": "...", "src": "..." },
      "starts_at": "2025-11-20T19:00:00Z",
      "ends_at":   "2025-11-20T20:30:00Z",
      "venue": { "name": { "cur": "Casa de Cultura", "src": "Culture House" } },
      "location": {
        "municipality": { "cur": "Donostia / San Sebastián", "src": "Donostia / San Sebastián" },
        "territory": { "cur": "Gipuzkoa", "src": "Gipuzkoa" }
      },
      "media": { "image_url": "https://...", "source_url": "https://..." },
      "meta": { "visible": true, "is_canceled": false, "moderation": "approved",
        "source": "kulturklik", "source_id": "abc-123",
        "last_source_at": "2025-10-29T18:30:00Z", "created_at": "2025-10-20T10:00:00Z",
        "updated_at": "2025-10-28T09:00:00Z" },
      "event_type": { "id": 5, "slug": "taller",
        "name": { "es": "Taller", "eu": "Tailer" }, "icon": "mdi-hammer-wrench" },
      "type_src": "Workshop infantil",
      "age_min": 3, "age_max": 12, "is_indoor": true,
      "accessibility": ["rampa", "baño adaptado"]
    }
  ],
  "links": { "first":"...", "last":"...", "prev":null, "next":"..." },
  "meta": { "current_page":1, "per_page":400, "total":1234, "last_page":4 }
}</code></pre>
      </div>

      {{-- Detalle de evento --}}
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-lg font-semibold">Detalle de evento</h3>
          <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">GET {{ $baseApiUrl }}/events/{id}</span>
        </div>
        <p class="mt-3 text-gray-700">Devuelve un evento por su <span class="font-mono">id</span> (solo si es visible y no cancelado).</p>

        <h4 class="mt-5 font-semibold">Ejemplo</h4>
        <div class="mt-3 rounded-xl bg-gray-900 p-4 text-gray-100">
          <pre class="text-xs"><code>curl "{{ $baseApiUrl }}/events/123"</code></pre>
        </div>
      </div>
    </section>

    
    {{-- Códigos de estado --}}
    <section class="mb-10">
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h3 class="text-lg font-semibold">Códigos de estado</h3>
        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left font-semibold">Código</th>
                <th class="px-4 py-2 text-left font-semibold">Significado</th>
                <th class="px-4 py-2 text-left font-semibold">Cuándo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr>
                <td class="px-4 py-2 font-mono">200</td>
                <td class="px-4 py-2">OK</td>
                <td class="px-4 py-2">Respuesta correcta.</td>
              </tr>
              <tr>
                <td class="px-4 py-2 font-mono">400</td>
                <td class="px-4 py-2">Bad Request</td>
                <td class="px-4 py-2">Parámetros inválidos.</td>
              </tr>
              <tr>
                <td class="px-4 py-2 font-mono">404</td>
                <td class="px-4 py-2">Not Found</td>
                <td class="px-4 py-2">No existe o no es visible.</td>
              </tr>
              <tr>
                <td class="px-4 py-2 font-mono">422</td>
                <td class="px-4 py-2">Unprocessable Entity</td>
                <td class="px-4 py-2">Validación de parámetros.</td>
              </tr>
              <tr>
                <td class="px-4 py-2 font-mono">500</td>
                <td class="px-4 py-2">Server Error</td>
                <td class="px-4 py-2">Error inesperado.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
</div>
</div>
</x-app-layout>
