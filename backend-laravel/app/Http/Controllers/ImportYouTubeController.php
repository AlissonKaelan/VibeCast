<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Playlist;
use App\Models\Track;

class ImportYouTubeController extends Controller
{
    public function import(Request $request)
    {
        // 1. Validar se o link foi enviado
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            // 2. O Laravel pede ao Python para extrair os dados
            // Atenção: O nome 'vibecast-python-extractor-1' deve ser o nome do serviço ou contentor na rede Docker
            $response = Http::post('http://vibecast-python-extractor-1:5000/import-youtube', [
                'url' => $request->url
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'O Python não conseguiu extrair os dados deste link.'], 400);
            }

            $data = $response->json();

            // 3. Criar a Playlist na Base de Dados
            $playlist = Playlist::create([
                'name' => $data['playlist_name'],
                'source' => 'youtube',
                // 'user_id' => auth()->id() // Descomente se tiver autenticação ativa
            ]);

            // 4. Guardar as faixas e conectá-las à Playlist
            foreach ($data['tracks_urls'] as $trackData) {
                // Primeiro: Cria a música solta no banco
                $track = Track::create([
                    'title' => $trackData['title'],
                    'artist' => $trackData['artist'],
                    'cover_url' => $trackData['cover_url'],
                    'youtube_url' => $trackData['youtube_url'],
                    'youtube_id' => $trackData['youtube_id'], 
                    'duration_seconds' => $trackData['duration_seconds'],
                    'status' => 'pending' // ou o status padrão que você usa
                ]);

                // Segundo: Conecta a música recém-criada à playlist na tabela pivot!
                $playlist->tracks()->attach($track->id);
            }

            return response()->json([
                'success' => true,
                'message' => $data['message'],
                'playlist_id' => $playlist->id,
                'total_tracks' => $data['total_tracks']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro interno no servidor: ' . $e->getMessage()], 500);
        }
    }
}