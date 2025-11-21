-- Migração: Sistema de Notificações Push com OneSignal
-- Execute este SQL no seu banco de dados

-- Tabela para armazenar subscriptions do OneSignal
CREATE TABLE IF NOT EXISTS onesignal_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    player_id VARCHAR(255) NOT NULL UNIQUE,
    device_type VARCHAR(50),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_player_id (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para configurações do OneSignal
CREATE TABLE IF NOT EXISTS onesignal_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id VARCHAR(255) NOT NULL,
    rest_api_key VARCHAR(255) NOT NULL,
    safari_web_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

