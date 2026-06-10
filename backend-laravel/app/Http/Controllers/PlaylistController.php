<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Playlist;
use App\Models\Track;

class PlaylistController extends Controller
{
    /**
     * Importa uma playlist pública do Spotify, salvando o container e os metadados.
     */
    public function import(Request $request)
    {
        // 1. Valida se a URL foi enviada corretamente
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            // 2. Envia a URL para o microsserviço Python fazer a raspagem do título
            $response = Http::post('http://python-extractor:5000/import-playlist', [
                'url' => $request->url
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'O extrator Python não conseguiu processar este link.'
                ], $response->status());
            }

            $data = $response->json();

            // 3. Cria o registro da Playlist no MySQL usando o Model
            $playlist = Playlist::create([
                'name' => $data['playlist_name'],
                'description' => 'Importada via link público do Spotify.'
            ]);

            // 4. Preparação para o vínculo de músicas
            // Como o raspador HTML puro trouxe 0 músicas por conta do JavaScript do Spotify,
            // o array estará vazio por enquanto, mas a lógica relacional já fica pronta:
            $trackIds = [];
            
            if (!empty($data['tracks_urls'])) {
                foreach ($data['tracks_urls'] as $trackData) {
                    // O método firstOrCreate evita duplicar a mesma música no banco
                    $track = Track::firstOrCreate(
                        [
                            'title' => $trackData['title'] ?? 'Música sem título',
                            'artist' => $trackData['artist'] ?? 'Artista desconhecido',
                        ],
                        [
                            'youtube_id' => null,
                            'cover_url' => $trackData['cover_url'] ?? null,
                            'duration_seconds' => $trackData['duration_seconds'] ?? 0,
                        ]
                    );
                    $trackIds[] = $track->id;
                }

                // O método attach() insere automaticamente as linhas na tabela pivô (playlist_track)
                $playlist->tracks()->attach($trackIds);
            }

            // 5. Retorna a resposta de sucesso com os dados do banco
            return response()->json([
                'success' => true,
                'message' => 'Playlist criada com sucesso no ecossistema VibeCast!',
                'playlist' => [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'description' => $playlist->description,
                    'total_tracks_imported' => count($trackIds),
                    'created_at' => $playlist->created_at
                ],
                'tracks' => $playlist->tracks,
                'raw_python_data' => $data
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erro ao importar playlist: ' . $e->getMessage());

            return response()->json([
                'error' => 'Erro interno ao salvar os dados da playlist.'
            ], 500);
        }
    }
}