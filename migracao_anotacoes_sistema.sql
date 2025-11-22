-- Migração: Sistema Completo de Anotações com Notificações
-- Execute este script no banco de dados

-- ============================================
-- TABELA DE ANOTAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS anotacoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'Usuário que criou a anotação',
    titulo VARCHAR(255) NOT NULL COMMENT 'Título da anotação',
    conteudo TEXT NOT NULL COMMENT 'Conteúdo da anotação',
    tipo ENUM('geral', 'lembrete', 'importante', 'urgente', 'informacao') DEFAULT 'geral',
    cor VARCHAR(20) NULL COMMENT 'Cor personalizada (hex)',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
    status ENUM('ativa', 'concluida', 'arquivada') DEFAULT 'ativa',
    
    -- Sistema de notificações
    notificar_email TINYINT(1) DEFAULT 0 COMMENT 'Enviar notificação por email',
    notificar_push TINYINT(1) DEFAULT 0 COMMENT 'Enviar notificação push',
    data_notificacao DATETIME NULL COMMENT 'Data/hora para enviar notificação',
    notificacao_enviada TINYINT(1) DEFAULT 0 COMMENT 'Se a notificação já foi enviada',
    
    -- Destinatários da notificação (se diferente do criador)
    destinatarios_usuarios TEXT NULL COMMENT 'JSON array com IDs de usuários destinatários',
    destinatarios_colaboradores TEXT NULL COMMENT 'JSON array com IDs de colaboradores destinatários',
    publico_alvo ENUM('todos', 'empresa', 'setor', 'cargo', 'especifico') DEFAULT 'especifico',
    empresa_id INT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    
    -- Categorias e tags
    categoria VARCHAR(100) NULL,
    tags TEXT NULL COMMENT 'JSON array com tags',
    
    -- Metadados
    visualizacoes INT DEFAULT 0 COMMENT 'Quantas vezes foi visualizada',
    compartilhada TINYINT(1) DEFAULT 0 COMMENT 'Se foi compartilhada com outros',
    fixada TINYINT(1) DEFAULT 0 COMMENT 'Se está fixada no topo',
    
    -- Datas importantes
    data_vencimento DATE NULL COMMENT 'Data de vencimento/validade',
    data_conclusao DATETIME NULL COMMENT 'Quando foi concluída',
    
    -- Anexos
    anexos TEXT NULL COMMENT 'JSON array com caminhos de arquivos anexados',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE SET NULL,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status),
    INDEX idx_tipo (tipo),
    INDEX idx_prioridade (prioridade),
    INDEX idx_data_notificacao (data_notificacao),
    INDEX idx_notificacao_enviada (notificacao_enviada),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_fixada (fixada),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA DE VISUALIZAÇÕES DE ANOTAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS anotacoes_visualizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anotacao_id INT NOT NULL,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    visualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (anotacao_id) REFERENCES anotacoes_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_visualizacao (anotacao_id, usuario_id, colaborador_id),
    INDEX idx_anotacao (anotacao_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA DE COMENTÁRIOS EM ANOTAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS anotacoes_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anotacao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (anotacao_id) REFERENCES anotacoes_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    INDEX idx_anotacao (anotacao_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA DE HISTÓRICO DE ALTERAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS anotacoes_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anotacao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL COMMENT 'criada, editada, concluida, arquivada, compartilhada, etc',
    dados_anteriores TEXT NULL COMMENT 'JSON com dados anteriores',
    dados_novos TEXT NULL COMMENT 'JSON com dados novos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (anotacao_id) REFERENCES anotacoes_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    INDEX idx_anotacao (anotacao_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

