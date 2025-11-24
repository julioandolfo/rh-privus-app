-- Migração: Sistema de Banco de Horas Completo
-- Execute este script no banco de dados

-- 1. Criar tabela de saldo atual
CREATE TABLE IF NOT EXISTS banco_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    saldo_horas DECIMAL(8,2) DEFAULT 0.00 COMMENT 'Saldo atual em horas (pode ser negativo)',
    saldo_minutos INT DEFAULT 0 COMMENT 'Saldo em minutos para precisão',
    ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_colaborador (colaborador_id),
    INDEX idx_saldo (saldo_horas),
    INDEX idx_ultima_atualizacao (ultima_atualizacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Criar tabela de movimentações (histórico completo)
CREATE TABLE IF NOT EXISTS banco_horas_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo ENUM('credito', 'debito') NOT NULL COMMENT 'Crédito = adiciona, Débito = remove',
    origem ENUM('hora_extra', 'ocorrencia', 'ajuste_manual', 'remocao_manual') NOT NULL,
    origem_id INT NULL COMMENT 'ID da origem (horas_extras.id, ocorrencias.id, etc)',
    quantidade_horas DECIMAL(8,2) NOT NULL COMMENT 'Quantidade de horas (positiva sempre)',
    saldo_anterior DECIMAL(8,2) NOT NULL COMMENT 'Saldo antes da movimentação',
    saldo_posterior DECIMAL(8,2) NOT NULL COMMENT 'Saldo após a movimentação',
    motivo TEXT NOT NULL COMMENT 'Motivo da movimentação',
    observacoes TEXT,
    usuario_id INT NULL COMMENT 'Usuário que realizou a movimentação',
    data_movimentacao DATE NOT NULL COMMENT 'Data da movimentação',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_origem (origem, origem_id),
    INDEX idx_data_movimentacao (data_movimentacao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Modificar tabela horas_extras
ALTER TABLE horas_extras
ADD COLUMN tipo_pagamento ENUM('dinheiro', 'banco_horas') DEFAULT 'dinheiro' 
    COMMENT 'Tipo de pagamento: dinheiro ou banco de horas',
ADD COLUMN banco_horas_movimentacao_id INT NULL 
    COMMENT 'ID da movimentação no banco de horas (se aplicável)',
ADD INDEX idx_tipo_pagamento (tipo_pagamento),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;

-- 4. Modificar tabela tipos_ocorrencias para adicionar opção de desconto banco de horas
ALTER TABLE tipos_ocorrencias
ADD COLUMN permite_desconto_banco_horas BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, permite descontar do banco de horas ao invés de dinheiro';

-- 5. Modificar tabela ocorrencias
ALTER TABLE ocorrencias
ADD COLUMN desconta_banco_horas BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, desconta do banco de horas ao invés de dinheiro',
ADD COLUMN horas_descontadas DECIMAL(5,2) NULL 
    COMMENT 'Quantidade de horas descontadas do banco',
ADD COLUMN banco_horas_movimentacao_id INT NULL 
    COMMENT 'ID da movimentação no banco de horas (se aplicável)',
ADD INDEX idx_desconta_banco_horas (desconta_banco_horas),
ADD INDEX idx_banco_horas_mov (banco_horas_movimentacao_id),
ADD FOREIGN KEY (banco_horas_movimentacao_id) 
    REFERENCES banco_horas_movimentacoes(id) ON DELETE SET NULL;

-- 6. Atualizar tipos de ocorrências existentes para permitir desconto banco de horas
UPDATE tipos_ocorrencias 
SET permite_desconto_banco_horas = TRUE 
WHERE codigo IN ('falta', 'ausencia_injustificada', 'atraso_entrada', 'atraso_almoco', 'atraso_cafe', 'saida_antecipada');

-- 7. Inicializar saldos para colaboradores existentes (opcional - cria registro com saldo zero)
INSERT INTO banco_horas (colaborador_id, saldo_horas, saldo_minutos)
SELECT id, 0.00, 0
FROM colaboradores
WHERE id NOT IN (SELECT colaborador_id FROM banco_horas WHERE colaborador_id IS NOT NULL);

