FROM python:3.10-slim

WORKDIR /app

# Instala o ffmpeg (obrigatório para o yt-dlp extrair e converter áudio)
RUN apt-get update && apt-get install -y ffmpeg && rm -rf /var/lib/apt/lists/*

# Copia os requisitos e instala (podemos criar um requirements.txt vazio por enquanto)
RUN pip install --no-cache-dir fastapi uvicorn yt-dlp ytmusicapi

EXPOSE 5000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000", "--reload"]