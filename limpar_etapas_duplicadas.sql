-- ============================================
-- SCRIPT PARA LIMPAR ETAPAS DUPLICADAS
-- ============================================
-- Este script identifica e remove etapas duplicadas do processo seletivo,
-- mantendo sempre a etapa mais antiga (menor ID) e atualizando todas as referências.

-- IMPORTANTE: Execute este script em uma transação e faça backup antes!

START TRANSACTION;

-- ============================================
-- PASSO 1: Identificar duplicatas
-- ============================================
-- Esta query mostra as etapas duplicadas que serão removidas
-- Execute primeiro para verificar o que será afetado

SELECT 
    nome,
    codigo,
    vaga_id,
    COUNT(*) as total_duplicatas,
    GROUP_CONCAT(id ORDER BY id) as ids,
    MIN(id) as id_manter
FROM processo_seletivo_etapas
WHERE vaga_id IS NULL  -- Apenas etapas padrão (ou remova esta linha para todas)
GROUP BY nome, codigo, vaga_id
HAVING COUNT(*) > 1;

-- ============================================
-- PASSO 2: Criar tabela temporária com mapeamento de IDs
-- ============================================
-- Esta tabela armazena qual ID manter e quais IDs serão substituídos

CREATE TEMPORARY TABLE IF NOT EXISTS etapas_mapeamento AS
SELECT 
    nome,
    codigo,
    vaga_id,
    MIN(id) as id_manter,
    GROUP_CONCAT(id ORDER BY id) as ids_todos
FROM processo_seletivo_etapas
WHERE vaga_id IS NULL  -- Apenas etapas padrão (ou remova esta linha para todas)
GROUP BY nome, codigo, vaga_id
HAVING COUNT(*) > 1;

-- Criar tabela com todos os IDs que serão substituídos
CREATE TEMPORARY TABLE IF NOT EXISTS etapas_substituir AS
SELECT 
    e.id as id_antigo,
    m.id_manter as id_novo
FROM processo_seletivo_etapas e
INNER JOIN etapas_mapeamento m 
    ON e.nome = m.nome 
    AND e.codigo = m.codigo 
    AND (e.vaga_id = m.vaga_id OR (e.vaga_id IS NULL AND m.vaga_id IS NULL))
WHERE e.id != m.id_manter;

-- ============================================
-- PASSO 3: Atualizar referências em candidaturas_etapas
-- ============================================
-- Esta tabela não tem CASCADE, então precisa atualizar manualmente

UPDATE candidaturas_etapas ce
INNER JOIN etapas_substituir es ON ce.etapa_id = es.id_antigo
SET ce.etapa_id = es.id_novo
WHERE NOT EXISTS (
    SELECT 1 
    FROM candidaturas_etapas ce2 
    WHERE ce2.candidatura_id = ce.candidatura_id 
    AND ce2.etapa_id = es.id_novo
);

-- Se houver conflitos (mesma candidatura com ambas etapas), manter a mais antiga
DELETE ce1 FROM candidaturas_etapas ce1
INNER JOIN candidaturas_etapas ce2 
    ON ce1.candidatura_id = ce2.candidatura_id 
    AND ce1.etapa_id != ce2.etapa_id
INNER JOIN etapas_substituir es1 ON ce1.etapa_id = es1.id_antigo
INNER JOIN etapas_substituir es2 ON ce2.etapa_id = es2.id_novo
WHERE ce1.id > ce2.id;

-- Atualizar as referências restantes
UPDATE candidaturas_etapas ce
INNER JOIN etapas_substituir es ON ce.etapa_id = es.id_antigo
SET ce.etapa_id = es.id_novo;

-- ============================================
-- PASSO 4: Atualizar referências em entrevistas
-- ============================================
-- Esta tabela tem ON DELETE SET NULL, mas vamos atualizar para manter consistência

UPDATE entrevistas e
INNER JOIN etapas_substituir es ON e.etapa_id = es.id_antigo
SET e.etapa_id = es.id_novo;

-- ============================================
-- PASSO 5: Atualizar referências em formularios_cultura
-- ============================================
-- Esta tabela tem ON DELETE SET NULL, mas vamos atualizar para manter consistência

UPDATE formularios_cultura fc
INNER JOIN etapas_substituir es ON fc.etapa_id = es.id_antigo
SET fc.etapa_id = es.id_novo;

-- ============================================
-- PASSO 6: Excluir etapas duplicadas
-- ============================================
-- As outras tabelas (vagas_etapas, kanban_automatizacoes) têm CASCADE,
-- então serão atualizadas automaticamente

DELETE FROM processo_seletivo_etapas
WHERE id IN (SELECT id_antigo FROM etapas_substituir);

-- ============================================
-- PASSO 7: Verificar resultado
-- ============================================
-- Verifique se ainda há duplicatas

SELECT 
    nome,
    codigo,
    vaga_id,
    COUNT(*) as total
FROM processo_seletivo_etapas
WHERE vaga_id IS NULL
GROUP BY nome, codigo, vaga_id
HAVING COUNT(*) > 1;

-- ============================================
-- FINALIZAÇÃO
-- ============================================
-- Se tudo estiver correto, execute:
-- COMMIT;
-- 
-- Se houver problemas, execute:
-- ROLLBACK;

-- Descomente a linha abaixo para confirmar as alterações:
-- COMMIT;

-- Ou descomente para reverter:
-- ROLLBACK;

