-- Adiciona coluna token_resposta nas tabelas de envios se não existir

-- Para pesquisas_satisfacao_envios
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pesquisas_satisfacao_envios' 
    AND COLUMN_NAME = 'token_resposta');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE pesquisas_satisfacao_envios ADD COLUMN token_resposta VARCHAR(255) UNIQUE NULL COMMENT \'Token único para resposta sem login\' AFTER colaborador_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índice se não existir
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pesquisas_satisfacao_envios' 
    AND INDEX_NAME = 'idx_token_resposta');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE pesquisas_satisfacao_envios ADD INDEX idx_token_resposta (token_resposta)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Para pesquisas_rapidas_envios
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pesquisas_rapidas_envios' 
    AND COLUMN_NAME = 'token_resposta');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE pesquisas_rapidas_envios ADD COLUMN token_resposta VARCHAR(255) UNIQUE NULL COMMENT \'Token único para resposta sem login\' AFTER colaborador_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índice se não existir
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'pesquisas_rapidas_envios' 
    AND INDEX_NAME = 'idx_token_resposta');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE pesquisas_rapidas_envios ADD INDEX idx_token_resposta (token_resposta)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

