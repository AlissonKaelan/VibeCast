@echo off
title VibeCast - Servidor (Docker)
color 0B

:: Evita o erro de caminho UNC irritante do Windows
cd /d C:\

:: ===================================================
:: TRUQUE ANTI-32 BITS (Ponte Sysnative)
:: ===================================================
set WSL_CMD=wsl
set PS_CMD=powershell
if exist "%windir%\sysnative\wsl.exe" set WSL_CMD="%windir%\sysnative\wsl.exe"
if exist "%windir%\sysnative\WindowsPowerShell\v1.0\powershell.exe" set PS_CMD="%windir%\sysnative\WindowsPowerShell\v1.0\powershell.exe"

echo ===================================================
echo             INICIANDO O VIBECAST...
echo ===================================================
echo.

:: Define a distro padrao
set WSL_DISTRO=Ubuntu

:: 0. Verifica e inicia o Docker Desktop
echo [0/3] Checando status do Docker Desktop...
tasklist /FI "IMAGENAME eq Docker Desktop.exe" 2>NUL | find /I /N "Docker Desktop.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo O Docker Desktop esta desligado. Iniciando...
    %PS_CMD% -Command "Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe' -WindowStyle Minimized"
    echo Aguardando o motor do Docker aquecer ^(isso pode levar alguns segundos^)...
) else (
    echo Docker Desktop ja esta rodando!
)

:: Loop de verificacao silencioso
:wait_docker
%WSL_CMD% -d %WSL_DISTRO% -e docker info >NUL 2>&1
if %ERRORLEVEL% NEQ 0 (
    timeout /t 3 /nobreak > NUL
    goto wait_docker
)
echo Motor do Docker operante!
echo.

:: 1. Inicia os conteineres base (Modo Offline)
echo [1/3] Ligando o nucleo do VibeCast...
%WSL_CMD% -d %WSL_DISTRO% -e bash -c "cd ~/Projetos/VibeCast && docker compose up --no-start && docker compose start db app frontend"
echo.

:: 2. Pausa para o Vite
echo [2/3] Preparando a interface e o banco de dados...
timeout /t 8 /nobreak > NUL
echo.

:: 3. Abre o navegador isolado 
echo [3/3] Abrindo o VibeCast...
start chrome --app=http://localhost:5173 --user-data-dir="%TEMP%\VibeCastProfile"

echo.
echo ===================================================
echo     VIBECAST ESTA RODANDO! PODE OUVIR MUSICA!
echo ===================================================
echo.
echo O sistema esta monitorando a janela. Feche o VibeCast para desligar o servidor...

:: Loop inteligente que vigia o Chrome
:monitor
%PS_CMD% -NoProfile -Command "if (Get-CimInstance Win32_Process -Filter \"Name='chrome.exe'\" | Where-Object { $_.CommandLine -like '*VibeCastProfile*' }) { exit 0 } else { exit 1 }"
if "%ERRORLEVEL%"=="0" (
    timeout /t 3 /nobreak > NUL
    goto monitor
)

:: Se chegou aqui, a janela foi fechada!
echo.
echo Janela fechada detectada! Encerrando e destruindo os conteineres do VibeCast...
%WSL_CMD% -d %WSL_DISTRO% -e bash -c "cd ~/Projetos/VibeCast && docker compose down"

:: (Libera 100% de RAM e CPU)
echo Desligando o Motor do Docker...
taskkill /F /IM "Docker Desktop.exe" /T > NUL 2>&1

echo Destruindo a Maquina Virtual (VmmemWSL)...
%WSL_CMD% --shutdown

echo VibeCast desligado com sucesso!
timeout /t 3 /nobreak > NUL

:: Fecha o executavel no gerenciador de tarefas
exit