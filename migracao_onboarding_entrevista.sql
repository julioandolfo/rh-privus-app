-- Migração: Adicionar suporte a entrevistas manuais no onboarding

-- Remove foreign key de candidatura_id se existir (para poder modificar para NULL)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND CONSTRAINT_NAME = 'onboarding_ibfk_1'
);

SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE onboarding DROP FOREIGN KEY onboarding_ibfk_1', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove outra possível foreign key
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND CONSTRAINT_NAME = 'fk_onboarding_candidatura'
);

SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE onboarding DROP FOREIGN KEY fk_onboarding_candidatura', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona coluna entrevista_id se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND COLUMN_NAME = 'entrevista_id'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE onboarding ADD COLUMN entrevista_id INT NULL AFTER candidatura_id', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Modifica candidatura_id para permitir NULL (para entrevistas manuais)
ALTER TABLE onboarding MODIFY COLUMN candidatura_id INT NULL;

-- Recria foreign key de candidatura_id permitindo NULL
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND CONSTRAINT_NAME = 'fk_onboarding_candidatura'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE onboarding ADD CONSTRAINT fk_onboarding_candidatura FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona foreign key para entrevista_id (se não existir)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND CONSTRAINT_NAME = 'fk_onboarding_entrevista'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE onboarding ADD CONSTRAINT fk_onboarding_entrevista FOREIGN KEY (entrevista_id) REFERENCES entrevistas(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índice para entrevista_id (se não existir)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'onboarding' 
    AND INDEX_NAME = 'idx_entrevista'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE onboarding ADD INDEX idx_entrevista (entrevista_id)', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

