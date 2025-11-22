-- Migração: Sistema de Pontuação
-- Tabelas para sistema de pontuação por ações

-- Tabela de configuração de pontos por ação
CREATE TABLE IF NOT EXISTS pontos_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acao VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nome da ação (ex: registrar_emocao, postar_feed, acesso_diario)',
    descricao VARCHAR(255) NULL,
    pontos INT NOT NULL DEFAULT 0 COMMENT 'Quantidade de pontos que a ação vale',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_acao (acao),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de histórico de pontos
CREATE TABLE IF NOT EXISTS pontos_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    acao VARCHAR(100) NOT NULL COMMENT 'Ação que gerou os pontos',
    pontos INT NOT NULL COMMENT 'Pontos ganhos nesta ação',
    referencia_id INT NULL COMMENT 'ID de referência (ex: id do post, id da emoção)',
    referencia_tipo VARCHAR(50) NULL COMMENT 'Tipo de referência (feed_post, emocao, acesso)',
    data_registro DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_acao (acao),
    INDEX idx_data_registro (data_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de total de pontos por usuário/colaborador
CREATE TABLE IF NOT EXISTS pontos_total (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL UNIQUE,
    colaborador_id INT NULL UNIQUE,
    pontos_totais INT DEFAULT 0 COMMENT 'Total de pontos acumulados',
    pontos_mes INT DEFAULT 0 COMMENT 'Pontos do mês atual',
    pontos_semana INT DEFAULT 0 COMMENT 'Pontos da semana atual',
    pontos_dia INT DEFAULT 0 COMMENT 'Pontos do dia atual',
    ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão de pontos
INSERT INTO pontos_config (acao, descricao, pontos, ativo) VALUES
('registrar_emocao', 'Registrar emoção diária', 50, 1),
('postar_feed', 'Postar no feed', 20, 1),
('curtir_feed', 'Curtir postagem no feed', 2, 1),
('comentar_feed', 'Comentar no feed', 5, 1),
('acesso_diario', 'Acessar o sistema diariamente', 10, 1)
ON DUPLICATE KEY UPDATE descricao=VALUES(descricao), pontos=VALUES(pontos);

