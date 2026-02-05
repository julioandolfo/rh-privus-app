-- =============================================
-- Migração: Sistema de Saldo em R$ (Dinheiro/Créditos)
-- Adiciona saldo monetário para colaboradores e preço em R$ para produtos
-- =============================================

-- Adiciona campo de saldo em dinheiro na tabela pontos_total
ALTER TABLE pontos_total 
ADD COLUMN IF NOT EXISTS saldo_dinheiro DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Saldo em R$ (créditos monetários)';

-- Adiciona campo de preço em R$ na tabela loja_produtos
ALTER TABLE loja_produtos 
ADD COLUMN IF NOT EXISTS preco_dinheiro DECIMAL(10,2) NULL COMMENT 'Preço em R$ (NULL = não aceita pagamento em R$)';

-- Adiciona campo para indicar forma de pagamento no resgate
ALTER TABLE loja_resgates 
ADD COLUMN IF NOT EXISTS forma_pagamento ENUM('pontos', 'dinheiro') DEFAULT 'pontos' COMMENT 'Forma de pagamento utilizada';

ALTER TABLE loja_resgates 
ADD COLUMN IF NOT EXISTS valor_dinheiro DECIMAL(10,2) NULL COMMENT 'Valor em R$ cobrado (se forma_pagamento = dinheiro)';

-- Tabela de histórico de movimentações de saldo em R$
CREATE TABLE IF NOT EXISTS saldo_dinheiro_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    tipo ENUM('credito', 'debito') NOT NULL COMMENT 'Tipo da movimentação',
    valor DECIMAL(10,2) NOT NULL COMMENT 'Valor da movimentação',
    saldo_anterior DECIMAL(10,2) NOT NULL COMMENT 'Saldo antes da movimentação',
    saldo_posterior DECIMAL(10,2) NOT NULL COMMENT 'Saldo após a movimentação',
    descricao VARCHAR(255) NOT NULL COMMENT 'Descrição/motivo da movimentação',
    referencia_tipo VARCHAR(50) NULL COMMENT 'Tipo de referência (loja_resgate, ajuste_manual, etc)',
    referencia_id INT NULL COMMENT 'ID da referência',
    usuario_id INT NULL COMMENT 'Usuário que realizou a operação (admin)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atualiza view de estatísticas da loja
CREATE OR REPLACE VIEW vw_loja_estatisticas AS
SELECT 
    (SELECT COUNT(*) FROM loja_produtos WHERE ativo = 1) as total_produtos_ativos,
    (SELECT COUNT(*) FROM loja_categorias WHERE ativo = 1) as total_categorias_ativas,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'pendente') as resgates_pendentes,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'aprovado') as resgates_aprovados,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'preparando') as resgates_preparando,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'enviado') as resgates_enviados,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'entregue') as resgates_entregues,
    (SELECT COUNT(*) FROM loja_resgates WHERE DATE(created_at) = CURDATE()) as resgates_hoje,
    (SELECT COALESCE(SUM(pontos_total), 0) FROM loja_resgates WHERE status NOT IN ('cancelado', 'rejeitado') AND forma_pagamento = 'pontos') as pontos_gastos_total,
    (SELECT COALESCE(SUM(pontos_total), 0) FROM loja_resgates WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status NOT IN ('cancelado', 'rejeitado') AND forma_pagamento = 'pontos') as pontos_gastos_mes,
    (SELECT COALESCE(SUM(valor_dinheiro), 0) FROM loja_resgates WHERE status NOT IN ('cancelado', 'rejeitado') AND forma_pagamento = 'dinheiro') as dinheiro_gasto_total,
    (SELECT COALESCE(SUM(valor_dinheiro), 0) FROM loja_resgates WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status NOT IN ('cancelado', 'rejeitado') AND forma_pagamento = 'dinheiro') as dinheiro_gasto_mes;

-- Exibe resumo
SELECT 'Migração de Saldo em R$ concluída com sucesso!' as resultado;
DESCRIBE pontos_total;
DESCRIBE loja_produtos;
DESCRIBE loja_resgates;
