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
  <p><small>Fecha: {{ optional($event->starts_at)->timezone('Europe/Madrid')->format('Y-m-d H:i') }}</small></p>

  <form method="post" action="{{ route('admin.events.update', $event) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="key" value="{{ request('key') }}">

    <label>Título (curado)</label>
    <input type="text" name="title_cur" value="{{ old('title_cur', $event->title_cur) }}" placeholder="Opcional…">

    <label>Descripción (curada)</label>
    <textarea name="description_cur" rows="6" placeholder="Opcional…">{{ old('description_cur', $event->description_cur) }}</textarea>

    <div class="row">
      <div>
        <label>Municipio (curado)</label>
        <input type="text" name="municipality_cur" value="{{ old('municipality_cur', $event->municipality_cur) }}">
      </div>
      <div>
        <label>Territorio (curado)</label>
        <input type="text" name="territory_cur" value="{{ old('territory_cur', $event->territory_cur) }}">
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
          @foreach(['pending','approved','rejected'] as $s)
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
      <a class="btn" href="{{ route('admin.events.index', ['key'=>request('key')]) }}">Volver</a>
    </div>
  </form>

  <hr style="margin:24px 0">

  <h3>Datos de origen</h3>
  <ul>
    <li><strong>Título (origen):</strong> {{ $event->title_src }}</li>
    <li><strong>Municipio (origen):</strong> {{ $event->municipality_src }}</li>
    <li><strong>Territorio (origen):</strong> {{ $event->territory_src }}</li>
    <li><strong>Precio (origen):</strong> {{ $event->price_desc_src }}</li>
    <li><strong>Organizador (origen):</strong> {{ $event->organizer_src }}</li>
    <li><strong>URL fuente:</strong> <a href="{{ $event->source_url }}" target="_blank">{{ $event->source_url }}</a></li>
  </ul>
</body>
</html>
