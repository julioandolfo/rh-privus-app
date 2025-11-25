-- Migração: Adicionar campos de desconto por faltas em tipos_bonus
-- Execute este script no banco de dados

-- Adiciona campos na tabela tipos_bonus
ALTER TABLE tipos_bonus
ADD COLUMN permite_desconto_faltas BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, este bônus pode ser descontado quando houver faltas',
ADD COLUMN valor_desconto_por_falta DECIMAL(10,2) NULL 
    COMMENT 'Valor fixo a descontar por falta (NULL = desconto proporcional ao valor do bônus)',
ADD INDEX idx_permite_desconto_faltas (permite_desconto_faltas);

-- Adiciona campo na tabela fechamentos_pagamento_bonus para armazenar desconto por faltas
ALTER TABLE fechamentos_pagamento_bonus
ADD COLUMN desconto_faltas DECIMAL(10,2) DEFAULT 0 
    COMMENT 'Valor descontado do bônus devido a faltas',
ADD COLUMN valor_original DECIMAL(10,2) NULL 
    COMMENT 'Valor original do bônus antes do desconto por faltas';

