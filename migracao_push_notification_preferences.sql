-- Migração: Tabela de Preferências de Notificações Push
-- Permite que usuários/colaboradores controlem quais tipos de notificações push recebem

CREATE TABLE IF NOT EXISTS push_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    tipo_notificacao VARCHAR(50) NOT NULL COMMENT 'Ex: feedback_recebido, ocorrencia_criada, etc',
    ativo TINYINT(1) DEFAULT 1 COMMENT '1 = ativo, 0 = desativado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo_notificacao),
    INDEX idx_usuario_tipo (usuario_id, tipo_notificacao),
    INDEX idx_colaborador_tipo (colaborador_id, tipo_notificacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: A unicidade é garantida pela lógica da aplicação
-- Um usuário/colaborador só pode ter uma preferência por tipo de notificação

-- Tipos de notificações disponíveis no sistema:
-- 'feedback_recebido' - Quando recebe um novo feedback
-- 'documento_pagamento_enviado' - Quando colaborador envia documento de pagamento (Admin/RH recebe)
-- 'documento_pagamento_aprovado' - Quando documento de pagamento é aprovado (colaborador recebe)
-- 'documento_pagamento_rejeitado' - Quando documento de pagamento é rejeitado (colaborador recebe)
-- 'ocorrencia_criada' - Quando uma ocorrência é criada (se implementado no futuro)
-- 'ocorrencia_atualizada' - Quando uma ocorrência é atualizada (se implementado no futuro)
-- 'comentario_feedback' - Quando alguém responde um feedback (se implementado no futuro)

