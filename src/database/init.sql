CREATE DATABASE IF NOT EXISTS extagram_db;
USE extagram_db;

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post TEXT NULL,
    photourl VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos de prueba
INSERT INTO posts (post, photourl) VALUES 
('Welcome to Extagram!', ''),
('First post with Docker', '');
