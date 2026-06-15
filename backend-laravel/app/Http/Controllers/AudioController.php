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

    public function downloadTrack($id)
    {
        // 1. Busca a música no banco de dados
        $track = \App\Models\Track::findOrFail($id);

        // 2. Se já tiver o arquivo, não baixa de novo!
        if ($track->file_path) {
            return response()->json([
                'message' => 'A música já está salva no seu PC!',
                'file_path' => asset('storage/' . $track->file_path)
            ]);
        }

        // 3. Pede para o Python fazer o trabalho pesado
        $response = \Illuminate\Support\Facades\Http::retry(3, 1000)
            ->post('http://python-extractor:5000/download-track', [
                'title' => $track->title,
                'artist' => $track->artist
            ]);

        if ($response->failed()) {
            // Captura a mensagem de erro detalhada vinda do Python
            $errorDetail = $response->json('detail') ?? 'Erro desconhecido no Python';
            return response()->json(['error' => $errorDetail], 500);
        }

        $data = $response->json();

        // 4. Salva o caminho do arquivo físico no Banco de Dados
        $track->file_path = $data['file_path'];
        $track->save();

        return response()->json([
            'success' => true,
            'message' => 'Download concluído e salvo no banco!',
            'file_path' => asset('storage/' . $track->file_path)
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
}