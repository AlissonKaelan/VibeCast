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

class PlaylistQuery(BaseModel):
    url: str

@app.get("/")
def read_root():
    return {"status": "Microsservico Python Rodando", "service": "VibeCast Extractor"}

@app.post("/extract-audio")
def extract_audio(query: TrackQuery):
    search_query = f"{query.title} {query.artist}"
    search_results = ytmusic.search(query=search_query, filter="songs", limit=1)
    if not search_results:
        raise HTTPException(status_code=404, detail="Música não encontrada no YouTube Music")
    
    video_id = search_results[0]['videoId']
    youtube_url = f"https://www.youtube.com/watch?v={video_id}"
    
    ydl_opts = {
        'format': 'bestaudio/best',
        'quiet': True,
        'no_warnings': True,
        'simulate': True,
        'cookiefile': '/app/cookies.txt'
    }
    
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=False)
            return {
                "video_id": video_id, 
                "title": search_results[0]['title'],
                "artist": query.artist, 
                "youtube_url": youtube_url,
                "direct_audio_url": info.get('url'), 
                "duration_seconds": search_results[0].get('duration_seconds')
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao extrair áudio: {str(e)}")

@app.post("/import-playlist")
def import_playlist(query: PlaylistQuery):
    try:
        # 1. Identifica automaticamente se é playlist, album ou track
        match = re.search(r'(playlist|album|track)/([a-zA-Z0-9]+)', query.url)
        if not match:
            raise HTTPException(status_code=400, detail="Link inválido. Cole uma URL de música, álbum ou playlist do Spotify.")
        
        item_type = match.group(1) # 'playlist', 'album', ou 'track'
        item_id = match.group(2)

        embed_url = f"https://open.spotify.com/embed/{item_type}/{item_id}"

        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"
        }
        response = requests.get(embed_url, headers=headers)

        if response.status_code != 200:
            raise HTTPException(status_code=400, detail="O Spotify bloqueou a leitura da URL.")

        soup = BeautifulSoup(response.text, "html.parser")
        script_tag = soup.find("script", id="__NEXT_DATA__")
        if not script_tag:
            raise HTTPException(status_code=400, detail="JSON de músicas não encontrado na página.")

        state_data = json.loads(script_tag.string)
        extracted_tracks = []
        collection_name = "Importação VibeCast"

        def find_tracks(node):
            nonlocal collection_name
            if isinstance(node, dict):
                node_type = node.get('type')
                
                # Garante que o nome da coleção seja exato ao tipo que o usuário importou
                if node_type == item_type and 'name' in node:
                    collection_name = node.get('name')

                uri = node.get('uri', '')
                title = None
                artist = None

                # Verifica se é uma música (pelos dois formatos possíveis do Spotify)
                if uri.startswith('spotify:track:') or node_type == 'track':
                    # Formato A: Usado em Playlists e Álbuns
                    if 'title' in node and 'subtitle' in node:
                        title = node.get('title')
                        artist = node.get('subtitle')
                    # Formato B: Usado em Músicas Únicas (Tracks)
                    elif 'name' in node and 'artists' in node:
                        title = node.get('name')
                        artists_list = node.get('artists', [])
                        if isinstance(artists_list, list):
                            # Junta os nomes de todos os artistas com vírgula
                            artist = ", ".join([a.get('name', '') for a in artists_list if isinstance(a, dict)])

                if title and artist:
                    cover_url = None
                    if 'coverArt' in node and isinstance(node['coverArt'], dict):
                        sources = node['coverArt'].get('sources', [])
                        if sources and isinstance(sources, list):
                            cover_url = sources[0].get('url')

                    extracted_tracks.append({
                        "title": title,
                        "artist": artist,
                        "cover_url": cover_url,
                        "duration_seconds": 0,
                        "youtube_id": None
                    })

                for key, value in node.items():
                    find_tracks(value)
            elif isinstance(node, list):
                for item in node:
                    find_tracks(item)

        find_tracks(state_data)

        # Remove duplicatas caso o JSON repita informações
        unique_tracks = {t['title'] + t['artist']: t for t in extracted_tracks}.values()
        extracted_tracks = list(unique_tracks)

        return {
            "playlist_name": collection_name,
            "total_tracks": len(extracted_tracks),
            "tracks_urls": extracted_tracks,
            "message": f"Sucesso! {len(extracted_tracks)} músicas extraídas."
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao raspar o Spotify: {str(e)}")
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
            'sleep_interval': 2,
            'max_sleep_interval': 5
        }
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=True)
            ext = info.get('ext', 'm4a')
            db_file_path = f"musicas/{file_name}.{ext}"
            
            return {
                "success": True,
                "file_path": db_file_path
            }

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))