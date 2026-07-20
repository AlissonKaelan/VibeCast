<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\RadioController;
use App\Http\Controllers\ImportYouTubeController;
use App\Models\Track;
use App\Jobs\DownloadAudioJob;

/*
|--------------------------------------------------------------------------
| Rotas de Utilizador (Sanctum)
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Rotas de Importação (Pontes para o Python)
|--------------------------------------------------------------------------
*/
Route::post('/import-playlist', [AudioController::class, 'importPlaylist']);
Route::post('/import-soundcloud', [AudioController::class, 'importSoundcloud']);
Route::post('/import/youtube', [ImportYouTubeController::class, 'import']);
Route::post('/playlist/import', [PlaylistController::class, 'import']);

/*
|--------------------------------------------------------------------------
| Rotas de Playlists
|--------------------------------------------------------------------------
*/
Route::get('/playlists', [PlaylistController::class, 'index']);
Route::post('/playlists', [PlaylistController::class, 'store']);
Route::get('/playlists/{id}', [PlaylistController::class, 'show']);
Route::put('/playlists/{id}', [PlaylistController::class, 'update']);
Route::delete('/playlists/{id}', [PlaylistController::class, 'destroy']);
Route::get('/playlists/{id}/status', [AudioController::class, 'getPlaylistStatus']);
Route::get('/playlists/{id}/export', [AudioController::class, 'exportPlaylist']);

/*
|--------------------------------------------------------------------------
| Rotas de Músicas (Tracks)
|--------------------------------------------------------------------------
*/
Route::get('/tracks', [TrackController::class, 'index']);
Route::put('/tracks/{id}', [AudioController::class, 'updateTrack']);
Route::delete('/tracks/{id}', [AudioController::class, 'deleteTrack']);
Route::put('/tracks/{id}/move', [TrackController::class, 'move']);
Route::post('/tracks/{id}/reset-file', [TrackController::class, 'resetFile']);

/*
|--------------------------------------------------------------------------
| Rotas de Áudio e Streaming
|--------------------------------------------------------------------------
*/
Route::get('/stream', [AudioController::class, 'streamTrack']);
Route::post('/track/stream', [AudioController::class, 'getStreamUrl']);

/*
|--------------------------------------------------------------------------
| Rotas de Web Rádio
|--------------------------------------------------------------------------
*/
Route::get('/radios', [RadioController::class, 'index']);
Route::post('/radios', [RadioController::class, 'store']);
Route::delete('/radios/{id}', [RadioController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| Lógica Mestre de Downloads (Com Proteção de Atualização)
|--------------------------------------------------------------------------
*/

// Rota para disparar o download em lote
Route::post('/playlists/{id}/download', [AudioController::class, 'downloadPlaylistTracks']);
Route::post('/playlists/{id}/download-all', [AudioController::class, 'downloadPlaylistTracks']);

// Rota Protegida para baixar música individual
Route::post('/tracks/{id}/download', function ($id) {
    // 1. O Laravel liga para o Python para saber se ele está ocupado a atualizar
    try {
        // Usa o nome do serviço docker 'vibecast-python-extractor-1' ou apenas 'extractor-python' dependendo do seu compose
        $response = Http::timeout(3)->get('http://vibecast-python-extractor-1:5000/status');
        $data = $response->json();

        if (isset($data['status']) && $data['status'] === 'updating') {
            return response()->json([
                'success' => false,
                'error_type' => 'updating',
                'message' => 'Servidor em manutenção automática (Atualização de segurança). Tente baixar novamente daqui a um minuto.'
            ], 503); 
        }
    } catch (\Exception $e) {
        // Se a API não responder, assume que está a reiniciar
        return response()->json([
            'success' => false,
            'error_type' => 'updating',
            'message' => 'O motor de download está a aquecer. Aguarde um instante.'
        ], 503);
    }

    // 2. Se o Python estiver livre, despacha o trabalho para a fila (O mesmo que o AudioController faz)
    $track = Track::findOrFail($id);
    dispatch(new DownloadAudioJob($track));
    
    return response()->json(['success' => true]);
});