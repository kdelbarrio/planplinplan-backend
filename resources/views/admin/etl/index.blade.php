<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            Listado de eventos importados
        </h1>
    </x-slot>

{{-- ===== ETL runner: combo + botón (tu bloque original) ===== --}}
<form id="etl-form" method="POST" action="{{ route('admin.etl.run') }}" class="inline">
  @csrf
  <x-text-select name="etl" id="etl-select">
    <option value="kulturklik">Importar Kulturklik</option>
    <option value="experiencias">Importar Experiencias</option>
  </x-text-select>
  <x-primary-button id="etl-run" type="submit" class="mx-4">Ejecutar ETL</x-primary-button>
</form>

<pre id="etl-output" class="mt-3 p-3 bg-gray-50 border rounded text-sm overflow-auto"></pre>

{{-- ===== Tabla ETLs ===== --}}
@php
  // mover esto a config/imports.php más adelante
  $etls = [
    [
      'key' => 'kulturklik',
      'name' => 'Importar Kulturklik',
      'source' => 'Kulturklik',
      'description' => 'Descarga y normaliza datos de eventos desde Kulturklik.',
    ],
    [
      'key' => 'experiencias',
      'name' => 'Importar Experiencias',
      'source' => 'CSV/Panel',
      'description' => 'Carga experiencias y aplica reglas (municipality/territory, fechas y age_min=3).',
    ],
  ];
@endphp

<div class="mt-6 bg-white shadow sm:rounded-lg">
  <div class="px-6 py-4">
    <table class="w-full border-collapse">
      <thead class="text-left text-sm text-gray-600 border-b">
        <tr class="[&>th]:px-4 [&>th]:py-2">
          <th class="w-1/5">ETL</th>
          <th class="w-1/6">Fuente</th>
          <th class="w-2/5">Descripción</th>
          <th class="w-1/6">Acción</th>
          <th class="w-1/6">Salida</th>
        </tr>
      </thead>
      <tbody class="text-sm divide-y">
        @foreach ($etls as $etl)
          <tr class="[&>td]:px-4 [&>td]:py-2 align-top">
            <td class="font-medium text-gray-900">
              {{ $etl['name'] }}
              <div class="text-xs text-gray-500">clave: {{ $etl['key'] }}</div>
            </td>
            <td>{{ $etl['source'] }}</td>
            <td class="text-gray-700">{{ $etl['description'] }}</td>
            <td>
              <x-primary-button
                type="button"
                class="etl-run-row"
                data-key="{{ $etl['key'] }}">
                Ejecutar
              </x-primary-button>
            </td>
            <td>
              <pre id="out-{{ $etl['key'] }}" class="p-2 bg-gray-50 border rounded text-xs max-h-28 overflow-auto"></pre>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- ===== Script: mantiene tu submit + añade ejecución por fila ===== --}}
<script>
  // --- Tu handler original del combo + botón:
  document.getElementById('etl-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const select = document.getElementById('etl-select');
    const output = document.getElementById('etl-output');
    const token  = document.querySelector('input[name="_token"]').value;

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
      output.textContent = res.ok ? (json.output || '(sin salida)') : (json.error || JSON.stringify(json));
    } catch (err) {
      output.textContent = String(err);
    }
  });

  // --- Botón "Ejecutar" por fila (usa misma ruta y token del form):
  (function () {
    const runButtons = document.querySelectorAll('.etl-run-row');
    const token = document.querySelector('input[name="_token"]').value;
    const action = document.getElementById('etl-form').action;

    runButtons.forEach(btn => {
      btn.addEventListener('click', async () => {
        const key = btn.dataset.key;
        const out = document.getElementById('out-' + key);
        out.textContent = 'Ejecutando...';

        try {
          const res = await fetch(action, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': token,
              'Accept': 'application/json'
            },
            body: JSON.stringify({ etl: key })
          });
          
          const json = await res.json();
          out.textContent = res.ok ? (json.output || '(sin salida)') : (json.error || JSON.stringify(json));
        
        } catch (err) {
          out.textContent = String(err);
        }
      });
    });
  })();
</script>
</x-app-layout>