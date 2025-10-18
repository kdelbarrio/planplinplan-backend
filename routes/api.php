<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
