-- Migração: Sistema de Tokens para Notificações Push
-- Permite login automático e visualização de detalhes da notificação

-- Tabela para armazenar notificações push enviadas com tokens de acesso
CREATE TABLE IF NOT EXISTS `notificacoes_push` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notificacao_id` INT UNSIGNED NOT NULL COMMENT 'ID da notificação em notificacoes_sistema',
    `usuario_id` INT UNSIGNED NULL COMMENT 'ID do usuário destinatário',
    `colaborador_id` INT UNSIGNED NULL COMMENT 'ID do colaborador destinatário',
    `token` VARCHAR(64) NOT NULL COMMENT 'Token único para login automático',
    `titulo` VARCHAR(255) NOT NULL COMMENT 'Título da notificação push',
    `mensagem` TEXT NOT NULL COMMENT 'Mensagem da notificação push',
    `url` TEXT NULL COMMENT 'URL de destino',
    `enviado` TINYINT(1) DEFAULT 0 COMMENT 'Se foi enviada com sucesso',
    `enviado_em` TIMESTAMP NULL COMMENT 'Data/hora do envio',
    `visualizada` TINYINT(1) DEFAULT 0 COMMENT 'Se foi visualizada',
    `visualizada_em` TIMESTAMP NULL COMMENT 'Data/hora da visualização',
    `expira_em` TIMESTAMP NULL COMMENT 'Data/hora de expiração do token',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notificacao` (`notificacao_id`),
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_colaborador` (`colaborador_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_enviado` (`enviado`),
    FOREIGN KEY (`notificacao_id`) REFERENCES `notificacoes_sistema`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários
ALTER TABLE `notificacoes_push` COMMENT = 'Registro de notificações push enviadas com tokens para login automático';
