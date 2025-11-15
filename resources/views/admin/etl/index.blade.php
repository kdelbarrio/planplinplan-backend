<x-app-layout>
  <x-slot name="header">
    <h1 class="font-semibold text-xl text-gray-800 leading-tight">
      Ejecutar ETLs
    </h1>
  </x-slot>

  <div class="py-6">
    <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
      <div class="bg-white shadow-sm sm:rounded-lg">
        <div class="p-6">
          <p class="text-sm text-gray-600 mb-4">
            Selecciona y ejecuta una ETL. La salida del comando se mostrará en la última columna.
          </p>

          <table class="w-full border-collapse">
            <thead class="text-left text-sm text-gray-600 border-b">
              <tr class="[&>th]:px-4 [&>th]:py-2">
                <th class="w-1/5">ETL</th>
                <th class="w-1/6">Fuente</th>
                <th class="w-2/5">Descripción</th>
                <th class="w-1/6">Acción</th>
                <th class="w-1/4">Salida</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              @foreach ($etls as $key => $etl)
                <tr class="[&>td]:px-4 [&>td]:py-2 align-top">
                  <td class="font-medium text-gray-900">
                    {{ $etl['name'] }}
                    <div class="text-xs text-gray-500">clave: {{ $key }}</div>
                    <div class="text-xs text-gray-500">comando: <code>{{ $etl['command'] }}</code></div>
                  </td>

                  <td class="text-gray-700">{{ $etl['source'] }}</td>

                  <td class="text-gray-700">{{ $etl['description'] }}</td>

                  <td>
                    <button
                      type="button"
                      class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                      data-etl-run
                      data-etl-key="{{ $key }}"
                    >
                      Ejecutar
                    </button>
                  </td>

                  <td class="text-xs">
                    <pre class="whitespace-pre-wrap break-words bg-gray-50 p-2 rounded border" data-etl-output="{{ $key }}">—</pre>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

        </div>
      </div>
    </div>
  </div>

  {{-- Token CSRF de Breeze (ya viene en el layout por defecto). Si no, descomenta lo de abajo. --}}
  {{-- <meta name="csrf-token" content="{{ csrf_token() }}"> --}}

  <script>
    (function () {
      const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        @json(csrf_token());

      document.querySelectorAll('[data-etl-run]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const key = btn.getAttribute('data-etl-key');
          const out = document.querySelector(`[data-etl-output="${key}"]`);
          btn.disabled = true;
          out.textContent = 'Ejecutando…';

          try {
            const res = await fetch("{{ route('admin.etl.run') }}", {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
              },
              body: JSON.stringify({ etl: key })
            });

            const json = await res.json();
            out.textContent = res.ok
              ? (json.output || '(sin salida)')
              : (json.error || JSON.stringify(json));

          } catch (err) {
            out.textContent = String(err);
          } finally {
            btn.disabled = false;
          }
        });
      });
    })();
  </script>
</x-app-layout>
