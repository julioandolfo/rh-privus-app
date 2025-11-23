-- Adiciona campo Instagram na tabela candidatos
ALTER TABLE candidatos 
ADD COLUMN IF NOT EXISTS instagram VARCHAR(255) NULL AFTER portfolio;

