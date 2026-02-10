-- ============================================
-- MIGRAÇÃO: Permitir Quantidade de Vagas Ilimitadas
-- Data: 2026-02-10
-- ============================================

-- Altera o campo quantidade_vagas para permitir NULL (ilimitado)
ALTER TABLE vagas 
MODIFY COLUMN quantidade_vagas INT NULL DEFAULT 1 
COMMENT 'NULL = ilimitado';

-- Atualiza vagas que tinham 0 ou valores muito altos para NULL (ilimitado)
UPDATE vagas 
SET quantidade_vagas = NULL 
WHERE quantidade_vagas = 0 OR quantidade_vagas >= 9999;
