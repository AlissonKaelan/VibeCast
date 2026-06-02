-- 1. Tabela de Usuários (Padrão Laravel)

CREATE TABLE users(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- 2. Tabela de Músicas (Cache de Metadados do Spotify)
CREATE TABLE tracks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    spotify_id VARCHAR(255) UNIQUE NOT NULL, -- ID único do Spotify para busca direta
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    album VARCHAR(255),
    duration_ms INT UNSIGNED NOT NULL,       -- Fundamental para o algoritmo de match
    cover_url VARCHAR(500),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_spotify (spotify_id)
);

-- 3. Tabela de Correspondência (Cache do Match com o YouTube Music)
CREATE TABLE track_matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    track_id BIGINT UNSIGNED NOT NULL,
    youtube_id VARCHAR(255) NOT NULL,        -- ID do vídeo/música encontrado no YT Music
    last_verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
    INDEX idx_youtube (youtube_id)
);

-- 4. Tabela de Playlists
CREATE TABLE playlists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Tabela Pivô (Relacionamento Muitos-para-Muitos entre Playlists e Músicas)
CREATE TABLE playlist_track (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id, track_id),
    FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
);