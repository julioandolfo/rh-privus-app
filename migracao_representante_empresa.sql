-- Migração: Adicionar campos de representante da empresa para assinatura de contratos

ALTER TABLE autentique_config 
ADD COLUMN IF NOT EXISTS representante_nome VARCHAR(255) NULL COMMENT 'Nome do representante/sócio que assina contratos',
ADD COLUMN IF NOT EXISTS representante_email VARCHAR(255) NULL COMMENT 'Email do representante para assinatura',
ADD COLUMN IF NOT EXISTS representante_cpf VARCHAR(14) NULL COMMENT 'CPF do representante',
ADD COLUMN IF NOT EXISTS representante_cargo VARCHAR(100) NULL COMMENT 'Cargo do representante (ex: Sócio, Diretor, RH)',
ADD COLUMN IF NOT EXISTS empresa_cnpj VARCHAR(18) NULL COMMENT 'CNPJ da empresa contratante';

-- Índice para busca
ALTER TABLE autentique_config ADD INDEX IF NOT EXISTS idx_representante_email (representante_email);
