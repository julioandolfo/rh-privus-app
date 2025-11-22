-- Migração: Sistema de Notificações Internas
-- Tabela para notificações do sistema (curtidas, comentários, fechamento de pagamentos, etc)

CREATE TABLE IF NOT EXISTS notificacoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário destinatário',
    colaborador_id INT NULL COMMENT 'Colaborador destinatário (se não tiver usuário)',
    tipo VARCHAR(50) NOT NULL COMMENT 'tipo: curtida, comentario, fechamento_pagamento, etc',
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(500) NULL COMMENT 'Link para a ação relacionada',
    referencia_id INT NULL COMMENT 'ID de referência (ex: id do post, id do pagamento)',
    referencia_tipo VARCHAR(50) NULL COMMENT 'Tipo de referência (feed_post, pagamento, etc)',
    lida TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_lida (lida),
    INDEX idx_tipo (tipo),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

