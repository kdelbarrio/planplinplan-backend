<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\ExperiencesImportController;
use Illuminate\Http\Request;                       // <-- añadido
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\Admin\EtlController;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', [AdminEventController::class, 'index'])
->middleware('auth')->name('admin.events.index');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/api/docs', [ApiDocsController::class, 'show'])->name('api.docs');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/events', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::post('/events/bulk', [AdminEventController::class, 'bulk'])->name('admin.events.bulk');
    Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('admin.events.edit');
    Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('admin.events.update');
    Route::post('/events/{event}/toggle-visible', [AdminEventController::class, 'toggleVisible'])->name('admin.events.toggleVisible');

    // Página con la tabla de ETLs
    Route::get('/etl', [EtlController::class, 'index'])->name('admin.etl.index');

    // POST para ejecutar una ETL
    Route::post('/etl/run', [EtlController::class, 'run'])->name('admin.etl.run');

    /*
    Route::post('/admin/etl/run', function (Request $request) {
    $request->validate(['etl' => 'required|string']);

    $map = [
        //'kulturklik'   => ['command' => 'etl:import', 'params' => ['--source' => 'kulturklik']],
        'kulturklik-100'   => ['command' => 'etl:import', 'params' => ['--mode' => 'upcoming','--max' => '2']],
        'kulturklik-200'   => ['command' => 'etl:import', 'params' => ['--mode' => 'upcoming','--max' => '4']],
        'experiencias' => ['command' => 'etl:import-experiences', 'params' => []],
    ];

    $key = $request->input('etl');
    if (! isset($map[$key])) {
        return response()->json(['error' => 'Unknown ETL selected'], 400);
    }

    $entry = $map[$key];

    // Usar BufferedOutput para capturar correctamente la salida del comando
    $buffer = new \Symfony\Component\Console\Output\BufferedOutput();

    try {
        \Artisan::call($entry['command'], $entry['params'], $buffer);
        $output = trim($buffer->fetch());
    } catch (\Throwable $e) {
        return response()->json(['output' => '(error)', 'error' => $e->getMessage()], 500);
    }

    return response()->json(['output' => $output === '' ? '(sin salida)' : $output]);
    })->name('admin.etl.run');
*/

});

require __DIR__.'/auth.php';
