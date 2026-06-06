<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\PlaylistController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rota da nossa API VibeCast (sem bloqueios do web)
Route::post('/track/stream', [AudioController::class, 'getStreamUrl']);

Route::post('/playlist/import', [PlaylistController::class, 'import']);