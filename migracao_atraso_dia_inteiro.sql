-- Migração: Permitir considerar atraso como dia inteiro
-- Execute este script no banco de dados

-- 1. Adicionar campo no tipo de ocorrência para permitir considerar dia inteiro
ALTER TABLE tipos_ocorrencias
ADD COLUMN permite_considerar_dia_inteiro BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, permite marcar ocorrência como dia inteiro (8h) ao invés de apenas minutos';

-- 2. Adicionar campo na ocorrência para marcar se considera dia inteiro
ALTER TABLE ocorrencias
ADD COLUMN considera_dia_inteiro BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, considera como falta do dia inteiro (8h) ao invés de apenas minutos de atraso',
ADD INDEX idx_considera_dia_inteiro (considera_dia_inteiro);

