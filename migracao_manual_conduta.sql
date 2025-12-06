-- ============================================
-- MIGRAÇÃO COMPLETA: Sistema Manual de Conduta
-- Inclui: Manual de Conduta, FAQ, Histórico e Analytics
-- ============================================

-- 1. Tabela principal do Manual de Conduta
CREATE TABLE IF NOT EXISTS manual_conduta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL DEFAULT 'Manual de Conduta Privus',
    conteudo LONGTEXT NOT NULL COMMENT 'Conteúdo HTML/Markdown do manual',
    versao VARCHAR(50) NULL COMMENT 'Versão do manual (ex: 1.0, 2.1)',
    ativo BOOLEAN DEFAULT TRUE COMMENT 'Se está ativo e visível',
    publicado_em DATETIME NULL COMMENT 'Data de publicação',
    publicado_por INT NULL COMMENT 'Usuário que publicou',
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (publicado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_ativo (ativo),
    INDEX idx_publicado_em (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de histórico do Manual de Conduta
CREATE TABLE IF NOT EXISTS manual_conduta_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manual_conduta_id INT NOT NULL,
    versao VARCHAR(50) NULL,
    conteudo_anterior LONGTEXT NULL COMMENT 'Conteúdo antes da alteração',
    conteudo_novo LONGTEXT NULL COMMENT 'Conteúdo após alteração',
    alterado_por INT NOT NULL,
    motivo_alteracao TEXT NULL COMMENT 'Motivo da alteração',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (manual_conduta_id) REFERENCES manual_conduta(id) ON DELETE CASCADE,
    FOREIGN KEY (alterado_por) REFERENCES usuarios(id),
    INDEX idx_manual_conduta (manual_conduta_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de FAQ
CREATE TABLE IF NOT EXISTS faq_manual_conduta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pergunta TEXT NOT NULL,
    resposta LONGTEXT NOT NULL,
    categoria VARCHAR(100) NULL COMMENT 'Categoria para agrupamento (ex: Geral, Regras, Benefícios)',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0 COMMENT 'Contador de visualizações',
    util_respondeu_sim INT DEFAULT 0 COMMENT 'Contador de "útil"',
    util_respondeu_nao INT DEFAULT 0 COMMENT 'Contador de "não útil"',
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (categoria),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabela de histórico do FAQ
CREATE TABLE IF NOT EXISTS faq_manual_conduta_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faq_id INT NOT NULL,
    pergunta_anterior TEXT NULL,
    pergunta_nova TEXT NULL,
    resposta_anterior LONGTEXT NULL,
    resposta_nova LONGTEXT NULL,
    alterado_por INT NOT NULL,
    motivo_alteracao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (faq_id) REFERENCES faq_manual_conduta(id) ON DELETE CASCADE,
    FOREIGN KEY (alterado_por) REFERENCES usuarios(id),
    INDEX idx_faq (faq_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabela de visualizações (Analytics)
CREATE TABLE IF NOT EXISTS manual_conduta_visualizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário logado',
    colaborador_id INT NULL COMMENT 'Colaborador (se não tiver usuário)',
    tipo ENUM('manual', 'faq') NOT NULL,
    faq_id INT NULL COMMENT 'ID do FAQ se tipo = faq',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (faq_id) REFERENCES faq_manual_conduta(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_faq (faq_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Inserir registro inicial do manual (vazio)
INSERT INTO manual_conduta (titulo, conteudo, versao, ativo, criado_por, publicado_em, publicado_por)
SELECT 
    'Manual de Conduta Privus',
    '<h1>Bem-vindo ao Manual de Conduta Privus</h1><p>Este manual será atualizado em breve com o conteúdo completo.</p>',
    '1.0',
    TRUE,
    (SELECT id FROM usuarios WHERE role = 'ADMIN' LIMIT 1),
    NOW(),
    (SELECT id FROM usuarios WHERE role = 'ADMIN' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM manual_conduta LIMIT 1);

