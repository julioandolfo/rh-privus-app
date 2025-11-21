-- Migração: Tabela de Histórico de Notificações Push Enviadas
-- Execute este SQL no seu banco de dados

CREATE TABLE IF NOT EXISTS push_notifications_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    enviado_por_usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    url TEXT NULL,
    onesignal_id VARCHAR(255) NULL,
    total_dispositivos INT DEFAULT 0,
    sucesso BOOLEAN DEFAULT TRUE,
    erro_mensagem TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (enviado_por_usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_enviado_por (enviado_por_usuario_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

