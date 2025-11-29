<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar evento #{{ $event->id }}
        </h1>
    </x-slot>

<div class="py-12">
   
  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
    <x-auth-session-status class="mb-4" :status="session('ok')" />
      @if($errors->any())
        <div style="color:#b00; margin-bottom:8px">
          <strong>Revisa:</strong> {{ implode(' · ', $errors->all()) }}
        </div>
      @endif
      <p>Fuente: {{ $event->source }} #{{ $event->source_id }}</p>


    <form method="post" action="{{ route('admin.events.update', $event) }}">
    @csrf
    @method('PUT')

      <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <div class="flex justify-end gap-4 mb-4">  
          <p>* Se mostrará el texto curado si existe; si no, el de origen.</p>
        </div>

        {{-- Título --}}    
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
          <div>
            <div class="text-xl"><strong>Título:</strong> <span id="title_src_txt">{{ $event->title_src }}</span></div>
            <button type="button" class="copy text-gray-600 underline" data-target="#title_cur" data-src="#title_src_txt">Usar como curado</button>
          </div>

          <div>
            <x-input-label>Título (curado)</x-input-label>
            <x-text-input class="w-full" id="title_cur" type="text" name="title_cur" value="{{ old('title_cur', $event->title_cur) }}" placeholder="Opcional…" />
            
          </div>

        </div>

        {{-- Descripción --}}
      
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
          <div>
              <div><strong>Descripción (HTML):</strong></div>
              <div id="description_src_txt" class="border p-2 rounded-md shadow-sm max-h-32 overflow-y-auto bg-gray-50">
                {!! $event->description_src !!}
              </div>
              <button type="button" class="copy text-gray-600 underline" data-target="#description_cur" data-src="#description_src_txt" data-html="1">Usar como curado</button>
          </div>  
        
          <div>
            <x-input-label>Descripción (curada) </x-input-label>
            <textarea class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" id="description_cur" name="description_cur"  placeholder="Opcional…">{{ old('description_cur', $event->description_cur) }}</textarea>
          </div>
          
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
          
            <div>
              <div><strong>Municipio:</strong> <span id="municipality_src_txt">{{ $event->municipality_src }}</span></div>
              <button type="button" class="copy text-gray-600 underline" data-target="#municipality_cur" data-src="#municipality_src_txt">Usar como curado</button>
            </div>
            <div>
              <x-input-label>Municipio (curado)</x-input-label>
              <x-text-input id="municipality_cur" type="text" name="municipality_cur" value="{{ old('municipality_cur', $event->municipality_cur) }}" />
            </div>
        </div>
      
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-4">
          
            <div>
              <div><strong>Territorio:</strong> <span id="territory_src_txt">{{ $event->territory_src }}</span></div>
              <button type="button" class="copy text-gray-600 underline" data-target="#territory_cur" data-src="#territory_src_txt">Usar como curado</button>
            </div>
            <div>
              <x-input-label>Territorio (curado)</x-input-label>
              <x-text-input id="territory_cur" type="text" name="territory_cur" value="{{ old('territory_cur', $event->territory_cur) }}" />
            </div>
        </div>

        
      </div>

      <div class="p-4 mt-6 sm:p-8 bg-white shadow sm:rounded-lg">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <x-input-label>Edad mínima</x-input-label>
            <x-text-input type="text" name="age_min" value="{{ old('age_min', $event->age_min) }}" />
          </div>
          <div>
            <x-input-label>Edad máxima</x-input-label>
            <x-text-input type="text" name="age_max" value="{{ old('age_max', $event->age_max) }}" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <x-input-label>Etiquetas de accesibilidad (coma o nueva línea)</x-input-label>
            <x-text-input name="accessibility_tags"  placeholder="ramp, subtitles, sign-language" value="{{ old('accessibility_tags', is_array($event->accessibility_tags) ? implode(', ', $event->accessibility_tags) : '') }}" />
          </div>
          <div>
            <x-input-label>Indoor</x-input-label>
            <x-text-select name="is_indoor">
              <option value="1" @selected(old('is_indoor', (int)$event->is_indoor)===1)>Sí</option>
              <option value="0" @selected(old('is_indoor', (int)$event->is_indoor)===0)>No</option>
            </x-text-select>
          </div>

        
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <x-input-label>Moderación</x-input-label>
            <x-text-select name="moderation">
              @foreach(['pendiente','aprobado','rechazado'] as $s)
                <option value="{{ $s }}" @selected(old('moderation', $event->moderation)===$s)>{{ $s }}</option>
              @endforeach
            </x-text-select>
          </div>
          <div>
            <x-input-label>Visible</x-input-label>
            <x-text-select name="visible">
              <option value="1" @selected(old('visible', (int)$event->visible)===1)>Sí</option>
              <option value="0" @selected(old('visible', (int)$event->visible)===0)>No</option>
            </x-text-select>
          </div>
        </div>
      </div>
    
      <div class="flex justify-end gap-4 mt-6">
          <a class="text-gray-600 underline mx-4" href="{{ route('admin.events.index') }}">Volver</a>  
          <x-primary-button type="submit">Guardar cambios</x-primary-button>
      </div>
    </form>

    <hr style="my-8">
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">

      <h2 class="mb-4 text-xl bold">Datos de origen (solo lectura)</h2>
      <div class="flex flex-row gap-8">
        <div>
          <img src="{{ $event->image_url }}" alt="Imagen del evento" style="max-width:100%; height:auto; margin-bottom:16px;" />    
        </div>
        <div>
          <ul>
            <li><strong>Título:</strong> {{ $event->title_src }}</li>
            <li><strong>Tipo de evento:</strong> {{ $event->eventType?->name ?? '—' }}
            <li><strong>Fecha inicio:</strong> {{ $event->starts_at?->timezone('Europe/Madrid')->format('d-m-Y') ?? ''}}</li>
            <li><strong>Fecha fin:</strong> {{ $event->ends_at?->timezone('Europe/Madrid')->format('d-m-Y') ?? ''}}</li>
            <li><strong>Hora:</strong> {{ $event->opening_hours }}</li>
            <li><strong>Municipio:</strong> {{ $event->municipality_src }}</li>
            <li><strong>Territorio:</strong> {{ $event->territory_src }}</li>
            <li><strong>Precio:</strong> {{ $event->price_desc_src }}</li>
            <li><strong>Organizador:</strong> {{ $event->organizer_src }}</li>
            <li><strong>URL fuente:</strong> <a href="{{ $event->source_url }}" target="_blank">{{ $event->source_url }}</a></li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</div>

  <script>
    // Copiar desde origen al campo curado
    document.querySelectorAll('.copy').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.querySelector(btn.dataset.target);
        const srcEl  = document.querySelector(btn.dataset.src);
        if (!target || !srcEl) return;
        const useHtml = btn.dataset.html === '1';
        const value = useHtml ? srcEl.innerHTML : srcEl.textContent;
        if (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT') {
          target.value = value.trim();
        }
      });
    });
  </script>


</x-app-layout>
