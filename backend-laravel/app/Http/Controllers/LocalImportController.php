<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use getID3;

class LocalImportController extends Controller
{
    public function scanLocalFolder()
    {
        $directory = base_path('importacao_local'); 
        
        if (!is_dir($directory)) {
            return response()->json(['error' => 'Pasta de importacao local não encontrada no servidor.'], 404);
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        $foundTracks = [];
        $getID3 = new getID3;

        foreach ($files as $file) {
            // Filtra apenas arquivos de áudio
            if (preg_match('/\.(mp3|m4a|wav|flac)$/i', $file)) {
                $path = $directory . '/' . $file;
                
                $fileInfo = $getID3->analyze($path);
                
                // Tenta extrair a tag ID3v2, depois a ID3v1, e se falhar usa o próprio nome do arquivo
                $title = $fileInfo['tags']['id3v2']['title'][0] ?? $fileInfo['tags']['id3v1']['title'][0] ?? pathinfo($file, PATHINFO_FILENAME);
                $artist = $fileInfo['tags']['id3v2']['artist'][0] ?? $fileInfo['tags']['id3v1']['artist'][0] ?? 'Artista Desconhecido';

                $foundTracks[] = [
                    'file_name' => $file,
                    'title' => $title,
                    'artist' => $artist,
                    'format' => $fileInfo['fileformat'] ?? 'unknown',
                    'playtime_seconds' => is_array($fileInfo['playtime_seconds'] ?? 0) ? current($fileInfo['playtime_seconds']) : ($fileInfo['playtime_seconds'] ?? 0)
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total' => count($foundTracks),
            'tracks' => $foundTracks
        ]);
    }

    public function processLocalImport()
    {
        $directory = base_path('importacao_local');
        $destinationFolder = storage_path('app/public/musicas');

        if (!is_dir($directory)) {
            return response()->json(['error' => 'Pasta de importacao local não encontrada.'], 404);
        }

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0755, true);
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        $importedTracks = [];
        $getID3 = new getID3;

        foreach ($files as $file) {
            if (preg_match('/\.(mp3|m4a|wav|flac)$/i', $file)) {
                $sourcePath = $directory . '/' . $file;
                $fileInfo = $getID3->analyze($sourcePath);

                $title = $fileInfo['tags']['id3v2']['title'][0] ?? $fileInfo['tags']['id3v1']['title'][0] ?? pathinfo($file, PATHINFO_FILENAME);
                $artist = $fileInfo['tags']['id3v2']['artist'][0] ?? $fileInfo['tags']['id3v1']['artist'][0] ?? 'Artista Desconhecido';
                
                $durationBruta = is_array($fileInfo['playtime_seconds'] ?? 0) ? current($fileInfo['playtime_seconds']) : ($fileInfo['playtime_seconds'] ?? 0);
                $duration = round($durationBruta);

                // Limpa nomes sujos para evitar erros no sistema de arquivos do Linux
                $safeTitle = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower(trim($title)));
                $safeArtist = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower(trim($artist)));
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                
                $newFileName = time() . "_{$safeArtist}_{$safeTitle}.{$ext}";
                $destinationPath = $destinationFolder . '/' . $newFileName;

                if (rename($sourcePath, $destinationPath)) {
                    $track = \App\Models\Track::create([
                        'title' => $title,
                        'artist' => $artist,
                        'file_path' => 'musicas/' . $newFileName,
                        'duration_seconds' => $duration
                    ]);

                    $importedTracks[] = $track;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($importedTracks) . ' músicas importadas fisicamente com sucesso!',
            'tracks' => $importedTracks
        ]);
    }

    public function uploadWebFiles(Request $request)
    {
        if (!$request->hasFile('files')) {
            return response()->json(['error' => 'Nenhum arquivo enviado.'], 400);
        }

        $importedTracks = [];
        $getID3 = new \getID3;
        $destinationFolder = storage_path('app/public/musicas');

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0755, true);
        }

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $tempPath = $file->getPathname();

            // Extrai os metadados internos
            $fileInfo = $getID3->analyze($tempPath);

            $title = $fileInfo['tags']['id3v2']['title'][0] ?? $fileInfo['tags']['id3v1']['title'][0] ?? null;
            $artist = $fileInfo['tags']['id3v2']['artist'][0] ?? $fileInfo['tags']['id3v1']['artist'][0] ?? null;

            if (!$title || !$artist) {
                $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
                $parts = explode(' - ', $nameWithoutExt, 2);
                
                if (count($parts) === 2) {
                    $artist = $artist ?? trim($parts[0]);
                    $title = $title ?? trim($parts[1]);
                } else {
                    $title = $title ?? $nameWithoutExt;
                    $artist = $artist ?? 'Artista Desconhecido';
                }
            }

            $durationBruta = is_array($fileInfo['playtime_seconds'] ?? 0) ? current($fileInfo['playtime_seconds']) : ($fileInfo['playtime_seconds'] ?? 0);
            $duration = round($durationBruta);

            $safeTitle = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower(trim($title)));
            $safeArtist = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower(trim($artist)));
            $ext = $file->getClientOriginalExtension();

            // uniqid() garante que arquivos com nomes idênticos enviados no mesmo segundo não colidam
            $newFileName = time() . '_' . uniqid() . "_{$safeArtist}_{$safeTitle}.{$ext}";
            $file->move($destinationFolder, $newFileName);

            $track = \App\Models\Track::create([
                'title' => $title,
                'artist' => $artist,
                'file_path' => 'musicas/' . $newFileName,
                'duration_seconds' => $duration
            ]);

            $importedTracks[] = $track;
        }

        return response()->json([
            'success' => true,
            'message' => count($importedTracks) . ' músicas enviadas e importadas com sucesso!',
            'tracks' => $importedTracks
        ]);
    }
}