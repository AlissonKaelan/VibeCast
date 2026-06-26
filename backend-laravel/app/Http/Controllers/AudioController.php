<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\DownloadTrackJob;
use App\Models\Playlist;

class AudioController extends Controller
{
    /**
     * Solicita ao microsserviço Python o link direto do áudio.
     */
    public function getStreamUrl(Request $request)
    {
        // 1. Valida se o frontend enviou o título e o artista corretamente
        $request->validate([
            'title' => 'required|string',
            'artist' => 'required|string',
        ]);

        try {
            // 2. Faz a requisição HTTP interna para o contêiner do Python
            // Repare no HOST: usamos 'python-extractor' que é o nome do serviço no docker-compose
            $response = Http::post('http://python-extractor:5000/extract-audio', [
                'title' => $request->title,
                'artist' => $request->artist,
            ]);

            // 3. Se o Python responder com erro, repassa o erro
            if ($response->failed()) {
                return response()->json([
                    'error' => 'Não foi possível obter o áudio da música.',
                    'details' => $response->json()
                ], $response->status());
            }

            // 4. Retorna os dados de sucesso organizados para o nosso frontend
            return response()->json($response->json());

        } catch (\Exception $e) {
            Log::error('Erro ao conectar com o extrator Python: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Erro interno na comunicação dos serviços de backend.'
            ], 500);
        }
    }

    public function downloadTrack(Request $request, $id)
    {
        $track = \App\Models\Track::findOrFail($id);

        if ($track->file_path) {
            return response()->json([
                'success' => true,
                'message' => 'A música já estava baixada.',
                'file_path' => $track->file_path
            ]);
        }

        // A MÁGICA DA DECISÃO
        // Se o youtube_id for um link (começa com http), é SoundCloud!
        if (str_starts_with($track->youtube_id ?? '', 'http')) {
            $response = \Illuminate\Support\Facades\Http::retry(3, 1000)
                ->post('http://python-extractor:5000/download-direct', [
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'url' => $track->youtube_id
                ]);
        } else {
            // Se não, pesquisa e baixa do YouTube Music
            $response = \Illuminate\Support\Facades\Http::retry(3, 1000)
                ->post('http://python-extractor:5000/download-track', [
                    'title' => $track->title,
                    'artist' => $track->artist
                ]);
        }

        if ($response->failed()) {
            $errorDetail = $response->json('detail') ?? 'Erro desconhecido no Python';
            return response()->json(['error' => $errorDetail], 500);
        }

        $data = $response->json();

        $caminhoSujo = $data['file_path'];
        $caminhoLimpo = str_replace('/app/', '', $caminhoSujo);

        $track->file_path = $caminhoLimpo;
        $track->save();

        return response()->json([
            'success' => true,
            'message' => 'Download concluído e salvo no banco!',
            'file_path' => $caminhoLimpo
        ]);
    }

    public function downloadPlaylistTracks($playlistId)
    {
        // 1. Pega a playlist e todas as músicas dela
        $playlist = \App\Models\Playlist::with('tracks')->findOrFail($playlistId);
        $dispatchedCount = 0;

        // 2. Passa por cada música
        foreach ($playlist->tracks as $track) {
            // Se a música ainda não foi baixada, manda para a Fila (Background)
            if (!$track->file_path) {
                \App\Jobs\DownloadTrackJob::dispatch($track);
                $dispatchedCount++;
            }
        }

        // 3. Responde IMEDIATAMENTE ao Vue.js (Código 202 Accepted = Processando em segundo plano)
        return response()->json([
            'success' => true,
            'message' => "Processamento iniciado. $dispatchedCount músicas foram adicionadas à fila de download."
        ], 202);
    }

    public function streamTrack(Request $request)
    {
        // Verifica se o caminho foi enviado
        $path = $request->query('path');
        if (!$path) {
            return response()->json(['error' => 'Caminho não fornecido'], 400);
        }

        // Caminho absoluto do ficheiro no disco do Docker
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists($fullPath)) {
            abort(404, 'Ficheiro de áudio não encontrado.');
        }

        // O response()->file() do Laravel suporta nativamente HTTP 206 (Byte-Range)
        // É isto que permite à barra de progresso saltar para qualquer parte da música!
        return response()->file($fullPath);
    }

    public function importPlaylist(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            // 1. Pede para o Python raspar o Spotify
            $response = \Illuminate\Support\Facades\Http::post('http://python-extractor:5000/import-playlist', [
                'url' => $request->url
            ]);

            if ($response->successful()) {
                $data = $response->json();

                
                // 2. Cria a nova Playlist no banco
                $playlist = \App\Models\Playlist::create([
                    'name' => $data['playlist_name'],
                    'description' => 'Importada via VibeCast'
                ]);

                // 3. Salva todas as músicas dentro desta playlist
                foreach ($data['tracks_urls'] as $trackData) {
                    
                    // Verifica se já existe uma música com este exato Título e Artista.
                    // Se existir, pega nela. Se não existir, cria uma nova.
                    $track = \App\Models\Track::firstOrCreate(
                        [
                            'title' => $trackData['title'],
                            'artist' => $trackData['artist']
                        ],
                        [
                            'cover_url' => $trackData['cover_url'],
                            'duration_seconds' => $trackData['duration_seconds'] ?? 0
                        ]
                    );

                    // Associa a música (nova ou já existente) a esta playlist.
                    // O 'syncWithoutDetaching' garante que a música não seja adicionada em duplicado à mesma playlist.
                    $playlist->tracks()->syncWithoutDetaching([$track->id]);
                }

                // 4. Retorna a mensagem de sucesso para o Vue.js
                return response()->json([
                    'message' => "Sucesso! {$data['total_tracks']} músicas salvas no banco.",
                    'playlist' => $playlist
                ]);
            }

            return response()->json([
                'error' => $response->json('detail') ?? 'Falha ao importar playlist'
            ], $response->status());

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao salvar playlist: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        }
    }
    public function exportPlaylist($id)
    {
        // 1. Procura a playlist e as suas músicas
        $playlist = \App\Models\Playlist::with('tracks')->findOrFail($id);
        
        // 2. Cria o ficheiro ZIP temporário
        $zip = new \ZipArchive();
        // Remove espaços e acentos do nome da playlist para não dar erro no Windows
        $safePlaylistName = preg_replace('/[^A-Za-z0-9\-]/', '_', $playlist->name);
        $zipFileName = 'VibeCast_' . $safePlaylistName . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $hasFiles = false;

            // 3. Adiciona as músicas baixadas ao ZIP
            foreach ($playlist->tracks as $track) {
                // Só adiciona se a música já tiver sido baixada (file_path preenchido e ficheiro real existir)
                if ($track->file_path && \Storage::disk('public')->exists($track->file_path)) {
                    $absolutePath = \Storage::disk('public')->path($track->file_path);
                    
                    // Formata o nome bonito para o pendrive: "Artista - Nome da Musica.m4a"
                    $safeTitle = preg_replace('/[^A-Za-z0-9 \-\.]/', '', $track->title);
                    $safeArtist = preg_replace('/[^A-Za-z0-9 \-\.]/', '', $track->artist);
                    $fileName = $safeArtist . ' - ' . $safeTitle . '.m4a';
                    
                    $zip->addFile($absolutePath, $fileName);
                    $hasFiles = true;
                }
            }
            $zip->close();

            // 4. Se não havia nenhuma música baixada na playlist, avisa o utilizador
            if (!$hasFiles) {
                return response()->json(['error' => 'Nenhuma música desta playlist foi descarregada ainda.'], 400);
            }

            // 5. Envia para o navegador e apaga o ZIP do servidor a seguir (para poupar espaço)
            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        return response()->json(['error' => 'Erro ao criar o ficheiro ZIP.'], 500);
    }

    public function importSoundcloud(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $response = \Illuminate\Support\Facades\Http::post('http://python-extractor:5000/import-soundcloud', [
            'url' => $request->url
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Erro ao raspar o SoundCloud no Python'], 500);
        }

        $data = $response->json();
        
        $playlist = \App\Models\Playlist::create([
            'name' => $data['playlist_name'],
            'description' => 'Importado do SoundCloud'
        ]);

        foreach ($data['tracks_urls'] as $trackData) {
            $track = \App\Models\Track::firstOrCreate(
                ['title' => $trackData['title'], 'artist' => $trackData['artist']],
                [
                    'cover_url' => $trackData['cover_url'] ?? null,
                    'duration_seconds' => $trackData['duration_seconds'] ?? 0,
                    // O SEGREDO: Guardamos o link do SoundCloud na coluna do YouTube
                    'youtube_id' => $trackData['soundcloud_url'] ?? null 
                ]
            );
            $playlist->tracks()->attach($track->id);
        }

        return response()->json(['message' => $data['message'], 'playlist' => $playlist]);
    }
}
