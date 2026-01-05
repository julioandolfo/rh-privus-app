-- ========================================
-- Script SQL: Correção Manual do Banco de Horas
-- ========================================
-- Use este script quando precisar corrigir manualmente o histórico
-- de movimentações e o saldo do banco de horas de um colaborador
--
-- IMPORTANTE: Execute as seções na ordem apresentada!
-- ========================================

-- ========================================
-- SEÇÃO 1: CONSULTAR SITUAÇÃO ATUAL
-- ========================================
-- Execute estas queries primeiro para entender a situação

-- 1.1 Ver saldo atual do colaborador (SUBSTITUA 123 pelo ID do colaborador)
SELECT 
    c.id,
    c.nome_completo,
    bh.saldo_horas,
    bh.saldo_minutos,
    (bh.saldo_horas + (bh.saldo_minutos / 60)) as saldo_total_horas,
    bh.ultima_atualizacao
FROM colaboradores c
LEFT JOIN banco_horas bh ON c.id = bh.colaborador_id
WHERE c.id = 123;  -- <-- ALTERE AQUI

-- 1.2 Ver todas as movimentações do colaborador
SELECT 
    m.id,
    m.data_movimentacao,
    m.tipo,
    m.origem,
    m.quantidade_horas,
    m.saldo_anterior,
    m.saldo_posterior,
    m.motivo,
    m.observacoes,
    u.nome as usuario_nome,
    m.created_at
FROM banco_horas_movimentacoes m
LEFT JOIN usuarios u ON m.usuario_id = u.id
WHERE m.colaborador_id = 123  -- <-- ALTERE AQUI
ORDER BY m.data_movimentacao ASC, m.created_at ASC, m.id ASC;

-- 1.3 Ver horas extras vinculadas ao banco de horas
SELECT 
    he.id,
    he.data_trabalho,
    he.quantidade_horas,
    he.tipo_pagamento,
    he.banco_horas_movimentacao_id,
    he.observacoes
FROM horas_extras he
WHERE he.colaborador_id = 123  -- <-- ALTERE AQUI
  AND he.tipo_pagamento = 'banco_horas'
ORDER BY he.data_trabalho DESC;

-- ========================================
-- SEÇÃO 2: DELETAR MOVIMENTAÇÕES INCORRETAS
-- ========================================
-- Use com CUIDADO! Isso deleta permanentemente as movimentações

-- 2.1 Deletar uma movimentação específica (SUBSTITUA 456 pelo ID da movimentação)
-- ATENÇÃO: Primeiro remova as referências!
UPDATE horas_extras 
SET banco_horas_movimentacao_id = NULL 
WHERE banco_horas_movimentacao_id = 456;  -- <-- ALTERE AQUI

UPDATE ocorrencias 
SET banco_horas_movimentacao_id = NULL 
WHERE banco_horas_movimentacao_id = 456;  -- <-- ALTERE AQUI

-- Agora pode deletar
DELETE FROM banco_horas_movimentacoes WHERE id = 456;  -- <-- ALTERE AQUI

-- 2.2 Deletar múltiplas movimentações (CUIDADO!)
-- Exemplo: deletar todas as movimentações de um período específico
-- DESCOMENTE E AJUSTE SE NECESSÁRIO:
/*
DELETE FROM banco_horas_movimentacoes 
WHERE colaborador_id = 123  -- <-- ALTERE AQUI
  AND data_movimentacao BETWEEN '2025-01-01' AND '2025-01-31';
*/

-- ========================================
-- SEÇÃO 3: RECALCULAR SALDO AUTOMATICAMENTE
-- ========================================
-- Este script recalcula o saldo baseado em todas as movimentações restantes

-- 3.1 Backup do saldo atual (por segurança)
CREATE TEMPORARY TABLE IF NOT EXISTS backup_saldo_banco_horas AS
SELECT * FROM banco_horas WHERE colaborador_id = 123;  -- <-- ALTERE AQUI

-- 3.2 Recalcular saldo (SUBSTITUA 123 pelo ID do colaborador)
SET @colaborador_id = 123;  -- <-- ALTERE AQUI
SET @saldo_atual = 0;

-- Cria tabela temporária para armazenar movimentações corrigidas
DROP TEMPORARY TABLE IF EXISTS temp_movimentacoes_corrigidas;
CREATE TEMPORARY TABLE temp_movimentacoes_corrigidas (
    id INT,
    saldo_anterior DECIMAL(8,2),
    saldo_posterior DECIMAL(8,2)
);

-- Processa cada movimentação em ordem cronológica
SET @saldo_atual = 0;

INSERT INTO temp_movimentacoes_corrigidas (id, saldo_anterior, saldo_posterior)
SELECT 
    m.id,
    @saldo_anterior := @saldo_atual as saldo_anterior,
    @saldo_atual := @saldo_atual + 
        CASE 
            WHEN m.tipo = 'credito' THEN m.quantidade_horas
            WHEN m.tipo = 'debito' THEN -m.quantidade_horas
        END as saldo_posterior
FROM banco_horas_movimentacoes m
WHERE m.colaborador_id = @colaborador_id
ORDER BY m.data_movimentacao ASC, m.created_at ASC, m.id ASC;

-- Atualiza as movimentações com os saldos corretos
UPDATE banco_horas_movimentacoes m
INNER JOIN temp_movimentacoes_corrigidas t ON m.id = t.id
SET 
    m.saldo_anterior = t.saldo_anterior,
    m.saldo_posterior = t.saldo_posterior;

