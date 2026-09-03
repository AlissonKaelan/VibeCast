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

        // AQUI ESTÁ A MÁGICA DA FILA! 
        // Em vez de baixar aqui, mandamos o ID para o nosso Trabalhador Invisível.
        \App\Jobs\DownloadAudioJob::dispatch($track->id);

        return response()->json([
            'success' => true,
            'message' => 'Download adicionado à fila!',
            'status' => 'processing'
        ], 202); // 202 Accepted significa "Recebi o pedido, mas vou processar depois"
    }

    public function downloadPlaylistTracks($playlistId)
    {
        $playlist = \App\Models\Playlist::with('tracks')->findOrFail($playlistId);
        $dispatchedCount = 0;

        foreach ($playlist->tracks as $track) {
            if (!$track->file_path) {
                // Despachamos o novo Job para cada música pendente
                \App\Jobs\DownloadAudioJob::dispatch($track->id);
                $dispatchedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Processamento iniciado. {$dispatchedCount} músicas foram adicionadas à fila de download."
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
        set_time_limit(300);

        $request->validate(['url' => 'required|url']);

        $response = \Illuminate\Support\Facades\Http::timeout(300)->post('http://python-extractor:5000/import-soundcloud', [
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
            if (empty($trackData['soundcloud_url'])) {
                continue;
            }

            $track = \App\Models\Track::firstOrCreate(
                ['youtube_id' => $trackData['soundcloud_url']],
                [
                    'title' => $trackData['title'],
                    'artist' => $trackData['artist'],
                    'cover_url' => $trackData['cover_url'] ?? null,
                    'duration_seconds' => $trackData['duration_seconds'] ?? 0,
                ]
            );
            
            $playlist->tracks()->syncWithoutDetaching([$track->id]);
        }

        return response()->json(['message' => $data['message'], 'playlist' => $playlist]);
    }

    /**
     * Retorna o estado atualizado das músicas de uma playlist para o Vue.js espiar
     */
    public function getPlaylistStatus($id)
    {
        // Se o ID for 'all', buscamos o status de todas as músicas do sistema
        if ($id === 'all') {
            $tracks = \App\Models\Track::select('id', 'file_path', 'duration_seconds')->get();
        } else {
            $playlist = \App\Models\Playlist::with(['tracks' => function($query) {
                $query->select('tracks.id', 'file_path', 'duration_seconds');
            }])->findOrFail($id);
            $tracks = $playlist->tracks;
        }

        return response()->json([
            'success' => true,
            'tracks' => $tracks
        ]);
    }

    /**
     * Atualiza o Título e o Artista de uma música
     */
    public function updateTrack(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
        ]);

        $track = \App\Models\Track::findOrFail($id);
        $track->update([
            'title' => $request->title,
            'artist' => $request->artist
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Música atualizada com sucesso!', 
            'track' => $track
        ]);
    }

    /**
     * Exclui uma música do banco de dados e apaga o arquivo de áudio do disco
     */
    public function deleteTrack($id)
    {
        $track = \App\Models\Track::findOrFail($id);

        // Se a música já foi baixada, apaga o arquivo físico (.m4a) do disco!
        if ($track->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($track->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($track->file_path);
        }

        // Apaga o registo do banco de dados
        $track->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Música excluída permanentemente!'
        ]);
    }

    public function wakeServices()
    {
        $containers = ['vibecast-python-extractor-1', 'vibecast-queue-worker'];
        $debug = [];

        foreach ($containers as $container) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/docker.sock');
            curl_setopt($ch, CURLOPT_URL, "http://localhost/containers/{$container}/start");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            
            // O Segredo: "Content-Type:" vazio arranca o cabeçalho intrometido do cURL
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:', 'Content-Length: 0']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ""); // Corpo estritamente nulo
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
            $debug[$container] = "Status {$httpCode}";
            curl_close($ch);
        }

        return response()->json(['status' => 'online', 'debug' => $debug]);
    }

    public function sleepServices()
    {
        $containers = ['vibecast-python-extractor-1', 'vibecast-queue-worker'];
        
        foreach ($containers as $container) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/docker.sock');
            // Altera a rota da API do Docker para /stop
            curl_setopt($ch, CURLOPT_URL, "http://localhost/containers/{$container}/stop");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:', 'Content-Length: 0']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }

        return response()->json(['status' => 'offline', 'message' => 'Serviços adormecidos. Hardware liberado!']);
    }
}
