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
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $response = Http::post('http://python-extractor:5000/import-playlist', [
                'url' => $request->url
            ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'O extrator Python não conseguiu processar este link.'
                ], $response->status());
            }

            $data = $response->json();

            $playlist = Playlist::create([
                'name' => $data['playlist_name'],
                'description' => 'Importada via link público do Spotify.'
            ]);

            $trackIds = [];
            
            if (!empty($data['tracks_urls'])) {
                foreach ($data['tracks_urls'] as $trackData) {
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

                // A MÁGICA AQUI: Usamos syncWithoutDetaching em vez de attach. 
                // Isso impede que a música seja duplicada na tabela pivô se houver um re-import!
                $playlist->tracks()->syncWithoutDetaching($trackIds);
            }

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

    // Mostrar uma playlist e as suas músicas
    public function show($id)
    {
        // O "with('tracks')" diz ao Laravel para usar a tabela pivô e buscar as músicas!
        $playlist = Playlist::with('tracks')->findOrFail($id);

        return response()->json([
            'playlist' => $playlist,
            'tracks' => $playlist->tracks // Devolve as músicas corretamente
        ]);
    }

    public function index()
    {
        // Pega todas as playlists salvas no banco de dados
        $playlists = Playlist::withCount('tracks')->get();

        return response()->json([
            'playlists' => $playlists
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validamos o nome como obrigatório e a descrição como opcional (nullable)
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000', // Adicionado
        ]);

        // 2. Gravamos no banco de dados incluindo a descrição
        $playlist = Playlist::create([
            'name' => $request->name,
            'description' => $request->description, // Adicionado
            'spotify_id' => uniqid('local_'), 
            'cover_url' => null,
        ]);

        return response()->json([
            'message' => 'Playlist criada com sucesso!',
            'playlist' => $playlist
        ], 201);
    }

    // Atualizar nome e descrição da playlist
    public function update(Request $request, $id)
    {
        $playlist = Playlist::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $playlist->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Playlist atualizada com sucesso!',
            'playlist' => $playlist
        ]);
    }

    // Excluir uma playlist
    public function destroy($id)
    {
        $playlist = Playlist::findOrFail($id);
        
        // Opcional: Se quiser deletar o vínculo com as músicas no banco, 
        // o Laravel fará automaticamente se houver cascade na migration.
        $playlist->delete();

        return response()->json([
            'message' => 'Playlist excluída com sucesso!'
        ]);
    }
}