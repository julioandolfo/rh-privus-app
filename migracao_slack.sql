-- Migração: Integração Slack
-- Data: 2026-03-08

-- Configuração do Bot Slack
CREATE TABLE IF NOT EXISTS `slack_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bot_token` VARCHAR(200) NOT NULL COMMENT 'Bot User OAuth Token (xoxb-...)',
    `workspace_nome` VARCHAR(100) NULL COMMENT 'Nome do workspace (informativo)',
    `bot_user_id` VARCHAR(30) NULL COMMENT 'ID do bot (preenchido automaticamente via auth.test)',
    `canal_comunicados` VARCHAR(100) NULL COMMENT 'Canal padrão para comunicados (#geral, C12345...)',
    `ativo` TINYINT(1) DEFAULT 1,
    `notificacoes_slack_ativas` TINYINT(1) DEFAULT 1 COMMENT 'Enviar notificações individuais via DM',
    `comunicados_no_canal` TINYINT(1) DEFAULT 1 COMMENT 'Postar comunicados no canal além dos DMs',
    `intervalo_entre_mensagens` INT DEFAULT 2 COMMENT 'Segundos entre disparos (Slack é mais permissivo que WA)',
    `max_mensagens_por_hora` INT DEFAULT 300 COMMENT 'Limite por hora (0 = sem limite)',
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slack User ID por colaborador
ALTER TABLE `colaboradores`
    ADD COLUMN IF NOT EXISTS `slack_user_id` VARCHAR(30) NULL
        COMMENT 'ID do usuário no Slack (Uxxxxxxxxx — preenchido via sincronização)',
    ADD COLUMN IF NOT EXISTS `slack_ativo` TINYINT(1) DEFAULT 1
        COMMENT 'Se deve receber notificações no Slack';

-- Fila de mensagens Slack (rate limiting e envio controlado)
CREATE TABLE IF NOT EXISTS `slack_fila_mensagens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` INT NULL,
    `canal_destino` VARCHAR(100) NOT NULL COMMENT 'User ID (Uxxxxxxx) ou Canal (#geral, Cxxxxxxx)',
    `titulo` VARCHAR(255) NOT NULL,
    `mensagem` TEXT NOT NULL,
    `url` VARCHAR(500) NULL,
    `tipo` ENUM('notificacao','comunicado','manual') DEFAULT 'notificacao',
    `status` ENUM('pendente','enviando','enviado','erro') DEFAULT 'pendente',
    `tentativas` TINYINT DEFAULT 0,
    `erro_detalhe` TEXT NULL,
    `agendado_para` DATETIME NULL,
    `enviado_em` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status_agendado` (`status`, `agendado_para`),
    INDEX `idx_colaborador` (`colaborador_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de mensagens enviadas pelo Slack
CREATE TABLE IF NOT EXISTS `slack_mensagens_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` INT NULL,
    `canal_destino` VARCHAR(100) NOT NULL,
    `tipo` ENUM('notificacao','comunicado','manual') DEFAULT 'notificacao',
    `titulo` VARCHAR(255) NULL,
    `mensagem` TEXT NOT NULL,
    `status` ENUM('enviado','erro') DEFAULT 'enviado',
    `ts` VARCHAR(50) NULL COMMENT 'Timestamp Slack da mensagem (para editar/deletar)',
    `erro_detalhe` TEXT NULL,
    `enviado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_colaborador` (`colaborador_id`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_enviado` (`enviado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
