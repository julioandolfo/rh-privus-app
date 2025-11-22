-- Adiciona campos de público alvo na tabela celebracoes
-- MariaDB não suporta IF NOT EXISTS em ALTER TABLE, então verificamos antes

-- Verifica e adiciona coluna publico_alvo
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND COLUMN_NAME = 'publico_alvo');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE celebracoes ADD COLUMN publico_alvo ENUM(\'todos\', \'especifico\', \'empresa\', \'setor\', \'cargo\') DEFAULT \'especifico\' AFTER destinatario_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona coluna empresa_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND COLUMN_NAME = 'empresa_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE celebracoes ADD COLUMN empresa_id INT NULL AFTER publico_alvo',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona coluna setor_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND COLUMN_NAME = 'setor_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE celebracoes ADD COLUMN setor_id INT NULL AFTER empresa_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona coluna cargo_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND COLUMN_NAME = 'cargo_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE celebracoes ADD COLUMN cargo_id INT NULL AFTER setor_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona índice idx_publico_alvo
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND INDEX_NAME = 'idx_publico_alvo');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE celebracoes ADD INDEX idx_publico_alvo (publico_alvo)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona índice idx_empresa
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND INDEX_NAME = 'idx_empresa');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE celebracoes ADD INDEX idx_empresa (empresa_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona índice idx_setor
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND INDEX_NAME = 'idx_setor');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE celebracoes ADD INDEX idx_setor (setor_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona índice idx_cargo
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND INDEX_NAME = 'idx_cargo');
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE celebracoes ADD INDEX idx_cargo (cargo_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona foreign key fk_celebracoes_empresa
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND CONSTRAINT_NAME = 'fk_celebracoes_empresa');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE celebracoes ADD CONSTRAINT fk_celebracoes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona foreign key fk_celebracoes_setor
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND CONSTRAINT_NAME = 'fk_celebracoes_setor');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE celebracoes ADD CONSTRAINT fk_celebracoes_setor FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona foreign key fk_celebracoes_cargo
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'celebracoes' 
    AND CONSTRAINT_NAME = 'fk_celebracoes_cargo');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE celebracoes ADD CONSTRAINT fk_celebracoes_cargo FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

