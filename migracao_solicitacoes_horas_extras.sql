-- Migração: Sistema de Solicitações de Horas Extras por Colaboradores
-- Execute este script no banco de dados

-- Tabela de solicitações de horas extras
CREATE TABLE IF NOT EXISTS solicitacoes_horas_extras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    data_trabalho DATE NOT NULL COMMENT 'Data em que as horas extras foram trabalhadas',
    quantidade_horas DECIMAL(5,2) NOT NULL COMMENT 'Quantidade de horas extras (máximo 8h por solicitação)',
    motivo TEXT NOT NULL COMMENT 'Motivo/justificativa das horas extras',
    status ENUM('pendente', 'aprovada', 'rejeitada') DEFAULT 'pendente',
    observacoes_rh TEXT NULL COMMENT 'Observações do RH ao aprovar/rejeitar',
    usuario_aprovacao_id INT NULL COMMENT 'Usuário RH que aprovou/rejeitou',
    data_aprovacao DATETIME NULL COMMENT 'Data/hora da aprovação/rejeição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_aprovacao_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_data_trabalho (data_trabalho),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

