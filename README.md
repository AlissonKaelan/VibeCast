# **VibeCast** 🎵

O VibeCast é uma plataforma de streaming de áudio independente e de código aberto. A aplicação separa a interface visual da fonte real da mídia, utilizando links públicos do Spotify para importar metadados (como uma prateleira) e extraindo o áudio puro diretamente do YouTube Music.

## 🚀 Arquitetura do Projeto

O projeto adota uma arquitetura de múltiplos serviços orquestrada em contêineres e um frontend reativo.

| Camada | Tecnologia | Função Principal |
| --- | --- | --- |
| **Frontend** | Vue.js + Vite + Tailwind CSS v4 | Interface responsiva, player de áudio HTML5 e reatividade (Polling). |
| **Backend Core** | PHP 8.2 (Laravel) | Arquitetura MVC, gerenciamento de filas (Jobs) para processamento em segundo plano e banco de dados. |
| **Microsserviço de Áudio** | Python + FastAPI + yt-dlp | Raspagem avançada via Iframe do Spotify e extração/download físico de áudio puro (`.m4a`/`.mp3`). |
| **Banco de Dados** | MySQL 8.0 | Armazenamento de metadados de músicas, playlists e caminhos de arquivos locais. |
| **Infraestrutura** | Docker Compose | Orquestração de redes, volumes compartilhados e ambientes isolados. |

---

## 🛠️ Requisitos de Ambiente

Para rodar o projeto do zero em uma máquina nova, você precisará instalar:

* **Docker Desktop** com a integração WSL 2 ativada (para usuários de Windows/Ubuntu).
* **Node.js** (versão 22.x LTS) instalado via NVM diretamente no seu terminal Ubuntu (WSL).
* **Git** para clonar o repositório.

---

## 🚀 Como executar localmente

Siga este passo a passo cronológico para inicializar o ecossistema completo.

### 1. Clonagem e Orquestração
* Clone o repositório: `git clone https://github.com/AlissonKaelan/VibeCast`.
* Acesse a pasta do projeto no seu terminal.
* Suba a infraestrutura base executando: `docker compose up -d --build`.
  * O Python responderá na porta `5000`.
  * O Laravel responderá na porta `8000`.

### 2. Configurando a API Core (Laravel) e Banco de Dados
* Acesse a pasta `backend-laravel/` e copie o arquivo `.env.example` renomeando-o para `.env`.
* Altere as configurações de fuso horário e banco de dados no `.env`:
  * `APP_TIMEZONE=America/Sao_Paulo`
  * `DB_HOST=db`
  * `QUEUE_CONNECTION=database`
* Volte à raiz do projeto e crie o atalho de pastas públicas: `docker compose exec app php artisan storage:link`.
* Instale as dependências e recrie as tabelas: 
  * `docker compose exec app composer install`
  * `docker compose exec app php artisan migrate:fresh`

### 3. Ligando o Trabalhador de Segundo Plano (Queue Worker)
* Para que os downloads ocorram em lote sem travar o servidor, abra uma **nova aba no terminal** e execute:
  * `docker compose exec app php artisan queue:work`
  * *(Deixe esta aba aberta enquanto usa a aplicação).*

### 4. Inicializando o Frontend
* Abra uma terceira aba no terminal Ubuntu (fora do Docker).
* Navegue até a pasta `frontend-vibecast/`.
* Instale as dependências com `npm install`.
* Inicie o servidor local com `npm run dev`.

---

## 🗺️ Roadmap e Próximos Passos

O motor de download físico e execução em segundo plano já estão prontos. As próximas fases incluem:

* **Player de Música Completo:** Substituir emojis por ícones profissionais, implementar barra de progresso de tempo (`currentTime`), pular faixas, loop e auto-play.
* **Gerenciador de Biblioteca Local:** Ler os arquivos baixados e permitir que o usuário crie suas próprias playlists misturando faixas.
* **Portabilidade Mobile Nativa:** Instalar o Capacitor no projeto Vite para envelopar a aplicação em um APK nativo com acesso offline aos arquivos armazenados.
