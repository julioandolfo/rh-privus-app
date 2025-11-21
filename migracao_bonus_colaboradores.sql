-- Migração: Sistema de Bônus/Pagamentos Dinâmicos para Colaboradores
-- Execute este script no banco de dados

-- 1. Tabela de tipos de bônus (vale transporte, vale alimentação, etc.)
CREATE TABLE IF NOT EXISTS tipos_bonus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de bônus dos colaboradores
CREATE TABLE IF NOT EXISTS colaboradores_bonus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo_bonus_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE RESTRICT,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo_bonus (tipo_bonus_id),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_data_fim (data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de itens de bônus no fechamento de pagamentos
CREATE TABLE IF NOT EXISTS fechamentos_pagamento_bonus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fechamento_pagamento_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    tipo_bonus_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fechamento_pagamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE RESTRICT,
    INDEX idx_fechamento (fechamento_pagamento_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo_bonus (tipo_bonus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Inserir tipos de bônus padrão
INSERT INTO tipos_bonus (nome, descricao, status) VALUES
('Vale Transporte', 'Auxílio transporte para deslocamento', 'ativo'),
('Vale Alimentação', 'Auxílio alimentação', 'ativo'),
('Vale Refeição', 'Auxílio refeição', 'ativo'),
('Plano de Saúde', 'Auxílio plano de saúde', 'ativo'),
('Bônus', 'Bônus variável', 'ativo')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

