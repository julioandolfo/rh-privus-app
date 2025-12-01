-- =====================================================
-- Script para aprovar todas as solicitações pendentes
-- =====================================================

-- Esta query cria registros em horas_extras para todas as solicitações pendentes
-- e marca as solicitações como aprovadas

-- Primeiro, vamos inserir as horas extras aprovadas
INSERT INTO horas_extras (
    colaborador_id, 
    data_trabalho, 
    quantidade_horas,
    valor_hora, 
    percentual_adicional, 
    valor_total,
    observacoes, 
    usuario_id, 
    tipo_pagamento,
    created_at
)
SELECT 
    s.colaborador_id,
    s.data_trabalho,
    s.quantidade_horas,
    COALESCE(c.salario / 220, 0) as valor_hora,
    COALESCE(e.percentual_hora_extra, 50.00) as percentual_adicional,
    COALESCE(c.salario / 220, 0) * s.quantidade_horas * (1 + (COALESCE(e.percentual_hora_extra, 50.00) / 100)) as valor_total,
    CONCAT('Solicitado pelo colaborador: ', s.motivo, ' | Aprovado em lote automaticamente') as observacoes,
    1 as usuario_id, -- Usuário admin
    'dinheiro' as tipo_pagamento,
    NOW() as created_at
FROM solicitacoes_horas_extras s
INNER JOIN colaboradores c ON s.colaborador_id = c.id
LEFT JOIN empresas e ON c.empresa_id = e.id
WHERE s.status = 'pendente';

-- Depois, marca todas como aprovadas
UPDATE solicitacoes_horas_extras 
SET 
    status = 'aprovada',
    observacoes_rh = 'Aprovado automaticamente em lote',
    usuario_aprovacao_id = 1,
    data_aprovacao = NOW()
WHERE status = 'pendente';

-- Exibe total de solicitações aprovadas
SELECT 
    COUNT(*) as total_aprovadas,
    'Solicitações aprovadas com sucesso!' as mensagem
FROM solicitacoes_horas_extras 
WHERE status = 'aprovada' 
AND data_aprovacao = CURDATE();

