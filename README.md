# VibeCast

O VibeCast é uma plataforma de streaming de áudio independente e de código aberto, criada com o objetivo de permitir escutar e baixar músicas do Spotify e YouTube de forma totalmente gratuita e sem anúncios. A aplicação separa a interface visual da fonte real da mídia, utilizando links públicos do Spotify para importar metadados (como uma prateleira) e extraindo o áudio puro diretamente do YouTube Music.

> **Nota de Desenvolvimento:** O foco principal deste projeto foi resolver um problema pessoal e servir como laboratório de aprendizado contínuo. Por isso, você poderá encontrar comentários explicativos detalhados espalhados pelos arquivos detalhando lógicas e conceitos estudados (como diretivas reativas no Vue.js). Estes comentários didáticos serão limpos e removidos com o tempo.

## Arquitetura do Projeto

O projeto adota uma arquitetura de múltiplos microsserviços 100% orquestrada em contêineres (Backend, Banco, Extrator, Trabalhador de Fila e Frontend isolados).

| Camada | Tecnologia | Função Principal |
| --- | --- | --- |
| **Frontend** | Vue.js + Vite + Tailwind CSS v4 | Interface responsiva, player de áudio completo customizado e gerenciamento de estado global com Pinia. |
| **Backend Core** | PHP 8.2 (Laravel) | Arquitetura de rotas, API REST, gerenciamento de filas (Jobs) para processamento em segundo plano e banco de dados. |
| **Microsserviço de Áudio** | Python + FastAPI + yt-dlp | Raspagem avançada via Iframe do Spotify e extração/download físico de áudio puro (`.m4a`/`.mp3`). |
| **Banco de Dados** | MySQL 8.0 | Armazenamento de metadados de músicas, playlists e caminhos de arquivos locais. |
| **Infraestrutura** | Docker Compose | Orquestração de redes, volumes compartilhados e ambientes isolados. |

---

## Funcionalidades Atuais

* **Motor de Importação:** Conversão automática de playlists do Spotify para arquivos físicos via YouTube Music.
* **Player Avançado:** Controles de volume dinâmico, barra de progresso interativa (arrastável), reprodução contínua e integração com biblioteca local.
* **Módulo de Web Rádios:** Escuta de transmissões ao vivo.
* **Gerenciador de Biblioteca:** Criação de playlists, edição de metadados das faixas, exclusão segura de arquivos e movimentação de músicas.
* **Exportação Offline:** Download em lote e empacotamento de playlists inteiras em arquivos `.zip` para pendrives.
* **Trabalhador em Segundo Plano:** Sistema de filas que permite baixar dezenas de músicas sem travar a navegação do usuário.

---

## Requisitos de Ambiente

Para rodar o projeto do zero em uma máquina nova, você precisará de:

* **Docker Engine** (Para usuários de Windows ou macOS, instale o **Docker Desktop**).
* **WSL 2 (Ubuntu/Linux) ativado** (Obrigatório apenas para usuários de Windows, garantindo que o Docker funcione corretamente).
* **Git** (para clonar o repositório).
* **Extensão de Navegador:** "Get cookies.txt LOCALLY" (necessária para autenticação de downloads).

*(Não é necessário instalar Node.js, PHP ou Python na sua máquina física, o Docker se encarregará de construir contêineres virgens com as versões exatas necessárias).*

---

## Passo a Passo de Instalação e Configuração

Para que o motor de downloads funcione e evite bloqueios de segurança do YouTube, a configuração de cookies é uma etapa obrigatória antes de iniciar o sistema.

### 1. Clonagem do Repositório

Para usuários de **Windows**, a escolha do local onde o projeto será clonado afeta drasticamente o desempenho do Docker. Oferecemos duas opções abaixo, sendo a primeira fortemente recomendada.

**Opção 1 (Recomendada para Windows e Padrão para Linux): Clonar dentro do WSL2**

O Docker no Windows funciona através de uma máquina virtual Linux (WSL2). Manter os arquivos dentro do sistema de arquivos nativo do Linux (`/home/...`) garante máxima velocidade de leitura e escrita, evita problemas de permissão e impede travamentos no *hot-reload* do Vue.js.

* Abra o terminal do seu Ubuntu (WSL) e execute:
```bash
mkdir -p ~/projetos
cd ~/projetos
git clone https://github.com/AlissonKaelan/VibeCast

```

*(Dica: Se estiver usando o VS Code, basta digitar `code VibeCast/` neste terminal para abrir o projeto com integração total ao Linux).*

**Opção 2: Clonar em uma pasta normal do Windows (Ex: `C:\Users\...`)**

* **Aviso de Desempenho:** Se você salvar o projeto no disco do Windows, toda vez que os contêineres precisarem ler um arquivo, eles usarão uma "ponte" de rede para acessar o disco local, o que deixa a aplicação, a instalação de pacotes (Composer/NPM) e o servidor incrivelmente lentos.
* Caso prefira essa abordagem, abra o PowerShell/Git Bash e execute:

```bash
git clone https://github.com/AlissonKaelan/VibeCast

```

### 2. Autenticação Obrigatória (Cookies)

O YouTube bloqueia downloads automatizados que não possuem identificação. Para o sistema funcionar, você deve fornecer um arquivo de sessão válido.

