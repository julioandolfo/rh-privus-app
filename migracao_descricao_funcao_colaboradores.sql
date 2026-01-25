-- Migração: Adiciona campo descricao_funcao na tabela colaboradores
-- Execute este script para adicionar o campo de descrição de função

SET @dbname = DATABASE();
SET @tablename = 'colaboradores';

-- Adiciona descricao_funcao se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'descricao_funcao') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN descricao_funcao TEXT NULL AFTER cargo_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Confirma a alteração
SELECT 'Campo descricao_funcao adicionado à tabela colaboradores (se não existia)' AS resultado;
