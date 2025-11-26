<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\ExperiencesImportController;
use Illuminate\Http\Request;                       // <-- añadido
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\Admin\EtlController;
use App\Http\Controllers\Admin\DashboardController;

//Route::get('/', function () {
 //   return view('dashboard');
//});

//Route::get('/', [AdminEventController::class, 'index'])
//->middleware('auth')->name('admin.events.index');

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/admin/users/{user}/promote', [DashboardController::class, 'promote'])->name('admin.users.promote');
});

Route::get('/api/docs', [ApiDocsController::class, 'show'])->name('api.docs');

Route::middleware('auth','can:access-admin')->group(function () {
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

    
});

Route::fallback(function () {
    return redirect()->route('login');
});

require __DIR__.'/auth.php';
