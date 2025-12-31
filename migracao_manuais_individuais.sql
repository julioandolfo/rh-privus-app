-- Migração: Manuais Individuais
-- Sistema para criar manuais específicos por colaborador com informações de acesso, senhas, funções, etc.

-- Tabela de manuais individuais
CREATE TABLE IF NOT EXISTS manuais_individuais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    conteudo TEXT NOT NULL COMMENT 'Conteúdo do manual em HTML ou texto formatado',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_by INT NULL COMMENT 'ID do usuário que criou',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de relacionamento muitos-para-muitos: manuais x colaboradores
CREATE TABLE IF NOT EXISTS manuais_individuais_colaboradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manual_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manual_id) REFERENCES manuais_individuais(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_manual_colaborador (manual_id, colaborador_id),
    INDEX idx_manual (manual_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
