-- Migração: Sistema de Comunicados
-- Tabelas para sistema de comunicados com rastreamento de leitura

-- Tabela de comunicados
CREATE TABLE IF NOT EXISTS comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL COMMENT 'Conteúdo HTML do comunicado',
    imagem VARCHAR(255) NULL COMMENT 'Caminho da imagem anexada',
    criado_por_usuario_id INT NOT NULL,
    status ENUM('rascunho', 'publicado', 'arquivado') DEFAULT 'rascunho',
    data_publicacao DATETIME NULL,
    data_expiracao DATETIME NULL COMMENT 'Data de expiração (opcional)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_data_publicacao (data_publicacao),
    INDEX idx_criado_por (criado_por_usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de rastreamento de leitura de comunicados
CREATE TABLE IF NOT EXISTS comunicados_leitura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comunicado_id INT NOT NULL,
    usuario_id INT NULL COMMENT 'ID do usuário que leu (se tiver usuário vinculado)',
    colaborador_id INT NULL COMMENT 'ID do colaborador que leu (se não tiver usuário vinculado)',
    lido TINYINT(1) DEFAULT 0 COMMENT 'Se foi marcado como lido',
    data_leitura DATETIME NULL COMMENT 'Data em que foi marcado como lido',
    data_visualizacao DATETIME NULL COMMENT 'Data da última visualização',
    vezes_visualizado INT DEFAULT 0 COMMENT 'Quantas vezes foi visualizado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (comunicado_id) REFERENCES comunicados(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_comunicado_usuario (comunicado_id, usuario_id, colaborador_id),
    INDEX idx_comunicado (comunicado_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_lido (lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

