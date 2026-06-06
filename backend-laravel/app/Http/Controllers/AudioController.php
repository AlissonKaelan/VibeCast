<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
}