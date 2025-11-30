<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                        Actualizado por última vez:
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
                                        <th class="px-2 py-4">Nombre</th>
                                        <th class="px-2 py-4">Email</th>
                                        <th class="px-2 py-4">Registrado</th>
                                        <th class="px-2 py-4">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($usersToReview as $u)
                                    <tr>
                                        <td class="px-2 py-4">{{ $u->name }}</td>
                                        <td class="px-2 py-4">{{ $u->email }}</td>
                                        <td class="px-2 py-4">{{ optional($u->created_at)->translatedFormat('j \d\e F Y') }}</td>
                                        <td class="px-2 py-4">
                                            <form method="POST" action="{{ route('admin.users.promote', $u->id) }}" onsubmit="return confirm('Confirmar promoción de {{ addslashes($u->name) }} a administrador?')">
                                                @csrf
                                                <x-primary-button type="submit" >
                                                    Dar acceso
                                                </x-primary-button>
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
                                        <th class="px-2 py-4">Fecha</th>
                                        <th class="px-2 py-4">Origen</th>
                                        <th class="px-2 py-4">Insertadas</th>
                                        <th class="px-2 py-4">Actualizadas</th>
                                        <th class="px-2 py-4">Errores</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($etlRuns as $run)
                                    <tr>
                                        <td class="px-2 py-4">{{ \Carbon\Carbon::parse($run->finished_at)->translatedFormat('j \d\e F Y H:i') }}</td>
                                        <td class="px-2 py-4">{{ $run->source }}</td>
                                        <td class="px-2 py-4">{{ $run->inserted }}</td>
                                        <td class="px-2 py-4">{{ $run->updated }}</td>
                                        <td class="px-2 py-4">{{ $run->errors }}</td>
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
