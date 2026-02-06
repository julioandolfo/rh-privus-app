-- Migração: Sistema de Solicitações de Feedback
-- Adiciona funcionalidade para colaboradores solicitarem feedback de outros

-- Tabela de solicitações de feedback
CREATE TABLE IF NOT EXISTS feedback_solicitacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitante_usuario_id INT NULL COMMENT 'Usuário que está solicitando o feedback',
    solicitante_colaborador_id INT NULL COMMENT 'Colaborador que está solicitando o feedback',
    solicitado_usuario_id INT NULL COMMENT 'Usuário que deve enviar o feedback',
    solicitado_colaborador_id INT NULL COMMENT 'Colaborador que deve enviar o feedback',
    mensagem TEXT NULL COMMENT 'Mensagem explicando o motivo da solicitação',
    prazo DATE NULL COMMENT 'Data limite para responder (opcional)',
    status ENUM('pendente', 'aceita', 'recusada', 'concluida', 'expirada') DEFAULT 'pendente' COMMENT 'Status da solicitação',
    feedback_id INT NULL COMMENT 'ID do feedback enviado (quando concluída)',
    resposta_mensagem TEXT NULL COMMENT 'Mensagem de resposta ao aceitar/recusar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    respondida_at TIMESTAMP NULL COMMENT 'Data que foi aceita/recusada',
    FOREIGN KEY (solicitante_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitante_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitado_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (solicitado_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE SET NULL,
    INDEX idx_solicitante_usuario (solicitante_usuario_id),
    INDEX idx_solicitante_colaborador (solicitante_colaborador_id),
    INDEX idx_solicitado_usuario (solicitado_usuario_id),
    INDEX idx_solicitado_colaborador (solicitado_colaborador_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir ação de pontos para solicitar feedback (se tabela existir)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('solicitar_feedback', 'Solicitar feedback de outro colaborador', 10, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), pontos = VALUES(pontos), ativo = VALUES(ativo);

-- Inserir ação de pontos para responder solicitação de feedback (se tabela existir)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('responder_solicitacao_feedback', 'Responder solicitação de feedback', 20, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), pontos = VALUES(pontos), ativo = VALUES(ativo);
