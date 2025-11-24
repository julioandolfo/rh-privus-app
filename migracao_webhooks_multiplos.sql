-- Migração: Suporte a Múltiplos Webhooks do Autentique
-- Atualiza a estrutura para suportar webhook de documento e webhook de assinatura separados

-- Adiciona campos para webhook de documento
ALTER TABLE autentique_config 
ADD COLUMN webhook_documento_url VARCHAR(500) NULL COMMENT 'URL do webhook para eventos de documento' AFTER webhook_url,
ADD COLUMN webhook_documento_secret VARCHAR(255) NULL COMMENT 'Secret do webhook de documento' AFTER webhook_secret,
ADD COLUMN webhook_assinatura_url VARCHAR(500) NULL COMMENT 'URL do webhook para eventos de assinatura' AFTER webhook_documento_secret,
ADD COLUMN webhook_assinatura_secret VARCHAR(255) NULL COMMENT 'Secret do webhook de assinatura' AFTER webhook_assinatura_url;

-- Migra dados antigos (se existirem) para webhook de documento
UPDATE autentique_config 
SET webhook_documento_url = webhook_url,
    webhook_documento_secret = webhook_secret
WHERE webhook_url IS NOT NULL AND webhook_documento_url IS NULL;

