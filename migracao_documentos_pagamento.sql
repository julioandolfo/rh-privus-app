-- Migração: Sistema de Anexo de Documentos para Pagamento
-- Data: 2024-12-19

-- Adiciona campos na tabela fechamentos_pagamento_itens para controle de documentos
ALTER TABLE `fechamentos_pagamento_itens` 
ADD COLUMN `documento_anexo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Caminho do arquivo anexado' AFTER `valor_total`,
ADD COLUMN `documento_status` ENUM('pendente', 'enviado', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'pendente' COMMENT 'Status do documento' AFTER `documento_anexo`,
ADD COLUMN `documento_data_envio` DATETIME NULL DEFAULT NULL COMMENT 'Data/hora do envio do documento' AFTER `documento_status`,
ADD COLUMN `documento_data_aprovacao` DATETIME NULL DEFAULT NULL COMMENT 'Data/hora da aprovação/rejeição' AFTER `documento_data_envio`,
ADD COLUMN `documento_aprovado_por` INT NULL DEFAULT NULL COMMENT 'ID do usuário que aprovou/rejeitou' AFTER `documento_data_aprovacao`,
ADD COLUMN `documento_observacoes` TEXT NULL DEFAULT NULL COMMENT 'Observações do admin ao aprovar/rejeitar' AFTER `documento_aprovado_por`,
ADD INDEX `idx_documento_status` (`documento_status`),
ADD INDEX `idx_documento_data_envio` (`documento_data_envio`);

-- Adiciona campo para tornar documento obrigatório ou não por fechamento
ALTER TABLE `fechamentos_pagamento` 
ADD COLUMN `documento_obrigatorio` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se documento é obrigatório para recebimento' AFTER `status`;

-- Cria tabela para histórico de alterações de documentos (opcional, para auditoria)
CREATE TABLE IF NOT EXISTS `fechamentos_pagamento_documentos_historico` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL COMMENT 'ID do item do fechamento',
    `acao` ENUM('enviado', 'aprovado', 'rejeitado', 'rejeitado_reenviado') NOT NULL,
    `documento_anexo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Caminho do arquivo',
    `usuario_id` INT NULL DEFAULT NULL COMMENT 'ID do usuário que executou a ação',
    `observacoes` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `fechamentos_pagamento_itens`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de alterações de documentos de pagamento';

