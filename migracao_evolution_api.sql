-- Migração: Integração Evolution API (WhatsApp)
-- Data: 2026-03-08
--
-- IMPORTANTE: A pesquisa de humor via WhatsApp grava diretamente na tabela
-- `emocoes` já existente, adicionando apenas a coluna `canal` para distinguir
-- o origin web do origin whatsapp. Não há tabela separada de humor.

-- Adiciona coluna canal na tabela emocoes (se ainda não existir)
ALTER TABLE `emocoes`
    ADD COLUMN IF NOT EXISTS `canal` ENUM('web', 'whatsapp') NOT NULL DEFAULT 'web'
        COMMENT 'Canal de registro da emoção' AFTER `descricao`;

-- Configuração da Evolution API
CREATE TABLE IF NOT EXISTS `evolution_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `api_url` VARCHAR(500) NOT NULL COMMENT 'URL base da Evolution API (ex: https://api.suaempresa.com)',
    `api_key` VARCHAR(500) NOT NULL COMMENT 'API Key global da Evolution API',
    `instance_name` VARCHAR(100) NOT NULL COMMENT 'Nome da instância (ex: rh-privus)',
    `ativo` TINYINT(1) DEFAULT 1,
    `horario_pesquisa_humor` TIME DEFAULT '09:00:00' COMMENT 'Horário diário de envio da pesquisa de humor',
    `pesquisa_humor_ativa` TINYINT(1) DEFAULT 0 COMMENT 'Se a pesquisa diária de humor está ativada',
    `dias_pesquisa_humor` VARCHAR(20) DEFAULT '1,2,3,4,5' COMMENT 'Dias da semana (0=Dom, 1=Seg...6=Sáb)',
    `mensagem_pesquisa_humor` TEXT COMMENT 'Mensagem personalizada da pesquisa de humor',
    `notificacoes_whatsapp_ativas` TINYINT(1) DEFAULT 1 COMMENT 'Enviar notificações do sistema via WA',
    `intervalo_entre_mensagens` INT DEFAULT 7 COMMENT 'Segundos de pausa entre disparos em lote (mínimo 3)',
    `max_mensagens_por_hora` INT DEFAULT 80 COMMENT 'Limite de mensagens por hora (0 = sem limite)',
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campo de WhatsApp nos colaboradores
ALTER TABLE `colaboradores` 
    ADD COLUMN IF NOT EXISTS `whatsapp_numero` VARCHAR(20) NULL COMMENT 'Número WhatsApp com DDD (ex: 11999999999)',
    ADD COLUMN IF NOT EXISTS `whatsapp_ativo` TINYINT(1) DEFAULT 1 COMMENT 'Se deve receber notificações no WhatsApp';

-- Log de mensagens enviadas pelo WhatsApp
CREATE TABLE IF NOT EXISTS `evolution_mensagens_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` INT NULL,
    `numero_destino` VARCHAR(20) NOT NULL,
    `tipo` ENUM('notificacao', 'pesquisa_humor', 'manual', 'resposta') DEFAULT 'notificacao',
    `mensagem` TEXT NOT NULL,
    `status` ENUM('enviado', 'erro', 'pendente') DEFAULT 'pendente',
    `response_data` JSON NULL COMMENT 'Resposta da Evolution API',
    `message_id` VARCHAR(100) NULL COMMENT 'ID da mensagem retornado pela Evolution API',
    `erro_detalhe` TEXT NULL,
    `enviado_em` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_colaborador` (`colaborador_id`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle de envios da pesquisa de humor (evita duplicatas no dia)
-- Nota: as respostas são armazenadas diretamente na tabela `emocoes` com canal='whatsapp'
CREATE TABLE IF NOT EXISTS `humor_pesquisa_envios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` INT NOT NULL,
    `data_envio` DATE NOT NULL,
    `enviado` TINYINT(1) DEFAULT 0,
    `respondido` TINYINT(1) DEFAULT 0,
    `mensagem_log_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_colaborador_data` (`colaborador_id`, `data_envio`),
    INDEX `idx_data_envio` (`data_envio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de mensagens WhatsApp (rate limiting e envio controlado)
-- Notificações são enfileiradas aqui e processadas pelo cron processar_fila_whatsapp.php
CREATE TABLE IF NOT EXISTS `evolution_fila_mensagens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` INT NULL COMMENT 'Colaborador destinatário',
    `numero` VARCHAR(20) NOT NULL COMMENT 'Número formatado com DDI',
    `titulo` VARCHAR(255) NOT NULL,
    `mensagem` TEXT NOT NULL,
    `url` VARCHAR(500) NULL,
    `tipo` ENUM('notificacao','pesquisa_humor','boas_vindas','manual') DEFAULT 'notificacao',
    `status` ENUM('pendente','enviando','enviado','erro') DEFAULT 'pendente',
    `tentativas` TINYINT DEFAULT 0,
    `erro_detalhe` TEXT NULL,
    `agendado_para` DATETIME NULL COMMENT 'NULL = enviar ASAP',
    `enviado_em` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status_agendado` (`status`, `agendado_para`),
    INDEX `idx_colaborador` (`colaborador_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhooks recebidos da Evolution API (log bruto)
CREATE TABLE IF NOT EXISTS `evolution_webhooks_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evento` VARCHAR(100) NULL COMMENT 'Tipo do evento (messages.upsert, etc.)',
    `instancia` VARCHAR(100) NULL,
    `numero_remetente` VARCHAR(30) NULL,
    `mensagem_recebida` TEXT NULL,
    `payload_raw` LONGTEXT NULL COMMENT 'JSON completo do webhook',
    `processado` TINYINT(1) DEFAULT 0,
    `acao_tomada` VARCHAR(100) NULL COMMENT 'O que o sistema fez com a mensagem',
    `colaborador_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_numero` (`numero_remetente`),
    INDEX `idx_processado` (`processado`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
