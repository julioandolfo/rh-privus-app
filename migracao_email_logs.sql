-- Script de Migração: Tabela de Logs de Emails
-- Execute este script para criar a tabela de logs de emails enviados

-- Cria tabela de logs de emails
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Informações do email
    email_destinatario VARCHAR(255) NOT NULL COMMENT 'Email do destinatário',
    nome_destinatario VARCHAR(255) NULL COMMENT 'Nome do destinatário',
    assunto VARCHAR(500) NOT NULL COMMENT 'Assunto do email',
    
    -- Template usado (se aplicável)
    template_codigo VARCHAR(50) NULL COMMENT 'Código do template usado',
    template_nome VARCHAR(255) NULL COMMENT 'Nome do template usado',
    
    -- Status do envio
    status ENUM('sucesso', 'erro') NOT NULL DEFAULT 'sucesso' COMMENT 'Status do envio',
    erro_mensagem TEXT NULL COMMENT 'Mensagem de erro (se houver)',
    
    -- Contexto do envio
    origem VARCHAR(100) NULL COMMENT 'Origem/contexto do envio (ex: novo_colaborador, promocao, cron)',
    usuario_id INT NULL COMMENT 'ID do usuário que disparou o envio (se aplicável)',
    colaborador_id INT NULL COMMENT 'ID do colaborador relacionado (se aplicável)',
    empresa_id INT NULL COMMENT 'ID da empresa relacionada (se aplicável)',
    
    -- Metadados
    ip_origem VARCHAR(45) NULL COMMENT 'IP de origem da requisição',
    user_agent TEXT NULL COMMENT 'User Agent do navegador',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do envio',
    
    -- Índices
    INDEX idx_email_logs_status (status),
    INDEX idx_email_logs_destinatario (email_destinatario),
    INDEX idx_email_logs_template (template_codigo),
    INDEX idx_email_logs_origem (origem),
    INDEX idx_email_logs_usuario (usuario_id),
    INDEX idx_email_logs_colaborador (colaborador_id),
    INDEX idx_email_logs_empresa (empresa_id),
    INDEX idx_email_logs_created (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de emails enviados pelo sistema';

-- Chaves estrangeiras (opcionais - descomentar e executar separadamente se necessário)
-- ALTER TABLE email_logs ADD FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;
-- ALTER TABLE email_logs ADD FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL;
-- ALTER TABLE email_logs ADD FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL;

-- O índice idx_email_logs_created já cobre buscas por data

-- Comentário para documentação
-- Esta tabela armazena todos os logs de emails enviados pelo sistema
-- Campos importantes:
-- - status: 'sucesso' ou 'erro'
-- - template_codigo: código do template usado (se usar sistema de templates)
-- - origem: contexto de onde o email foi disparado
-- - erro_mensagem: detalhe do erro quando status = 'erro'
