-- Migração: Sistema de Salários, Promoções, Horas Extras e Pagamentos
-- Execute este script no banco de dados
-- Esta versão verifica se as colunas já existem antes de adicioná-las

-- 1. Adicionar campos em colaboradores (verifica se já existem antes de adicionar)
SET @dbname = DATABASE();
SET @tablename = 'colaboradores';

-- Adiciona salario se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'salario') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN salario DECIMAL(10,2) NULL AFTER tipo_contrato'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona pix se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'pix') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN pix VARCHAR(255) NULL AFTER salario'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona banco se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'banco') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN banco VARCHAR(100) NULL AFTER pix'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona agencia se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'agencia') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN agencia VARCHAR(20) NULL AFTER banco'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona conta se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'conta') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN conta VARCHAR(30) NULL AFTER agencia'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona tipo_conta se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'tipo_conta') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN tipo_conta ENUM(\'corrente\', \'poupanca\') NULL AFTER conta'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona cnpj se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cnpj') > 0,
    'SELECT 1',
    'ALTER TABLE colaboradores ADD COLUMN cnpj VARCHAR(18) NULL AFTER cpf'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona índices se não existirem
SET @indexexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_salario');
SET @sqlstmt = IF(@indexexists = 0, 'CREATE INDEX idx_salario ON colaboradores(salario)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @indexexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_cnpj');
SET @sqlstmt = IF(@indexexists = 0, 'CREATE INDEX idx_cnpj ON colaboradores(cnpj)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Adicionar campo % hora extra em empresas (verifica se já existe antes de adicionar)
SET @tablename = 'empresas';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'percentual_hora_extra') > 0,
    'SELECT 1',
    'ALTER TABLE empresas ADD COLUMN percentual_hora_extra DECIMAL(5,2) DEFAULT 50.00 AFTER status'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona índice se não existir
SET @indexexists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_percentual_hora_extra');
SET @sqlstmt = IF(@indexexists = 0, 'CREATE INDEX idx_percentual_hora_extra ON empresas(percentual_hora_extra)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Criar tabela de histórico de promoções/salários
CREATE TABLE IF NOT EXISTS promocoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    salario_anterior DECIMAL(10,2) NOT NULL,
    salario_novo DECIMAL(10,2) NOT NULL,
    motivo TEXT NOT NULL,
    data_promocao DATE NOT NULL,
    usuario_id INT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_promocao (data_promocao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Criar tabela de horas extras
CREATE TABLE IF NOT EXISTS horas_extras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    data_trabalho DATE NOT NULL,
    quantidade_horas DECIMAL(5,2) NOT NULL COMMENT 'Quantidade de horas extras trabalhadas',
    valor_hora DECIMAL(10,2) NOT NULL COMMENT 'Valor da hora normal do colaborador',
    percentual_adicional DECIMAL(5,2) NOT NULL COMMENT '% adicional de hora extra',
    valor_total DECIMAL(10,2) NOT NULL COMMENT 'Valor total calculado',
    observacoes TEXT,
    usuario_id INT NULL COMMENT 'Usuário que cadastrou',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_trabalho (data_trabalho),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Criar tabela de fechamentos de pagamento
CREATE TABLE IF NOT EXISTS fechamentos_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    mes_referencia VARCHAR(7) NOT NULL COMMENT 'Formato: YYYY-MM',
    data_fechamento DATE NOT NULL,
    total_colaboradores INT DEFAULT 0,
    total_pagamento DECIMAL(12,2) DEFAULT 0.00,
    total_horas_extras DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('aberto', 'fechado', 'pago') DEFAULT 'aberto',
    observacoes TEXT,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_empresa (empresa_id),
    INDEX idx_mes_referencia (mes_referencia),
    INDEX idx_status (status),
    UNIQUE KEY uk_empresa_mes (empresa_id, mes_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Criar tabela de itens do fechamento (colaboradores incluídos)
CREATE TABLE IF NOT EXISTS fechamentos_pagamento_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fechamento_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    salario_base DECIMAL(10,2) NOT NULL,
    horas_extras DECIMAL(5,2) DEFAULT 0.00,
    valor_horas_extras DECIMAL(10,2) DEFAULT 0.00,
    descontos DECIMAL(10,2) DEFAULT 0.00,
    adicionais DECIMAL(10,2) DEFAULT 0.00,
    valor_total DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fechamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_fechamento (fechamento_id),
    INDEX idx_colaborador (colaborador_id),
    UNIQUE KEY uk_fechamento_colaborador (fechamento_id, colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
