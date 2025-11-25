-- =====================================================
-- MIGRAÇÃO: Sistema de Fechamentos Extras/Esporádicos
-- Data: 2024
-- Descrição: Adiciona suporte para pagamentos extras, adiantamentos e bônus individuais
-- =====================================================

-- 1. Alterar tabela fechamentos_pagamento para suportar fechamentos extras
ALTER TABLE fechamentos_pagamento
ADD COLUMN tipo_fechamento ENUM('regular', 'extra') DEFAULT 'regular' AFTER empresa_id,
ADD COLUMN subtipo_fechamento VARCHAR(50) NULL COMMENT 'bonus_especifico, individual, grupal, adiantamento' AFTER tipo_fechamento,
ADD COLUMN data_pagamento DATE NULL COMMENT 'Data prevista/real de pagamento' AFTER data_fechamento,
ADD COLUMN descricao TEXT NULL COMMENT 'Descrição do fechamento extra' AFTER observacoes,
ADD COLUMN referencia_externa VARCHAR(100) NULL COMMENT 'Ex: "Meta Q1 2024", "Adiantamento Dezembro"' AFTER descricao,
ADD COLUMN permite_edicao TINYINT(1) DEFAULT 1 COMMENT 'Se permite edição após criação' AFTER status;

-- Adicionar índices
ALTER TABLE fechamentos_pagamento
ADD INDEX idx_tipo_fechamento (tipo_fechamento),
ADD INDEX idx_subtipo_fechamento (subtipo_fechamento),
ADD INDEX idx_data_pagamento (data_pagamento);

-- Remover UNIQUE KEY que impede múltiplos fechamentos no mesmo mês (apenas para fechamentos regulares)
-- Vamos criar uma validação em código PHP ao invés de constraint única
-- A constraint será aplicada apenas para tipo_fechamento = 'regular'
ALTER TABLE fechamentos_pagamento
DROP INDEX uk_empresa_mes;

-- Criar índice composto para validação de fechamentos regulares únicos por mês/empresa
ALTER TABLE fechamentos_pagamento
ADD INDEX idx_empresa_mes_tipo (empresa_id, mes_referencia, tipo_fechamento);

-- 2. Alterar tabela fechamentos_pagamento_itens para suportar fechamentos extras
ALTER TABLE fechamentos_pagamento_itens
ADD COLUMN inclui_salario TINYINT(1) DEFAULT 1 COMMENT 'Se inclui salário no cálculo' AFTER colaborador_id,
ADD COLUMN inclui_horas_extras TINYINT(1) DEFAULT 1 COMMENT 'Se inclui horas extras' AFTER inclui_salario,
ADD COLUMN inclui_bonus_automaticos TINYINT(1) DEFAULT 1 COMMENT 'Se inclui bônus automáticos' AFTER inclui_horas_extras,
ADD COLUMN valor_manual DECIMAL(10,2) NULL COMMENT 'Valor manual (para adiantamentos, etc)' AFTER adicionais,
ADD COLUMN motivo TEXT NULL COMMENT 'Motivo do pagamento extra' AFTER observacoes;

-- 3. Criar tabela de configurações de fechamentos extras
CREATE TABLE IF NOT EXISTS fechamentos_pagamento_extras_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL COMMENT 'Ex: "Bônus Alimentação Mensal"',
    tipo_bonus_id INT NULL COMMENT 'Se aplicável a um tipo de bônus específico',
    subtipo ENUM('bonus_especifico', 'individual', 'grupal', 'adiantamento') NOT NULL,
    recorrente TINYINT(1) DEFAULT 0 COMMENT 'Se é recorrente (ex: todo dia 1º)',
    dia_mes INT NULL COMMENT 'Dia do mês para recorrência (1-31)',
    valor_padrao DECIMAL(10,2) NULL COMMENT 'Valor padrão (se aplicável)',
    empresa_id INT NULL COMMENT 'Se específico para uma empresa',
    ativo TINYINT(1) DEFAULT 1,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_bonus_id) REFERENCES tipos_bonus(id) ON DELETE SET NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_subtipo (subtipo),
    INDEX idx_recorrente (recorrente),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Criar tabela de adiantamentos
CREATE TABLE IF NOT EXISTS fechamentos_pagamento_adiantamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fechamento_pagamento_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    valor_adiantamento DECIMAL(10,2) NOT NULL,
    valor_descontar DECIMAL(10,2) NOT NULL COMMENT 'Valor a descontar no próximo fechamento regular',
    mes_desconto VARCHAR(7) NULL COMMENT 'Mês de referência onde será descontado (YYYY-MM)',
    descontado TINYINT(1) DEFAULT 0 COMMENT 'Se já foi descontado',
    fechamento_desconto_id INT NULL COMMENT 'ID do fechamento onde foi descontado',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fechamento_pagamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (fechamento_desconto_id) REFERENCES fechamentos_pagamento(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_descontado (descontado),
    INDEX idx_mes_desconto (mes_desconto),
    INDEX idx_fechamento (fechamento_pagamento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Atualizar registros existentes para tipo_fechamento = 'regular'
UPDATE fechamentos_pagamento SET tipo_fechamento = 'regular' WHERE tipo_fechamento IS NULL;

-- 6. Atualizar itens existentes para incluir todos os campos padrão
UPDATE fechamentos_pagamento_itens 
SET inclui_salario = 1, 
    inclui_horas_extras = 1, 
    inclui_bonus_automaticos = 1 
WHERE inclui_salario IS NULL;

-- =====================================================
-- FIM DA MIGRAÇÃO
-- =====================================================

