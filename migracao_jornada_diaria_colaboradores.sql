-- Migração: Adicionar campo jornada_diaria_horas em colaboradores
-- Execute este script no banco de dados

SET @dbname = DATABASE();
SET @tablename = 'colaboradores';

-- Adiciona jornada_diaria_horas se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'jornada_diaria_horas') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN jornada_diaria_horas DECIMAL(4,2) DEFAULT 8.00 COMMENT ''Jornada de trabalho diária em horas (padrão 8h)'' AFTER tipo_contrato'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Atualiza colaboradores existentes que não têm valor definido
UPDATE colaboradores 
SET jornada_diaria_horas = 8.00 
WHERE jornada_diaria_horas IS NULL OR jornada_diaria_horas = 0;
