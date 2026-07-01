# **VibeCast** 🎵

O VibeCast é uma plataforma de streaming de áudio independente e de código aberto. A aplicação separa a interface visual da fonte real da mídia, utilizando links públicos do Spotify para importar metadados (como uma prateleira) e extraindo o áudio puro diretamente do YouTube Music.

## 🚀 Arquitetura do Projeto

O projeto adota uma arquitetura de múltiplos microsserviços **100% orquestrada em contêineres** (Backend, Banco, Extrator, Trabalhador de Fila e Frontend isolados).

| Camada | Tecnologia | Função Principal |
| --- | --- | --- |
| **Frontend** | Vue.js + Vite + Tailwind CSS v4 | Interface responsiva, player de áudio completo customizado e gerenciamento de estado global com Pinia. |
| **Backend Core** | PHP 8.2 (Laravel) | Arquitetura de rotas, API REST, gerenciamento de filas (Jobs) para processamento em segundo plano e banco de dados. |
| **Microsserviço de Áudio** | Python + FastAPI + yt-dlp | Raspagem avançada via Iframe do Spotify e extração/download físico de áudio puro (`.m4a`/`.mp3`). |
| **Banco de Dados** | MySQL 8.0 | Armazenamento de metadados de músicas, playlists e caminhos de arquivos locais. |
| **Infraestrutura** | Docker Compose | Orquestração de redes, volumes compartilhados e ambientes isolados. |

---

## 🎧 Funcionalidades Atuais

* **Motor de Importação:** Conversão automática de playlists do Spotify para arquivos físicos via YouTube Music.
* **Player Avançado:** Controles de volume dinâmico, barra de progresso interativa (arrastável), reprodução contínua e integração com biblioteca local.
* **Módulo de Web Rádios:** Escuta de transmissões ao vivo.
* **Gerenciador de Biblioteca:** Criação de playlists, edição de metadados das faixas, exclusão segura de arquivos e movimentação de músicas.
* **Exportação Offline:** Download em lote e empacotamento de playlists inteiras em arquivos `.zip` para pendrives.
* **Trabalhador em Segundo Plano:** Sistema de filas que permite baixar dezenas de músicas sem travar a navegação do usuário.

---

## 🛠️ Requisitos de Ambiente

Para rodar o projeto do zero em uma máquina nova, você precisará apenas de:

* **Docker Desktop** com a integração WSL 2 ativada (para usuários de Windows/Ubuntu).
* **Git** (para clonar o repositório).
* *(O Node.js e o PHP não precisam mais ser instalados na máquina física, pois o Docker construirá contêineres virgens com as versões exatas necessárias).*

---

## 🚀 Como executar o projeto

Você pode iniciar o VibeCast de forma **Automatizada** (recomendado para uso diário) ou **Manual** (para desenvolvedores).

### Opção A: Início Automatizado (Windows)

Se você utiliza Windows com WSL (Ubuntu), um script executável foi criado para ligar tudo com um clique:

1. Clone o repositório: `git clone https://github.com/AlissonKaelan/VibeCast`
2. Configure o arquivo `.env` do Laravel (veja as instruções no passo 2 da Opção B).
3. Na raiz do projeto, dê um duplo clique no arquivo **`Iniciar_VibeCast.bat`**.
4. O script iniciará o Docker silenciosamente, aguardará a inicialização do Vite e do Banco de Dados, e abrirá o aplicativo automaticamente no seu navegador Chrome.

### Opção B: Inicialização Manual (Linux / Mac / Dev)

Caso prefira levantar os serviços manualmente pelo terminal, siga o passo a passo cronológico:

**1. Clonagem e Orquestração**

* Clone o repositório: `git clone https://github.com/AlissonKaelan/VibeCast`
* Acesse a pasta do projeto no terminal.
* Suba a infraestrutura completa executando: `docker compose up -d`
*(O Docker iniciará o Vue na porta `5173`, o Python na `5000`, o Laravel na `8000` e o Queue Worker em background).*

**2. Configurando a API Core (Laravel) e Banco de Dados**

* Acesse a pasta `backend-laravel/` e copie o arquivo `.env.example` renomeando-o para `.env`.
* Altere as configurações de banco de dados e fila no `.env`:
* `APP_TIMEZONE=America/Sao_Paulo`
* `DB_HOST=db`
* `QUEUE_CONNECTION=database`


* Entre no contêiner do backend para rodar os comandos administrativos:
* `docker compose exec app bash`
* Dentro do contêiner, execute:
* `composer install` *(Instala dependências do PHP)*
* `php artisan migrate:fresh` *(Cria as tabelas do banco)*
* `php artisan storage:link` *(Cria atalho para ler os áudios e imagens públicas)*


* Digite `exit` para sair do contêiner.



**3. Acessando a Aplicação**

* Abra o seu navegador e acesse: `http://localhost:5173`

---

## 🗺️ Roadmap e Próximos Passos

O núcleo duro de download físico, arquitetura de banco de dados e reprodução contínua estão prontos. As próximas fases planeadas para coroar o VibeCast v1.0 incluem:

* **Motor de Temas Dinâmico:** Painel de customização visual permitindo trocar a cor de destaque (Azul, Roxo, Verde, etc.) e ativar/desativar o Glassmorphism (Efeito vidro) reativamente. *(Em desenvolvimento)*
* **Responsividade e PWA:** Otimização absoluta da interface para telas de smartphones e configuração de Service Workers (manifest.json) para instalação do App Nativo mobile direto pelo navegador.
* **Letras de Músicas (Opcional):** Integração com APIs abertas de Lyrics para exibir a letra da faixa atual em tempo real.
* **Selagem para Produção (Deploy):** Criação de um `Dockerfile` de produção focado em performance, removendo os volumes em tempo real para permitir a hospedagem em VPS de nuvem pública.
