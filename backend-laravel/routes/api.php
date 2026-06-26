<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\TrackController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Listar todas as músicas (Aba Início)
Route::get('/tracks', [\App\Http\Controllers\TrackController::class, 'index']);

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

// Rota para listar todas as playlists na Biblioteca
Route::get('/playlists', [PlaylistController::class, 'index']);

// Rota para listar todas as playlists na Biblioteca
Route::get('/playlists', [PlaylistController::class, 'index']);

// NOVA ROTA: Criar playlist manual
Route::post('/playlists', [PlaylistController::class, 'store']);

// Rotas de gerenciamento de Playlists
Route::get('/playlists', [PlaylistController::class, 'index']);
Route::post('/playlists', [PlaylistController::class, 'store']);
Route::put('/playlists/{id}', [PlaylistController::class, 'update']);
Route::delete('/playlists/{id}', [PlaylistController::class, 'destroy']);

// Mover música para outra playlist
Route::put('/tracks/{id}/move', [TrackController::class, 'move']);

// Rota para resetar o status de uma música deletada fisicamente
Route::post('/tracks/{id}/reset-file', [\App\Http\Controllers\TrackController::class, 'resetFile']);

// Rota para fazer streaming do áudio com suporte a avançar/recuar
Route::get('/stream', [\App\Http\Controllers\AudioController::class, 'streamTrack']);

// Rota da ponte de Importação do Spotify
Route::post('/import-playlist', [\App\Http\Controllers\AudioController::class, 'importPlaylist']);
// Rota para exportar a playlist inteira em .zip para o Pendrive
Route::get('/playlists/{id}/export', [\App\Http\Controllers\AudioController::class, 'exportPlaylist']);

Route::post('/import-soundcloud', [App\Http\Controllers\AudioController::class, 'importSoundcloud']);