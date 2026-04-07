-- Migração: Sistema de Solicitação de Pagamento PJ
-- Colaboradores PJ enviam mensalmente: planilha de horas + NFe + Boleto

-- 1. Adiciona valor_hora ao cadastro do colaborador (usado para calcular total da solicitação)
ALTER TABLE colaboradores
ADD COLUMN valor_hora DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Valor da hora trabalhada (PJ)' AFTER salario;

-- 2. Tabela principal de solicitações
CREATE TABLE IF NOT EXISTS solicitacoes_pagamento_pj (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    mes_referencia VARCHAR(7) NOT NULL COMMENT 'Formato YYYY-MM',
    valor_hora_aplicado DECIMAL(10,2) NOT NULL COMMENT 'Valor da hora no momento do envio',
    total_horas DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de horas calculado da planilha',
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'total_horas * valor_hora_aplicado',
    planilha_anexo VARCHAR(500) NULL,
    planilha_nome_original VARCHAR(255) NULL,
    nfe_anexo VARCHAR(500) NULL,
    nfe_nome_original VARCHAR(255) NULL,
    nfe_numero VARCHAR(100) NULL,
    nfe_valor DECIMAL(10,2) NULL,
    boleto_anexo VARCHAR(500) NULL,
    boleto_nome_original VARCHAR(255) NULL,
    status ENUM('enviada','em_analise','aprovada','rejeitada','paga') DEFAULT 'enviada',
    observacoes_colaborador TEXT NULL,
    observacoes_admin TEXT NULL,
    motivo_rejeicao TEXT NULL,
    validacao_planilha JSON NULL COMMENT 'Resultado completo da validação da planilha',
    fechamento_pagamento_id INT NULL COMMENT 'Vinculado quando aprovado e gerado fechamento',
    aprovado_por INT NULL,
    data_aprovacao DATETIME NULL,
    data_pagamento DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (fechamento_pagamento_id) REFERENCES fechamentos_pagamento(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_mes_referencia (mes_referencia),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Linhas da planilha parseadas (histórico granular para auditoria)
CREATE TABLE IF NOT EXISTS solicitacoes_pagamento_pj_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    data_trabalho DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    pausa_minutos INT DEFAULT 0,
    horas_trabalhadas DECIMAL(5,2) NOT NULL,
    projeto VARCHAR(255) NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_pagamento_pj(id) ON DELETE CASCADE,
    INDEX idx_solicitacao (solicitacao_id),
    INDEX idx_data (data_trabalho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Log de auditoria (toda ação fica registrada)
CREATE TABLE IF NOT EXISTS solicitacoes_pagamento_pj_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id INT NOT NULL,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    acao VARCHAR(100) NOT NULL COMMENT 'criada, editada, enviada, aprovada, rejeitada, paga, anexo_adicionado, etc',
    detalhes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_pagamento_pj(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    INDEX idx_solicitacao (solicitacao_id),
    INDEX idx_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
