-- Migração: Adicionar campos de tipo de valor e valor fixo em tipos_bonus
-- Execute este script no banco de dados

-- Adiciona campos na tabela tipos_bonus
ALTER TABLE tipos_bonus
ADD COLUMN tipo_valor ENUM('fixo', 'informativo', 'variavel') DEFAULT 'variavel' 
    COMMENT 'Tipo de valor: fixo (usa valor_fixo), informativo (não soma no total), variavel (usa valor do colaborador)',
ADD COLUMN valor_fixo DECIMAL(10,2) NULL 
    COMMENT 'Valor fixo do bônus (usado quando tipo_valor = fixo)',
ADD INDEX idx_tipo_valor (tipo_valor);

-- Atualiza registros existentes para 'variavel' (comportamento atual)
UPDATE tipos_bonus SET tipo_valor = 'variavel' WHERE tipo_valor IS NULL;

