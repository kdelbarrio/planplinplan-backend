<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            Listado de eventos importados
        </h1>
    </x-slot>
<div class="py-12">
  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

    <div class="p-2 sm:p-2 bg-white shadow sm:rounded-lg">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <h2 class="px-4 pt-4 font-bold">Módulo importación ETL</h2>
        <div class="p-4">

          {{-- ETL runner: combo + botón --}}
          <form id="etl-form" method="POST" action="{{ route('admin.etl.run') }}" class="inline">
            @csrf
            <x-text-select name="etl" id="etl-select">
              <option value="kulturklik-100">Importar Kulturklik (100 eventos)</option>
              <option value="kulturklik-200">Importar Kulturklik (200 eventos)</option>
              <option value="experiencias">Importar Experiencias</option>
            </x-text-select>
            <x-primary-button id="etl-run" type="submit" class="mx-4">Ejecutar ETL</x-primary-button>
          </form>

          <div id="etl-output"></div>
        </div>
      </div>
    </div>

<div class="p-8 sm:p-2 bg-white shadow sm:rounded-lg">
  <h2 class="px-4 pt-4 font-bold">Filtrar eventos</h2> 
  <form method="get" class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 lg:grid-cols-5 xl:grid-cols-5 items-end">
      {{-- Estado (select) --}}
    <div>
      <x-input-label for="status">Estado</x-input-label> 
      <x-text-select name="status">
        <option value="">Todos los estados</option>
        @foreach(['pendiente','aprobado','rechazado'] as $s)
          <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
        @endforeach
      </x-text-select>
    </div>
    <div>
      <x-input-label for="event_type_id">Tipo de evento</x-input-label>
      <x-text-select name="event_type_id" id="event_type_id">
        <option value="">Todos los tipos</option>
        @foreach($types as $t)
          <option value="{{ $t->id }}" @selected((string)request('event_type_id') === (string)$t->id)>
            {{ $t->name }}
          </option>
        @endforeach
      </x-text-select>
    </div>

    <div>
      <x-input-label for="q">Búsqueda por texto</x-input-label>
      <x-text-input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar… (título, municipio, territorio)" />
    </div>
    <div>
      <x-input-label for="per_page">Resultados por página</x-input-label>  
      <x-text-select name="per_page">
        @foreach([10,20,50,100] as $n)
          <option value="{{ $n }}" @selected(request('per_page',20)==$n)>{{ $n }}</option>
        @endforeach
      </x-text-select>
    </div>
    <div>  
      <x-primary-button>Filtrar</x-primary-button>
      <a class="text-gray-600 underline mx-4" href="{{ route('admin.events.index') }}">Limpiar</a>
    </div>
  </form>
  <hr class="py-4">
    <div class="p-4">
        <p>Total de eventos: {{ $events->total() }} </p>
    </div>
  {{-- Form de acciones masivas --}}
  <form method="post" action="{{ route('admin.events.bulk') }}" class="p-4">
    @csrf
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="hidden" name="q" value="{{ request('q') }}">
    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div> 
        <label><input type="checkbox" id="select-all" class="px-2"> Seleccionar todos</label>
      </div>
      <div>
        <x-secondary-button class="btn" name="action" value="approve" type="submit">Aprobar</x-secondary-button>
        <x-secondary-button class="btn" name="action" value="publish" type="submit">Publicar</x-secondary-button>
        <x-secondary-button class="btn" name="action" value="approve_publish" type="submit">Aprobar + Publicar</x-secondary-button>
        <x-secondary-button class="btn" name="action" value="hide" type="submit">Ocultar</x-secondary-button>
      </div>
    </div>

    <table class="border-collapse " >
      <thead>
        <tr class="bg-gray-200">
          <th class="px-4 py-2"></th>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">Título</th>
          <th class="px-4 py-2">Fecha</th>
          <th class="px-4 py-2">Municipio</th>
          <th class="px-4 py-2">Territorio</th>
          <th class="px-4 py-2">Mod.</th>
          <th class="px-4 py-2">Visible</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
      @foreach($events as $e)
        <tr class="border-b hover:bg-gray-50" >
          <td class="px-2 py-2 ">
            <input type="checkbox" name="ids[]" value="{{ $e->id }}" class="row-check">
          </td>
          <td class="px-2 py-2">{{ $e->id }}</td>
          <td class="px-2 py-2">
            <div><strong>{{ $e->title_cur ?: $e->title_src }}</strong></div>
            <div><small style="color-gray">{{ $e->source }} #{{ $e->source_id }}</small></div>
          </td>
          <td class="px-2 py-2">{{ $e->starts_at?->timezone('Europe/Madrid')->format('d-m-Y H:i') ?? '' }}</td>
          <td class="px-2 py-2">{{ $e->municipality_cur ?: $e->municipality_src }}</td>
          <td class="px-2 py-2">{{ $e->territory_cur ?: $e->territory_src }}</td>
          <td class="px-2 py-2"><span class="pill">{{ $e->moderation }}</span></td>
          <td class="px-2 py-2 text-center">
            <!--<form method="post" action="{{ route('admin.events.toggleVisible', $e) }}">
              @csrf
              <x-secondary-button class="btn" type="submit">{{ $e->visible ? 'Sí' : 'No' }}</x-secondary-button>
            </form> -->
            {{ $e->visible ? '✔' : '✖' }}
          </td>
          <td class="px-2 py-2">
            <x-link-button href="{{ route('admin.events.edit', $e) }}">Editar</x-link-button>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <div class="mt-4">
      {{ $events->withQueryString()->links() }}
    </div>
  </form>
</div>
  <script>
    const selectAll = document.getElementById('select-all');
    const checks = Array.from(document.querySelectorAll('.row-check'));
    if (selectAll) {
      selectAll.addEventListener('change', e => {
        checks.forEach(ch => ch.checked = e.target.checked);
      });
    }
        // Envía la petición por AJAX y pinta la respuesta en la misma página
    document.getElementById('etl-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const select = document.getElementById('etl-select');
      const output = document.getElementById('etl-output');
      const token = document.querySelector('input[name="_token"]').value;

      output.textContent = 'Ejecutando...';

      try {
        const res = await fetch(this.action, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
          },
          body: JSON.stringify({ etl: select.value })
        });

        const json = await res.json();
        if (!res.ok) {
          output.textContent = json.error || JSON.stringify(json);
        } else {
          output.textContent = json.output || '(sin salida)';
        }
      } catch (err) {
        output.textContent = String(err);
      }
    });
  </script>
</div>
</x-app-layout>
