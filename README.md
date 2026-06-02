# **VibeCast** 🎵

Uma plataforma de streaming de áudio independente que utiliza a base de metadados do Spotify combinada com o catálogo de mídia do YouTube Music. 

## 🚀 Arquitetura do Projeto

O projeto é dividido em microsserviços rodando em contêineres Docker:
* **Frontend:** Vue.js + Tailwind CSS (Em breve)
* **Backend Core:** PHP 8.2 + Laravel (API e lógica de negócios)
* **Microsserviço de Áudio:** Python + FastAPI + yt-dlp (Extração de mídia)
* **Banco de Dados:** MySQL 8.0

## 🛠️ Como executar localmente

Certifique-se de ter o Docker e o Docker Compose instalados no seu ambiente de desenvolvimento.

1. Clone o repositório:
```bash
   git clone https://github.com/AlissonKaelan/VibeCast
```

2. Acesse a pasta do projeto e inicie os contêineres:

```bash
   docker compose up -d --build
```

3. O banco de dados estará acessível na porta `3308` (Host) / `3306` (Interna).
4. O serviço de extração de áudio responderá na porta `5000`.
5. A API principal responderá na porta `8000`.
