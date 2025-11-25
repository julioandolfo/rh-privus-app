-- Migração: Sistema Dinâmico de Desconto de Bônus por Tipos de Ocorrências
-- Execute este script no banco de dados

-- Remove campos antigos (se existirem) - vamos usar sistema mais flexível
ALTER TABLE tipos_bonus
DROP COLUMN IF EXISTS permite_desconto_faltas,
DROP COLUMN IF EXISTS valor_desconto_por_falta;

-- Cria tabela de relacionamento tipos_bonus e tipos_ocorrencias
CREATE TABLE IF NOT EXISTS tipos_bonus_ocorrencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_bonus_id INT NOT NULL,
    tipo_ocorrencia_id INT NOT NULL,
    tipo_desconto ENUM('proporcional', 'fixo', 'percentual', 'total') DEFAULT 'proporcional'
        COMMENT 'proporcional = divide pelo número de dias úteis, fixo = valor fixo por ocorrência, percentual = % do valor do bônus, total = zera o bônus completamente',
    valor_desconto DECIMAL(10,2) NULL
        COMMENT 'Valor fixo ou percentual (depende do tipo_desconto). NULL = proporcional ao valor do bônus',
    desconta_apenas_aprovadas BOOLEAN DEFAULT TRUE
        COMMENT 'Se TRUE, só desconta ocorrências aprovadas',
    desconta_banco_horas BOOLEAN DEFAULT FALSE
        COMMENT 'Se TRUE, também desconta ocorrências que descontam do banco de horas',
    periodo_dias INT NULL
        COMMENT 'Período em dias para considerar ocorrências (NULL = período do fechamento)',
    verificar_periodo_anterior BOOLEAN DEFAULT FALSE
        COMMENT 'Se TRUE, verifica ocorrências no período anterior ao fechamento (ex: mês anterior)',
    periodo_anterior_meses INT DEFAULT 1
        COMMENT 'Quantos meses anteriores verificar (usado quando verificar_periodo_anterior = TRUE)',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE CASCADE,
    UNIQUE KEY uk_bonus_ocorrencia (tipo_bonus_id, tipo_ocorrencia_id),
    INDEX idx_tipo_bonus (tipo_bonus_id),
    INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atualiza tabela fechamentos_pagamento_bonus para armazenar detalhes do desconto
ALTER TABLE fechamentos_pagamento_bonus
ADD COLUMN IF NOT EXISTS desconto_ocorrencias DECIMAL(10,2) DEFAULT 0 
    COMMENT 'Valor total descontado por ocorrências',
ADD COLUMN IF NOT EXISTS valor_original DECIMAL(10,2) NULL 
    COMMENT 'Valor original do bônus antes do desconto por ocorrências',
ADD COLUMN IF NOT EXISTS detalhes_desconto JSON NULL
    COMMENT 'Detalhes do desconto: quais ocorrências descontaram, quantidades, valores';

-- Cria índice para melhor performance
ALTER TABLE fechamentos_pagamento_bonus
ADD INDEX IF NOT EXISTS idx_desconto_ocorrencias (desconto_ocorrencias);

-- Se a tabela tipos_bonus_ocorrencias já existir, atualiza os campos
ALTER TABLE tipos_bonus_ocorrencias
MODIFY COLUMN tipo_desconto ENUM('proporcional', 'fixo', 'percentual', 'total') DEFAULT 'proporcional'
    COMMENT 'proporcional = divide pelo número de dias úteis, fixo = valor fixo por ocorrência, percentual = % do valor do bônus, total = zera o bônus completamente',
ADD COLUMN IF NOT EXISTS verificar_periodo_anterior BOOLEAN DEFAULT FALSE
    COMMENT 'Se TRUE, verifica ocorrências no período anterior ao fechamento (ex: mês anterior)',
ADD COLUMN IF NOT EXISTS periodo_anterior_meses INT DEFAULT 1
    COMMENT 'Quantos meses anteriores verificar (usado quando verificar_periodo_anterior = TRUE)';
