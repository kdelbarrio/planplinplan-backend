<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-900 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <!-- Bienvenida y última sesión -->
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold">Hola, {{ $me->name }}</h1>
                    <p class="text-sm text-gray-600">
                        Última conexión:
                        @if($lastLogin)
                            {{ $lastLogin->translatedFormat('j \d\e F \a \l\a\s H:i') }} {{-- p. ej. "6 de noviembre a las 14:30" --}}
                        @else
                            Nunca registrada
                        @endif
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- admin-only area --}}
                @if($me->is_admin ?? false)

                    <!-- Usuarios por revisar -->
                    <section class="bg-gray-50 p-4 rounded border">
                        <h2 class="font-medium mb-3">Usuarios por revisar</h2>
                        @if($usersToReview->isEmpty())
                            <p class="text-gray-500">No hay usuarios pendientes.</p>
                        @else
                            <table class="min-w-full text-sm divide-y divide-gray-200">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500">
                                        <th class="px-2 py-2">Nombre</th>
                                        <th class="px-2 py-2">Email</th>
                                        <th class="px-2 py-2">Registrado</th>
                                        <th class="px-2 py-2">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($usersToReview as $u)
                                    <tr>
                                        <td class="px-2 py-3">{{ $u->name }}</td>
                                        <td class="px-2 py-3">{{ $u->email }}</td>
                                        <td class="px-2 py-3">{{ optional($u->created_at)->translatedFormat('j \d\e F Y') }}</td>
                                        <td class="px-2 py-3">
                                            <form method="POST" action="{{ route('admin.users.promote', $u->id) }}" onsubmit="return confirm('Confirmar promoción de {{ addslashes($u->name) }} a administrador?')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-indigo-600 hover:bg-indigo-700">
                                                    Dar acceso
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>

                    <!-- Últimas importaciones -->
                    <section class="bg-gray-50 p-4 rounded border">
                        <h2 class="font-medium mb-3">Últimas 10 importaciones</h2>
                        @if($etlRuns->isEmpty())
                            <p class="text-gray-500">No hay importaciones registradas.</p>
                        @else
                            <table class="min-w-full text-sm divide-y divide-gray-200">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500">
                                        <th class="px-2 py-2">Fecha</th>
                                        <th class="px-2 py-2">Origen</th>
                                        <th class="px-2 py-2">Insertadas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($etlRuns as $run)
                                    <tr>
                                        <td class="px-2 py-3">{{ \Carbon\Carbon::parse($run->finished_at)->translatedFormat('j \d\e F Y H:i') }}</td>
                                        <td class="px-2 py-3">{{ $run->source }}</td>
                                        <td class="px-2 py-3">{{ $run->inserted }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
