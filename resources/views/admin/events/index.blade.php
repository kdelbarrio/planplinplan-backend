<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin · Events</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin:20px;}
    table{border-collapse:collapse; width:100%}
    th,td{border-bottom:1px solid #eee; padding:8px; text-align:left; vertical-align:top}
    .pill{padding:2px 8px; border-radius:999px; font-size:12px; background:#eef}
    .ok{color:green}
    .btn{display:inline-block; padding:6px 10px; border:1px solid #ccc; border-radius:6px; text-decoration:none}
    .btn-primary{background:#1f6feb; color:#fff; border-color:#1f6feb}
    .toolbar{display:flex; gap:8px; flex-wrap:wrap; align-items:center}
    .bulk{display:flex; gap:8px; align-items:center}
  </style>
</head>
<body>
  <h1>Eventos (admin)</h1>

  @if(session('ok')) <div class="ok">{{ session('ok') }}</div>@endif

  <form method="get" class="toolbar" style="margin:12px 0;">
    <select name="status">
      <option value="">— moderación —</option>
      @foreach(['pending','approved','rejected'] as $s)
        <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
      @endforeach
    </select>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar… (título, municipio, territorio)" size="32">
    <select name="per_page">
      @foreach([10,20,50,100] as $n)
        <option value="{{ $n }}" @selected(request('per_page',20)==$n)>{{ $n }}/página</option>
      @endforeach
    </select>
    <button class="btn">Filtrar</button>
    <a class="btn" href="{{ route('admin.events.index') }}">Limpiar</a>
  </form>

  {{-- Form de acciones masivas --}}
  <form method="post" action="{{ route('admin.events.bulk') }}">
    @csrf
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="hidden" name="q" value="{{ request('q') }}">
    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
    <div class="bulk" style="margin:8px 0;">
      <label><input type="checkbox" id="select-all"> Seleccionar todos</label>
      <button class="btn" name="action" value="approve" type="submit">Aprobar</button>
      <button class="btn" name="action" value="publish" type="submit">Publicar</button>
      <button class="btn" name="action" value="approve_publish" type="submit">Aprobar + Publicar</button>
      <button class="btn" name="action" value="hide" type="submit">Ocultar</button>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:28px;"></th>
          <th>ID</th>
          <th>Título</th>
          <th>Fecha</th>
          <th>Municipio</th>
          <th>Territorio</th>
          <th>Mod.</th>
          <th>Visible</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      @foreach($events as $e)
        <tr>
          <td>
            <input type="checkbox" name="ids[]" value="{{ $e->id }}" class="row-check">
          </td>
          <td>{{ $e->id }}</td>
          <td>
            <div><strong>{{ $e->title_cur ?: $e->title_src }}</strong></div>
            <div><small style="color:#777">{{ $e->source }} #{{ $e->source_id }}</small></div>
          </td>
          <td>{{ optional($e->starts_at)->timezone('Europe/Madrid')->format('Y-m-d H:i') }}</td>
          <td>{{ $e->municipality_cur ?: $e->municipality_src }}</td>
          <td>{{ $e->territory_cur ?: $e->territory_src }}</td>
          <td><span class="pill">{{ $e->moderation }}</span></td>
          <td>
            <form method="post" action="{{ route('admin.events.toggleVisible', $e) }}">
              @csrf
              <button class="btn" type="submit">{{ $e->visible ? 'Sí' : 'No' }}</button>
            </form>
          </td>
          <td>
            <a class="btn btn-primary" href="{{ route('admin.events.edit', $e) }}">Editar</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <div style="margin-top:12px;">
      {{ $events->withQueryString()->links() }}
    </div>
  </form>

  <script>
    const selectAll = document.getElementById('select-all');
    const checks = Array.from(document.querySelectorAll('.row-check'));
    if (selectAll) {
      selectAll.addEventListener('change', e => {
        checks.forEach(ch => ch.checked = e.target.checked);
      });
    }
  </script>
</body>
</html>
