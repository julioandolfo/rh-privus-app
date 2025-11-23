-- ============================================
-- SCRIPT SIMPLIFICADO PARA LIMPAR ETAPAS DUPLICADAS
-- ============================================
-- Versão mais simples e direta para limpar duplicatas
-- Mantém sempre a etapa com menor ID

START TRANSACTION;

-- ============================================
-- 1. Ver duplicatas antes de limpar
-- ============================================
SELECT 
    nome,
    codigo,
    vaga_id,
    COUNT(*) as total,
    GROUP_CONCAT(id ORDER BY id) as ids,
    MIN(id) as id_manter
FROM processo_seletivo_etapas
WHERE vaga_id IS NULL  -- Apenas etapas padrão
GROUP BY nome, codigo, vaga_id
HAVING COUNT(*) > 1;

-- ============================================
-- 2. Atualizar candidaturas_etapas
-- ============================================
UPDATE candidaturas_etapas ce
INNER JOIN processo_seletivo_etapas e1 ON ce.etapa_id = e1.id
INNER JOIN processo_seletivo_etapas e2 
    ON e1.nome = e2.nome 
    AND e1.codigo = e2.codigo 
    AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
    AND e2.id < e1.id  -- Manter o menor ID
SET ce.etapa_id = e2.id
WHERE ce.etapa_id = e1.id
AND NOT EXISTS (
    SELECT 1 
    FROM candidaturas_etapas ce2 
    WHERE ce2.candidatura_id = ce.candidatura_id 
    AND ce2.etapa_id = e2.id
);

-- Remover duplicatas de candidaturas_etapas após atualização
DELETE ce1 FROM candidaturas_etapas ce1
INNER JOIN candidaturas_etapas ce2 
    ON ce1.candidatura_id = ce2.candidatura_id 
    AND ce1.etapa_id != ce2.etapa_id
INNER JOIN processo_seletivo_etapas e1 ON ce1.etapa_id = e1.id
INNER JOIN processo_seletivo_etapas e2 ON ce2.etapa_id = e2.id
WHERE e1.nome = e2.nome 
AND e1.codigo = e2.codigo
AND e1.id > e2.id;

-- ============================================
-- 3. Atualizar entrevistas
-- ============================================
UPDATE entrevistas e
INNER JOIN processo_seletivo_etapas e1 ON e.etapa_id = e1.id
INNER JOIN processo_seletivo_etapas e2 
    ON e1.nome = e2.nome 
    AND e1.codigo = e2.codigo 
    AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
    AND e2.id < e1.id
SET e.etapa_id = e2.id
WHERE e.etapa_id = e1.id;

-- ============================================
-- 4. Atualizar formularios_cultura
-- ============================================
UPDATE formularios_cultura fc
INNER JOIN processo_seletivo_etapas e1 ON fc.etapa_id = e1.id
INNER JOIN processo_seletivo_etapas e2 
    ON e1.nome = e2.nome 
    AND e1.codigo = e2.codigo 
    AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
    AND e2.id < e1.id
SET fc.etapa_id = e2.id
WHERE fc.etapa_id = e1.id;

-- ============================================
-- 5. Excluir etapas duplicadas
-- ============================================
DELETE e1 FROM processo_seletivo_etapas e1
INNER JOIN processo_seletivo_etapas e2 
    ON e1.nome = e2.nome 
    AND e1.codigo = e2.codigo 
    AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
WHERE e1.id > e2.id  -- Mantém o menor ID
AND e1.vaga_id IS NULL;  -- Apenas etapas padrão

-- ============================================
-- 6. Verificar resultado
-- ============================================
SELECT 
    nome,
    codigo,
    vaga_id,
    COUNT(*) as total
FROM processo_seletivo_etapas
WHERE vaga_id IS NULL
GROUP BY nome, codigo, vaga_id
HAVING COUNT(*) > 1;

-- Se não retornar nenhuma linha, não há mais duplicatas!

-- ============================================
-- IMPORTANTE: Descomente uma das linhas abaixo
-- ============================================
-- COMMIT;   -- Confirma as alterações
-- ROLLBACK; -- Reverte as alterações

