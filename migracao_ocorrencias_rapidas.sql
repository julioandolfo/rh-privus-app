-- Migração: Adicionar campo para controlar ocorrências rápidas
-- Execute este script no banco de dados

ALTER TABLE tipos_ocorrencias
ADD COLUMN permite_ocorrencia_rapida BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, este tipo aparece como opção em ocorrências rápidas';

-- Marca alguns tipos padrão como permitidos para ocorrências rápidas
UPDATE tipos_ocorrencias 
SET permite_ocorrencia_rapida = TRUE 
WHERE codigo IN ('ocorrencia_rapida', 'elogio', 'advertencia', 'comportamento_inadequado');

