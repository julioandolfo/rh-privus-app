-- Migração: Adicionar campo senha em colaboradores
-- Execute este script no banco de dados

-- Adicionar campo senha_hash em colaboradores
ALTER TABLE colaboradores 
ADD COLUMN senha_hash VARCHAR(255) NULL AFTER foto,
ADD INDEX idx_senha_hash (senha_hash);

-- Nota: As senhas serão opcionais inicialmente
-- Quando um colaborador tiver senha definida, poderá fazer login usando CPF ou email_pessoal

