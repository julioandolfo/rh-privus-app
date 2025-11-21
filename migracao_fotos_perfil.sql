-- Migração: Sistema de Fotos de Perfil
-- Execute este script no banco de dados

-- Adicionar campo foto em colaboradores
ALTER TABLE colaboradores 
ADD COLUMN foto VARCHAR(255) NULL AFTER observacoes,
ADD INDEX idx_foto (foto);

-- Adicionar campo foto em usuarios
ALTER TABLE usuarios 
ADD COLUMN foto VARCHAR(255) NULL AFTER setor_id,
ADD INDEX idx_foto (foto);