-- Atualiza o saldo final
SET @saldo_final = @saldo_atual;
SET @horas_inteiras = FLOOR(ABS(@saldo_final));
SET @minutos = (ABS(@saldo_final) - @horas_inteiras) * 60;
SET @horas_inteiras = IF(@saldo_final < 0, -@horas_inteiras, @horas_inteiras);

-- Atualiza ou insere o saldo
INSERT INTO banco_horas (colaborador_id, saldo_horas, saldo_minutos, ultima_atualizacao)
VALUES (@colaborador_id, @horas_inteiras, @minutos, NOW())
ON DUPLICATE KEY UPDATE 
    saldo_horas = @horas_inteiras,
    saldo_minutos = @minutos,
    ultima_atualizacao = NOW();

-- 3.3 Verificar resultado
SELECT 
    'Saldo recalculado com sucesso!' as status,
    @saldo_final as saldo_final_horas,
    @horas_inteiras as saldo_horas,
    @minutos as saldo_minutos;

-- Limpa tabela temporária
DROP TEMPORARY TABLE IF EXISTS temp_movimentacoes_corrigidas;

-- ========================================
-- SEÇÃO 4: CORREÇÕES ESPECÍFICAS
-- ========================================

-- 4.1 Zerar completamente o banco de horas de um colaborador
-- ATENÇÃO: Isso deleta TUDO!
/*
DELETE FROM banco_horas_movimentacoes WHERE colaborador_id = 123;  -- <-- ALTERE AQUI
DELETE FROM banco_horas WHERE colaborador_id = 123;  -- <-- ALTERE AQUI
UPDATE horas_extras SET banco_horas_movimentacao_id = NULL WHERE colaborador_id = 123;  -- <-- ALTERE AQUI
UPDATE ocorrencias SET banco_horas_movimentacao_id = NULL, desconta_banco_horas = 0, horas_descontadas = NULL WHERE colaborador_id = 123;  -- <-- ALTERE AQUI
*/

-- 4.2 Ajustar manualmente o saldo (último recurso!)
-- Use apenas se o recálculo automático não funcionar
/*
UPDATE banco_horas 
SET saldo_horas = 10,  -- <-- ALTERE AQUI (pode ser negativo)
    saldo_minutos = 30,  -- <-- ALTERE AQUI
    ultima_atualizacao = NOW()
WHERE colaborador_id = 123;  -- <-- ALTERE AQUI
*/

-- 4.3 Criar movimentação de ajuste manual
-- Use para adicionar uma movimentação de correção
/*
INSERT INTO banco_horas_movimentacoes (
    colaborador_id, tipo, origem, origem_id,
    quantidade_horas, saldo_anterior, saldo_posterior,
    motivo, observacoes, usuario_id, data_movimentacao
) VALUES (
    123,  -- <-- ALTERE: ID do colaborador
    'credito',  -- <-- ALTERE: 'credito' ou 'debito'
    'ajuste_manual',
    NULL,
    5.00,  -- <-- ALTERE: quantidade de horas
    0.00,  -- <-- ALTERE: saldo anterior (consulte antes)
    5.00,  -- <-- ALTERE: saldo posterior (anterior + quantidade)
    'Ajuste manual - correção de erro no sistema',
    'Movimentação criada manualmente para corrigir inconsistência',
    1,  -- <-- ALTERE: ID do usuário que está fazendo a correção
    CURDATE()
);
*/

-- ========================================
-- SEÇÃO 5: VERIFICAÇÕES FINAIS
-- ========================================

-- 5.1 Verificar consistência do saldo
SELECT 
    c.nome_completo,
    bh.saldo_horas,
    bh.saldo_minutos,
    (bh.saldo_horas + (bh.saldo_minutos / 60)) as saldo_calculado,
    (
        SELECT COALESCE(SUM(
            CASE 
                WHEN m.tipo = 'credito' THEN m.quantidade_horas
                WHEN m.tipo = 'debito' THEN -m.quantidade_horas
            END
        ), 0)
        FROM banco_horas_movimentacoes m
        WHERE m.colaborador_id = c.id
    ) as saldo_real_movimentacoes,
    ABS((bh.saldo_horas + (bh.saldo_minutos / 60)) - (
        SELECT COALESCE(SUM(
            CASE 
                WHEN m.tipo = 'credito' THEN m.quantidade_horas
                WHEN m.tipo = 'debito' THEN -m.quantidade_horas
            END
        ), 0)
        FROM banco_horas_movimentacoes m
        WHERE m.colaborador_id = c.id
    )) as diferenca
FROM colaboradores c
LEFT JOIN banco_horas bh ON c.id = bh.colaborador_id
WHERE c.id = 123  -- <-- ALTERE AQUI
HAVING diferenca > 0.01;  -- Mostra apenas se houver diferença

-- 5.2 Ver histórico atualizado
SELECT 
    m.id,
    m.data_movimentacao,
    m.tipo,
    m.quantidade_horas,
    m.saldo_anterior,
    m.saldo_posterior,
    m.motivo
FROM banco_horas_movimentacoes m
WHERE m.colaborador_id = 123  -- <-- ALTERE AQUI
ORDER BY m.data_movimentacao ASC, m.created_at ASC, m.id ASC;

-- ========================================
-- FIM DO SCRIPT
-- ========================================
