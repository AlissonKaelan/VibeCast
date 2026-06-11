<?php

namespace App\Jobs;

use App\Models\Track;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadTrackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $track;
    
    // Aumentamos o limite de tempo para 5 minutos para garantir que músicas longas não quebrem
    public $timeout = 300; 

    public function __construct(Track $track)
    {
        $this->track = $track;
    }

    public function handle(): void
    {
        // 1. Verificação de segurança: Se a música já tem arquivo, não baixa de novo
        if ($this->track->file_path) {
            return; 
        }

        try {
            // 2. Chama o Python (aumentamos o timeout da requisição HTTP também para 5 minutos)
            $response = Http::timeout(300)->post('http://python-extractor:5000/download-track', [
                'title' => $this->track->title,
                'artist' => $this->track->artist
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // 3. Salva no banco de dados e avisa o sistema que terminou
                $this->track->file_path = $data['file_path'];
                $this->track->save();
                
                Log::info("Sucesso! Música '{$this->track->title}' baixada via Fila.");
            } else {
                Log::error("Erro no Python ao baixar ID {$this->track->id}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Falha na Fila para a música ID {$this->track->id}: " . $e->getMessage());
        }
    }
}