* Instale a extensão **Get cookies.txt LOCALLY** em seu navegador (Chrome, Edge ou Firefox).
* Acesse o YouTube e faça login utilizando uma conta secundária ou descartável. **Nunca utilize sua conta pessoal principal por questões de segurança de dados.**
* Com o YouTube aberto, clique na extensão e exporte o arquivo de cookies.
* Renomeie o arquivo baixado para `cookies.txt` e cole-o obrigatoriamente dentro do projeto no caminho exato: `VibeCast/extractor-python/cookies.txt`.

### 3. Configurando a API Core (Laravel)

* Acesse a pasta `backend-laravel/` e copie o arquivo `.env.example` renomeando-o para `.env`.
* Altere as configurações de banco de dados e fila no `.env`:

```env
APP_TIMEZONE=America/Sao_Paulo
DB_HOST=db
QUEUE_CONNECTION=database

```

---

## Otimização de Hardware (Memória RAM no Windows)

Por padrão, o ecossistema do Linux no Windows (processo `VmmemWSL`) pode alocar até 50% de toda a memória RAM do seu computador. Se você deseja rodar o VibeCast e continuar utilizando o PC para jogos ou programação simultânea, você pode travar o limite de memória do Docker.

**Como aplicar a trava de memória:**

1. Abra o Bloco de Notas do Windows e cole o seguinte código:

```ini
[wsl2]
memory=2GB
processors=2

```

2. Salve o arquivo com o nome exato de `.wslconfig` na raiz da pasta do seu usuário do Windows (Exemplo: `C:\Users\SeuUsuario\.wslconfig`).
3. **Atenção à extensão:** O Bloco de Notas costuma esconder a extensão e salvar o arquivo como `.wslconfig.txt`. Certifique-se de ir na aba "Exibir" do Explorador de Arquivos, ativar a visualização de "Extensões de nomes de arquivos" e apagar o `.txt` do final.
4. Abra o PowerShell e digite `wsl --shutdown` (ou reinicie o computador). Ao ligar o Docker novamente, ele estará limitado a 2GB de RAM.

> **Importante sobre Múltiplos Projetos:** Essa configuração se aplica a toda a máquina virtual. Se você pretende rodar o VibeCast simultaneamente com outros projetos Docker na mesma máquina, é recomendado aumentar esse limite no arquivo (ex: `memory=4GB` ou `6GB`) ou simplesmente apagar o arquivo `.wslconfig` para permitir que o Docker gerencie a memória de forma dinâmica, evitando que os contêineres caiam por falta de recursos.

---

## Como executar o projeto

Você pode iniciar o VibeCast de forma Automatizada (recomendado para uso diário) ou Manual (para ambientes de desenvolvimento).

### Opção A: Início Automatizado (Apenas Windows)

O projeto possui um executável projetado para orquestrar os contêineres com um único clique. Como o executável atua como um ativador de scripts do Docker no ambiente WSL, ele precisa estar localizado na pasta correta para funcionar.

1. Acesse o repositório oficial no GitHub pelo navegador: [https://github.com/AlissonKaelan/VibeCast](https://github.com/AlissonKaelan/VibeCast)
2. Na barra lateral direita, clique em **Releases**.
3. Baixe a versão mais recente do arquivo `VibeCast.exe`.
4. Mova o arquivo `VibeCast.exe` e cole-o diretamente na **raiz da pasta do projeto clonado** no seu computador. Após colar na pasta correta, você pode criar um atalho deste arquivo e enviá-lo para a sua Área de Trabalho para facilitar o acesso no dia a dia.
5. **Obrigatório:** O aplicativo **Docker Desktop deve estar aberto e rodando** no seu computador antes de tentar iniciar o projeto.
6. Dê um duplo clique no executável ou no atalho. O script ligará os contêineres silenciosamente, aguardará a inicialização do Banco de Dados e abrirá o aplicativo automaticamente no seu navegador Chrome.

### Opção B: Inicialização Manual (Linux / Mac / Dev)

Caso prefira levantar os serviços pelo terminal ou utilize outro sistema operacional:

1. Na raiz do projeto, suba a infraestrutura executando: `docker compose up -d`
2. Entre no contêiner do backend para rodar os comandos administrativos: `docker compose exec app bash`
3. Dentro do contêiner, execute as instalações e migrações:
* `composer install`
* `php artisan migrate:fresh`
* `php artisan storage:link`


4. Digite `exit` para sair do contêiner.
5. Abra o seu navegador e acesse: `http://localhost:5173`

---

## Solução de Problemas (Troubleshooting)

**Problemas com Downloads (Erro 403 do YouTube)**
O YouTube atualiza constantemente suas defesas. Se o download falhar silenciosamente:

* **Cookies Expirados:** O seu arquivo de cookies perdeu a validade. Refaça o "Passo 2" da instalação gerando um novo arquivo pelo navegador e substituindo o antigo na pasta `extractor-python/`. Reinicie o serviço do Python em seguida.
* **Extrator Desatualizado:** Caso a biblioteca fique defasada, acesse o terminal, entre no contêiner do Python (`docker compose exec python-extractor bash`) e force a atualização rodando: `pip install --upgrade yt-dlp`. Reinicie o contêiner.

---
