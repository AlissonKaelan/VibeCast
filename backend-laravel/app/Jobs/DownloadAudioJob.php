<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Track;
use Illuminate\Support\Facades\Log;

class DownloadAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $trackId;
    public $timeout = 300; // 5 minutos de tolerância para o download

    /**
     * Create a new job instance.
     */
    public function __construct($trackOrId)
    {
        // Extrai apenas o número do ID, quer o Controller envie o número direto ou a música inteira
        $this->trackId = is_object($trackOrId) ? $trackOrId->id : (is_array($trackOrId) ? $trackOrId['id'] : $trackOrId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processando Job de Download para a Track ID: " . $this->trackId);
        
        $track = Track::find($this->trackId);

        if (!$track) {
            Log::error("Track ID {$this->trackId} não encontrada no banco.");
            return;
        }

        if ($track->file_path) {
            Log::info("Track ID {$this->trackId} já baixada. Pulando.");
            return; // Já está baixado
        }

        try {
            // A API de extração Python roda na porta 5000 do container python-extractor
            $pythonApiUrl = 'http://python-extractor:5000'; 
            
            // Decisão: Download direto (Soundcloud) ou Pesquisa (YouTube)
            if ($track->youtube_id && str_contains($track->youtube_id, 'soundcloud.com')) {
                 $response = Http::timeout(300)->post("{$pythonApiUrl}/download-direct", [
                    'url' => $track->youtube_id,
                    'track_id' => $track->id
                ]);
            } else {
                 $response = Http::timeout(300)->post("{$pythonApiUrl}/download-track", [
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'track_id' => $track->id
                ]);
            }

            if ($response->successful()) {
                $data = $response->json();
                
                // Atualiza o banco com o arquivo físico e duração real
                $track->update([
                    'file_path' => $data['file_path'],
                    'duration_seconds' => $data['duration_seconds'] ?? $track->duration_seconds,
                ]);

                Log::info("Job concluído! Track ID {$this->trackId} baixada com sucesso.");
            } else {
                Log::error("Erro do Python no Job da Track ID {$this->trackId}: " . $response->body());
                // Lança exceção para o Laravel saber que o Job falhou e tentar novamente depois
                throw new \Exception("Falha ao baixar do Python: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Exceção no Job de Download (Track ID: {$this->trackId}): " . $e->getMessage());
            throw $e;
        }
    }
}