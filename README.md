# **VibeCast** 🎵

O VibeCast é uma plataforma de streaming de áudio independente e de código aberto. A aplicação separa a interface visual da fonte real da mídia, utilizando links públicos do Spotify para importar metadados (como uma prateleira) e extraindo o áudio puro diretamente do YouTube Music.

## 🚀 Arquitetura do Projeto

O projeto adota uma arquitetura de múltiplos serviços orquestrada em contêineres e um frontend reativo.

| Camada | Tecnologia | Função Principal |
| --- | --- | --- |
| **Frontend** | Vue.js + Vite + Tailwind CSS v4 | Interface responsiva e player de áudio.

 |
| **Backend Core** | PHP 8.2 (Laravel) | Arquitetura MVC para gerenciar rotas, banco de dados e regras de negócio.|

 |
| **Microsserviço de Áudio** | Python + FastAPI + yt-dlp | Raspagem de links públicos com `requests`/`beautifulsoup4` e extração de áudio puro com `ytmusicapi` e `yt-dlp`.|

 |
| **Banco de Dados** | MySQL 8.0 | Armazenamento de cache de URLs, playlists e histórico.|

 |
| **Infraestrutura** | Docker Compose | Orquestração de redes e ambientes isolados.|
 

---

## 🛠️ Requisitos de Ambiente

Para rodar o projeto do zero em uma máquina nova, você precisará instalar:

* Docker Desktop com a integração WSL 2 ativada (para usuários de Windows/Ubuntu).
* Node.js (versão 22.x LTS) instalado via NVM diretamente no seu terminal Ubuntu (WSL).


* Git para clonar o repositório.



---

## 🚀 Como executar localmente

Siga este passo a passo cronológico para inicializar o ecossistema completo.

### 1. Clonagem e Orquestração

* Clone o repositório: `git clone https://github.com/AlissonKaelan/VibeCast`.


* Acesse a pasta do projeto no seu terminal.
* Suba todos os contêineres executando: `docker compose up -d --build`.


* O serviço de extração de áudio responderá na porta `5000`.


* A API principal responderá na porta `8000`.



### 2. Configurando o Banco de Dados (MySQL)

* O banco será construído pelo Docker e exposto na porta externa `3308` e interna `3306`.


* Para gerenciar via DBeaver, crie uma conexão em `127.0.0.1:3308` apontando para o banco `VibeCast` com o usuário `root` e senha `root_password_local`.


* Altere as propriedades do driver definindo `allowPublicKeyRetrieval` como `true` e `useSSL` como `false`.



### 3. Configurando a API Core (Laravel)

* Acesse a pasta `backend-laravel/` e copie o arquivo `.env.example` renomeando-o para `.env`.


* Altere as configurações de banco de dados no `.env` para `DB_HOST=db` e `DB_PORT=3306`.


* Volte à raiz do projeto e acesse o terminal do contêiner PHP: `docker compose exec app bash`.


* Dentro do contêiner, instale as dependências com `composer install` e recrie as tabelas executando `php artisan migrate:fresh`.



### 4. Inicializando o Frontend

* Abra uma nova aba no terminal Ubuntu (fora do Docker).
* Navegue até a pasta `frontend-vibecast/`.


* Instale as dependências com `npm install`.


* Inicie o servidor de desenvolvimento local executando `npm run dev`.


* O painel estará disponível localmente e na sua rede Wi-Fi para testes diretamente no seu celular.



---

## 🗺️ Roadmap e Próximos Passos

A infraestrutura e o motor de backend já estão validados. As próximas fases do desenvolvimento incluem:

* **Integração Visual (Frontend):** Desenhar as telas de importação de playlists e construir o player de áudio contínuo utilizando os componentes reativos do Vue.js.


* **Execução Lazy Loading:** Otimizar o backend para que a extração pesada do áudio ocorra apenas no exato segundo em que o botão "Play" for acionado.


* **Portabilidade Mobile Nativa:** Instalar o Capacitor no projeto Vite para envelopar a aplicação em um APK nativo.


* **Download e Armazenamento:** Habilitar a API nativa `@capacitor/filesystem` para salvar as músicas extraídas, permitindo o download e a reprodução offline diretamente no celular.
