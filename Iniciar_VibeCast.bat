@echo off
title VibeCast - Servidor (Docker)
color 0B

:: Move silenciosamente para o drive C: para evitar avisos de UNC do Windows
cd /d C:\

echo ===================================================
echo             INICIANDO O VIBECAST...
echo ===================================================
echo.

:: Define variaveis
set WSL_DISTRO=Ubuntu
set WSL_PATH=/home/alissonkaelan/Projetos/VibeCast

:: 1. Inicia TODOS os conteineres (Backend, Banco, Python e agora o Frontend)
echo [1/3] Ligando os motores no Docker...
wsl -d %WSL_DISTRO% --cd %WSL_PATH% -e docker compose up -d
echo.

:: 2. Pausa para o Vite (Node 20) baixar dependencias e ligar
echo [2/3] Preparando a interface e o banco de dados...
timeout /t 8 /nobreak > NUL
echo.

:: 3. Abre o navegador padrao do Windows apontando para o Vue
echo [3/3] Abrindo o VibeCast...
start chrome --app=http://localhost:5173

echo.
echo ===================================================
echo     VIBECAST ESTA RODANDO! PODE OUVIR MUSICA!
echo ===================================================
echo.
echo Para DESLIGAR o servidor completamente, pressione qualquer tecla aqui...
pause > NUL

:: Quando o utilizador pressionar uma tecla, desliga tudo
echo.
echo Encerrando e desligando o Docker...
wsl -d %WSL_DISTRO% --cd %WSL_PATH% -e docker compose down

echo.
echo VibeCast desligado com sucesso! Ate a proxima.
timeout /t 3 /nobreak > NUL