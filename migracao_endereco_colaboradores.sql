-- Migração: Adicionar campos de endereço em colaboradores
-- Execute este script no banco de dados

SET @dbname = DATABASE();
SET @tablename = 'colaboradores';

-- Adiciona CEP se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cep') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN cep VARCHAR(10) NULL AFTER cnpj'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona logradouro se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'logradouro') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN logradouro VARCHAR(255) NULL AFTER cep'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona numero se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'numero') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN numero VARCHAR(20) NULL AFTER logradouro'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona complemento se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'complemento') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN complemento VARCHAR(255) NULL AFTER numero'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona bairro se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'bairro') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN bairro VARCHAR(100) NULL AFTER complemento'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona cidade_endereco se não existir (para diferenciar de cidade da empresa)
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cidade_endereco') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN cidade_endereco VARCHAR(100) NULL AFTER bairro'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona estado_endereco se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'estado_endereco') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN estado_endereco VARCHAR(2) NULL AFTER cidade_endereco'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

