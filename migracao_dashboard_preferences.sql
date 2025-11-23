-- Migração: Tabela de Preferências do Dashboard
-- Criado em: 2025-01-23
-- Descrição: Armazena configurações personalizadas do dashboard (margem, altura de células, tema, etc)

-- Cria tabela de preferências do dashboard
CREATE TABLE IF NOT EXISTS `dashboard_preferences` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` INT(11) NOT NULL,
    `configuracao_chave` VARCHAR(100) NOT NULL,
    `configuracao_valor` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuario_chave` (`usuario_id`, `configuracao_chave`),
    KEY `idx_usuario` (`usuario_id`),
    KEY `idx_chave` (`configuracao_chave`),
    CONSTRAINT `fk_dashboard_pref_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preferências e configurações do dashboard por usuário';

-- Índices adicionais para performance
CREATE INDEX `idx_created_at` ON `dashboard_preferences` (`created_at`);

-- Comentários nas colunas
ALTER TABLE `dashboard_preferences` 
    MODIFY COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'ID da preferência',
    MODIFY COLUMN `usuario_id` INT(11) NOT NULL COMMENT 'ID do usuário',
    MODIFY COLUMN `configuracao_chave` VARCHAR(100) NOT NULL COMMENT 'Chave da configuração (ex: dashboard_settings)',
    MODIFY COLUMN `configuracao_valor` TEXT NULL COMMENT 'Valor da configuração em JSON',
    MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    MODIFY COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de atualização';

