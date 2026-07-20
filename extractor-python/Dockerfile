FROM python:3.10-slim

WORKDIR /app

# Instala o ffmpeg (obrigatório para o yt-dlp extrair e converter áudio)
RUN apt-get update && apt-get install -y ffmpeg && rm -rf /var/lib/apt/lists/*

# Copia os requisitos e instala
RUN pip install --no-cache-dir fastapi uvicorn yt-dlp ytmusicapi requests beautifulsoup4

EXPOSE 5000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000", "--reload"]

# Copie o entrypoint e dê permissão de execução
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
# Expor a porta 5000
EXPOSE 5000
# Use o script como ponto de partida
ENTRYPOINT ["/entrypoint.sh"]