-- Migração: Sistema de Feed Social
-- Tabelas para feed social com postagens, curtidas e comentários

-- Tabela de postagens no feed
CREATE TABLE IF NOT EXISTS feed_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário que criou o post',
    colaborador_id INT NULL COMMENT 'Colaborador que criou o post (se não tiver usuário)',
    tipo ENUM('texto', 'imagem', 'celebração') DEFAULT 'texto',
    conteudo TEXT NOT NULL COMMENT 'Conteúdo do post',
    imagem VARCHAR(500) NULL COMMENT 'Caminho da imagem (se tipo = imagem)',
    tipo_celebração VARCHAR(100) NULL COMMENT 'Tipo de celebração (aniversario, promocao, conquista, etc)',
    status ENUM('ativo', 'oculto', 'removido') DEFAULT 'ativo',
    total_curtidas INT DEFAULT 0,
    total_comentarios INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de curtidas no feed
CREATE TABLE IF NOT EXISTS feed_curtidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_post_usuario (post_id, usuario_id),
    UNIQUE KEY uk_post_colaborador (post_id, colaborador_id),
    INDEX idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de comentários no feed
CREATE TABLE IF NOT EXISTS feed_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    comentario TEXT NOT NULL,
    status ENUM('ativo', 'oculto', 'removido') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

