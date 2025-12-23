-- Migração: Criar tabela de histórico/anotações do onboarding

CREATE TABLE IF NOT EXISTS onboarding_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'Quem registrou',
    tipo ENUM('anotacao', 'andamento', 'documento', 'contato', 'problema', 'outro') DEFAULT 'anotacao',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    status_andamento ENUM('pendente', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'em_andamento',
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (onboarding_id) REFERENCES onboarding(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    INDEX idx_onboarding (onboarding_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_tipo (tipo),
    INDEX idx_status (status_andamento),
    INDEX idx_data (data_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

