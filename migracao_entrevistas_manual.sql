-- Migração: Permitir entrevistas sem candidatura (entrevistas manuais)
-- Execute este script no banco de dados

-- Modifica a tabela entrevistas para permitir candidatura_id NULL
ALTER TABLE entrevistas 
MODIFY COLUMN candidatura_id INT NULL;

-- Remove a foreign key constraint antiga (se existir)
-- Busca o nome real da constraint dinamicamente
SET @constraint_name = NULL;
SELECT CONSTRAINT_NAME INTO @constraint_name
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'entrevistas'
  AND COLUMN_NAME = 'candidatura_id'
  AND REFERENCED_TABLE_NAME = 'candidaturas'
LIMIT 1;

SET @sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE entrevistas DROP FOREIGN KEY `', @constraint_name, '`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona nova foreign key que permite NULL (se não existir)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND CONSTRAINT_NAME = 'fk_entrevistas_candidatura'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE entrevistas ADD CONSTRAINT fk_entrevistas_candidatura FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona campos para candidato manual (quando não há candidatura)
-- Verifica se a coluna já existe antes de adicionar
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND COLUMN_NAME = 'candidato_nome_manual'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE entrevistas ADD COLUMN candidato_nome_manual VARCHAR(255) NULL AFTER candidatura_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND COLUMN_NAME = 'candidato_email_manual'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE entrevistas ADD COLUMN candidato_email_manual VARCHAR(255) NULL AFTER candidato_nome_manual',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND COLUMN_NAME = 'candidato_telefone_manual'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE entrevistas ADD COLUMN candidato_telefone_manual VARCHAR(20) NULL AFTER candidato_email_manual',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND COLUMN_NAME = 'vaga_id_manual'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE entrevistas ADD COLUMN vaga_id_manual INT NULL AFTER candidato_telefone_manual',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND COLUMN_NAME = 'coluna_kanban'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE entrevistas ADD COLUMN coluna_kanban VARCHAR(50) NULL AFTER vaga_id_manual',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona foreign key para vaga manual (se não existir)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND CONSTRAINT_NAME = 'fk_entrevistas_vaga_manual'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE entrevistas ADD CONSTRAINT fk_entrevistas_vaga_manual FOREIGN KEY (vaga_id_manual) REFERENCES vagas(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índices (se não existirem)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND INDEX_NAME = 'idx_candidatura_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE entrevistas ADD INDEX idx_candidatura_id (candidatura_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND INDEX_NAME = 'idx_vaga_manual'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE entrevistas ADD INDEX idx_vaga_manual (vaga_id_manual)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrevistas'
      AND INDEX_NAME = 'idx_coluna_kanban'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE entrevistas ADD INDEX idx_coluna_kanban (coluna_kanban)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
