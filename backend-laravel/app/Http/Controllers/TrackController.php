<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Track;

class TrackController extends Controller
{

    public function index()
    {
        // Devolve todas as músicas, das mais recentes para as mais antigas
        return response()->json([
            'tracks' => Track::orderBy('created_at', 'desc')->get()
        ]);
    }

    public function move(Request $request, $id)
    {
        try {
            $request->validate([
                'playlist_id' => 'required|exists:playlists,id'
            ]);

            $track = Track::findOrFail($id);
            
            // Adiciona a música à playlist usando a tabela pivô (sem duplicar)
            $track->playlists()->syncWithoutDetaching([$request->playlist_id]);

            return response()->json([
                'message' => 'Música adicionada à playlist com sucesso!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetFile($id)
    {
        $track = Track::findOrFail($id);
        $track->file_path = null;
        $track->save();

        return response()->json(['message' => 'Registro corrigido. Pronto para baixar novamente.']);
    }
}