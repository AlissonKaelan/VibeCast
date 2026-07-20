#!/bin/bash
echo "============================================="
echo " Verificando atualizações de segurança..."
echo "============================================="

# 1. Cria a flag (tranca a porta) para o Laravel saber que estamos ocupados
touch /app/is_updating.flag

# 2. Atualiza o yt-dlp e o pytube silenciosamente para evitar logs gigantes
pip install --upgrade yt-dlp pytube

# 3. Remove a flag (destranca a porta)
rm -f /app/is_updating.flag

echo " Atualização concluída! Iniciando a API VibeCast Extractor..."
echo "============================================="

# 4. Inicia o servidor FastAPI (o comando que ficava no Dockerfile)
exec uvicorn main:app --host 0.0.0.0 --port 5000