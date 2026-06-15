from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from ytmusicapi import YTMusic
import yt_dlp
import requests
from bs4 import BeautifulSoup
import json
import os
import re

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
    ydl_opts = {'format': 'bestaudio/best', 'quiet': True, 'no_warnings': True, 'simulate': True, 'cookiefile': '/app/cookies.txt'}
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
# NOVA ROTA: Importador Blindado (Via Iframe)
# ==========================================
@app.post("/import-playlist")
def import_playlist(query: PlaylistQuery):
    try:
        # 1. Extrai apenas o ID mágico da playlist da URL colada
        # Ex: https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M
        match = re.search(r'playlist/([a-zA-Z0-9]+)', query.url)
        if not match:
            raise HTTPException(status_code=400, detail="Link inválido. Precisa ser uma URL de playlist do Spotify.")
        
        playlist_id = match.group(1)

        # 2. O GRANDE TRUQUE: Acessar a versão Iframe (Embed) descoberta por você!
        embed_url = f"https://open.spotify.com/embed/playlist/{playlist_id}"

        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
        }
        response = requests.get(embed_url, headers=headers)

        if response.status_code != 200:
            raise HTTPException(status_code=400, detail="O Spotify bloqueou a leitura do Iframe.")

        soup = BeautifulSoup(response.text, "html.parser")

        # 3. O Iframe guarda as músicas num script limpo chamado __NEXT_DATA__
        script_tag = soup.find("script", id="__NEXT_DATA__")
        if not script_tag:
            raise HTTPException(status_code=400, detail="JSON de músicas não encontrado no Iframe.")

        # Carrega o JSON gigante
        state_data = json.loads(script_tag.string)

        extracted_tracks = []
        playlist_name = "Playlist Importada"

        # 4. A nossa Caçadora entra em ação no JSON do Iframe
        def find_tracks(node):
            nonlocal playlist_name
            if isinstance(node, dict):
                # Captura o nome oficial da playlist (isso já estava funcionando)
                if node.get('type') == 'playlist' and 'name' in node:
                    playlist_name = node.get('name')

                # ----------------------------------------------------
                # O NOVO SEGREDO: Como o Iframe guarda as músicas!
                # ----------------------------------------------------
                uri = node.get('uri', '') # Pegamos a URI do Spotify
                
                # Só é música se a URI começar com 'spotify:track:'
                if 'title' in node and 'subtitle' in node and uri.startswith('spotify:track:'):
                    title = node.get('title')
                    artist = node.get('subtitle') 
                    cover_url = None
                    duration = 0 

                    # Pega a capa do álbum
                    if 'coverArt' in node:
                        sources = node['coverArt'].get('sources', [])
                        if sources:
                            cover_url = sources[0].get('url')

                    # Adiciona a música na nossa lista de extração
                    if title:
                        extracted_tracks.append({
                            "title": title,
                            "artist": artist,
                            "cover_url": cover_url,
                            "duration_seconds": duration,
                            "youtube_id": None
                        })

                # Continua cavando no JSON
                for key, value in node.items():
                    find_tracks(value)
            elif isinstance(node, list):
                for item in node:
                    find_tracks(item)

        # Dispara a busca
        find_tracks(state_data)

        # Filtra músicas repetidas
        unique_tracks = {t['title'] + t['artist']: t for t in extracted_tracks}.values()
        extracted_tracks = list(unique_tracks)

        return {
            "playlist_name": playlist_name,
            "total_tracks": len(extracted_tracks),
            "tracks_urls": extracted_tracks,
            "message": f"Sucesso! {len(extracted_tracks)} músicas extraídas via Iframe."
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao raspar a playlist: {str(e)}")


# ==========================================
# NOVA ROTA: Download Físico de Música
# ==========================================
class DownloadQuery(BaseModel):
    title: str
    artist: str

@app.post("/download-track")
def download_track(query: DownloadQuery):
    try:
        search_query = f"{query.title} {query.artist}"
        search_results = ytmusic.search(query=search_query, filter="songs", limit=1)
        
        if not search_results:
            raise HTTPException(status_code=404, detail="Música não encontrada.")
            
        video_id = search_results[0]['videoId']
        youtube_url = f"https://www.youtube.com/watch?v={video_id}"
        
        safe_title = re.sub(r'[^a-zA-Z0-9]', '_', query.title)
        safe_artist = re.sub(r'[^a-zA-Z0-9]', '_', query.artist)
        file_name = f"{safe_artist}_{safe_title}_{video_id}".lower()
        output_template = f"/app/musicas/{file_name}.%(ext)s"
        
        ydl_opts = {
            'format': 'bestaudio[ext=m4a]/bestaudio/best',
            'outtmpl': output_template,
            'quiet': False,
            'no_warnings': True,
            'cookiefile': '/app/cookies.txt', 
            'user_agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            'sleep_interval': 5,        
            'max_sleep_interval': 15,
        }
        
        # Bloco de execução único e limpo
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=True)
            ext = info.get('ext', 'm4a')
            db_file_path = f"musicas/{file_name}.{ext}"
            
            return {
                "success": True,
                "file_path": db_file_path
            }

    except Exception as e:
        # Aqui o erro é enviado para o Laravel com o detalhe do yt-dlp
        raise HTTPException(status_code=500, detail=str(e))