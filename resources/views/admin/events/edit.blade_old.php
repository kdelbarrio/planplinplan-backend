<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Editar evento #{{ $event->id }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin:20px; max-width:900px}
    label{display:block; margin-top:12px; font-weight:600}
    input[type=text], textarea, select {width:100%; padding:8px; border:1px solid #ccc; border-radius:6px}
    .row{display:grid; grid-template-columns:1fr 1fr; gap:12px}
    .btn{display:inline-block; padding:8px 12px; border:1px solid #ccc; border-radius:6px; text-decoration:none}
    .btn-primary{background:#1f6feb; color:#fff; border-color:#1f6feb}
    .field-help{font-size:12px; color:#666}
    .src-block{background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:10px; margin-top:6px}
    .copy{font-size:12px; margin-left:6px}
  </style>
</head>
<body>
  @if(session('ok')) <div style="color:green; margin-bottom:8px">{{ session('ok') }}</div>@endif
  @if($errors->any())
    <div style="color:#b00; margin-bottom:8px">
      <strong>Revisa:</strong> {{ implode(' · ', $errors->all()) }}
    </div>
  @endif

  <h1>Editar evento #{{ $event->id }}</h1>

  <p><small>Fuente: {{ $event->source }} #{{ $event->source_id }}</small></p>
  <p><small>Fecha: {{ $event->starts_at?->timezone('Europe/Madrid')->format('Y-m-d H:i') ?? ''}}</small></p>

  <form method="post" action="{{ route('admin.events.update', $event) }}">
    @csrf
    @method('PUT')

    {{-- Título --}}
    <label>Título (curado)</label>
    <div class="row">
      <div>
        <input id="title_cur" type="text" name="title_cur" value="{{ old('title_cur', $event->title_cur) }}" placeholder="Opcional…">
        <div class="field-help">Se mostrará este si existe; si no, el de origen.</div>
      </div>
      <div>
        <div class="src-block">
          <div><strong>Origen:</strong> <span id="title_src_txt">{{ $event->title_src }}</span></div>
          <button type="button" class="btn copy" data-target="#title_cur" data-src="#title_src_txt">Usar como curado</button>
        </div>
      </div>
    </div>

    {{-- Descripción --}}
    <label>Descripción (curada)</label>
    <div class="row">
      <div>
        <textarea id="description_cur" name="description_cur" rows="6" placeholder="Opcional…">{{ old('description_cur', $event->description_cur) }}</textarea>
      </div>
      <div>
        <div class="src-block">
          <div><strong>Origen (HTML):</strong></div>
          <div id="description_src_txt" style="max-height:180px; overflow:auto; background:white; padding:6px; border:1px solid #eee">
            {!! $event->description_src !!}
          </div>
          <button type="button" class="btn copy" data-target="#description_cur" data-src="#description_src_txt" data-html="1">Usar como curado</button>
        </div>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Municipio (curado)</label>
        <input id="municipality_cur" type="text" name="municipality_cur" value="{{ old('municipality_cur', $event->municipality_cur) }}">
        <div class="src-block">
          <div><strong>Origen:</strong> <span id="municipality_src_txt">{{ $event->municipality_src }}</span></div>
          <button type="button" class="btn copy" data-target="#municipality_cur" data-src="#municipality_src_txt">Usar como curado</button>
        </div>
      </div>
      <div>
        <label>Territorio (curado)</label>
        <input id="territory_cur" type="text" name="territory_cur" value="{{ old('territory_cur', $event->territory_cur) }}">
        <div class="src-block">
          <div><strong>Origen:</strong> <span id="territory_src_txt">{{ $event->territory_src }}</span></div>
          <button type="button" class="btn copy" data-target="#territory_cur" data-src="#territory_src_txt">Usar como curado</button>
        </div>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Edad mínima</label>
        <input type="text" name="age_min" value="{{ old('age_min', $event->age_min) }}">
      </div>
      <div>
        <label>Edad máxima</label>
        <input type="text" name="age_max" value="{{ old('age_max', $event->age_max) }}">
      </div>
    </div>

    <label>Etiquetas de accesibilidad (coma o nueva línea)</label>
    <textarea name="accessibility_tags" rows="3" placeholder="ramp, subtitles, sign-language">{{ old('accessibility_tags', is_array($event->accessibility_tags) ? implode(', ', $event->accessibility_tags) : '') }}</textarea>

    <div class="row">
      <div>
        <label>Moderación</label>
        <select name="moderation">
          @foreach(['pendiente','aprobado','rechazado'] as $s)
            <option value="{{ $s }}" @selected(old('moderation', $event->moderation)===$s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label>Visible</label>
        <select name="visible">
          <option value="1" @selected(old('visible', (int)$event->visible)===1)>Sí</option>
          <option value="0" @selected(old('visible', (int)$event->visible)===0)>No</option>
        </select>
      </div>
    </div>

    <div style="margin-top:16px">
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
      <a class="btn" href="{{ route('admin.events.index') }}">Volver</a>
    </div>
  </form>

  <hr style="margin:24px 0">

  <h3>Datos de origen (solo lectura)</h3>
  <ul>
    <li><strong>Título (origen):</strong> {{ $event->title_src }}</li>
    <li><strong>Municipio (origen):</strong> {{ $event->municipality_src }}</li>
    <li><strong>Territorio (origen):</strong> {{ $event->territory_src }}</li>
    <li><strong>Precio (origen):</strong> {{ $event->price_desc_src }}</li>
    <li><strong>Organizador (origen):</strong> {{ $event->organizer_src }}</li>
    <li><strong>URL fuente:</strong> <a href="{{ $event->source_url }}" target="_blank">{{ $event->source_url }}</a></li>
  </ul>

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
</body>
</html>
