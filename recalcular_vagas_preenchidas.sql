-- ============================================
-- RECÁLCULO: Quantidade de Vagas Preenchidas
-- Data: 2026-02-10
-- ============================================

-- Recalcula quantidade_preenchida baseado nos candidatos aprovados
UPDATE vagas v
SET quantidade_preenchida = (
    SELECT COUNT(*) 
    FROM candidaturas c 
    WHERE c.vaga_id = v.id 
    AND (c.coluna_kanban = 'aprovados' OR c.status = 'aprovada' OR c.coluna_kanban = 'contratado')
)
WHERE v.id IN (SELECT DISTINCT vaga_id FROM candidaturas);

-- Verifica os resultados
SELECT 
    v.id,
    v.titulo,
    v.quantidade_vagas,
    v.quantidade_preenchida,
    (SELECT COUNT(*) FROM candidaturas c WHERE c.vaga_id = v.id AND (c.coluna_kanban = 'aprovados' OR c.status = 'aprovada' OR c.coluna_kanban = 'contratado')) as total_aprovados
FROM vagas v
WHERE v.quantidade_preenchida > 0
ORDER BY v.id DESC
LIMIT 20;
