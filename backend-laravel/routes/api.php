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

// O motor real está de volta!
Route::post('/playlist/import', [PlaylistController::class, 'import']);

// Rota para baixar a música fisicamente para o PC
Route::post('/tracks/{id}/download', [AudioController::class, 'downloadTrack']);

// Rota para disparar o download em lote (Fila)
Route::post('/playlists/{id}/download-all', [AudioController::class, 'downloadPlaylistTracks']);

// Rota para consultar o status atualizado da playlist (Polling)
Route::get('/playlists/{id}', [PlaylistController::class, 'show']);