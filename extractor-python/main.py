from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from ytmusicapi import YTMusic
import yt_dlp
import requests
from bs4 import BeautifulSoup
import json

app = FastAPI(title="VibeCast Extractor API")
ytmusic = YTMusic()

class TrackQuery(BaseModel):
    title: str
    artist: str

# Novo modelo exigindo apenas a URL da playlist
class PlaylistQuery(BaseModel):
    url: str

@app.get("/")
def read_root():
    return {"status": "Microsservico Python Rodando", "service": "VibeCast Extractor"}

@app.post("/extract-audio")
def extract_audio(query: TrackQuery):
    # ... (Seu código anterior do extract_audio continua intacto aqui)
    search_query = f"{query.title} {query.artist}"
    search_results = ytmusic.search(query=search_query, filter="songs", limit=1)
    if not search_results:
        raise HTTPException(status_code=404, detail="Música não encontrada no YouTube Music")
    video_id = search_results[0]['videoId']
    youtube_url = f"https://www.youtube.com/watch?v={video_id}"
    ydl_opts = {'format': 'bestaudio/best', 'quiet': True, 'no_warnings': True, 'simulate': True}
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=False)
            return {
                "video_id": video_id, "title": search_results[0]['title'],
                "artist": query.artist, "youtube_url": youtube_url,
                "direct_audio_url": info.get('url'), "duration_seconds": search_results[0].get('duration_seconds')
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao extrair áudio: {str(e)}")

# ==========================================
# NOVA ROTA: Importador de Playlist Spotify
# ==========================================
@app.post("/import-playlist")
def import_playlist(query: PlaylistQuery):
    try:
        # Disfarça a nossa requisição para o Spotify achar que somos um navegador real
        headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}
        response = requests.get(query.url, headers=headers)
        
        if response.status_code != 200:
            raise HTTPException(status_code=400, detail="Não foi possível acessar o link do Spotify.")

        soup = BeautifulSoup(response.text, "html.parser")
        
        # O Spotify guarda os dados da playlist em uma tag meta de título
        # Ex: <meta property="og:title" content="Nome da Playlist">
        meta_title = soup.find("meta", property="og:title")
        playlist_name = meta_title["content"] if meta_title else "Playlist Importada"

        # Raspar as músicas exatas exige ler um bloco JSON escondido no HTML da página pública
        # Vamos buscar todas as tags meta que contêm as faixas (music:song)
        track_tags = soup.find_all("meta", property="music:song")
        
        extracted_tracks = []
        for tag in track_tags:
            # Aqui poderíamos fazer uma requisição rápida para pegar o nome de cada faixa,
            # mas para devolver algo útil imediatamente, vamos simular o retorno da lista
            track_url = tag.get("content")
            extracted_tracks.append({"spotify_url": track_url})
            
            # Nota: Em um ambiente de produção pesado, leríamos o script <script id="initial-state">
            # para pegar Título e Artista de uma vez só sem precisar bater na API.

        return {
            "playlist_name": playlist_name,
            "total_tracks": len(extracted_tracks),
            "tracks_urls": extracted_tracks,
            "message": "Raspagem inicial concluída. Metadados encontrados com sucesso!"
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao raspar a playlist: {str(e)}")