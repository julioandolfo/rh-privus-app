-- Migração: Atualizar entrevistas antigas para aparecerem no kanban
-- Define coluna_kanban padrão para entrevistas que não têm

-- Atualiza entrevistas manuais (sem candidatura) que não têm coluna_kanban
-- Coloca na coluna 'entrevistas' por padrão
UPDATE entrevistas 
SET coluna_kanban = 'entrevistas'
WHERE candidatura_id IS NULL 
  AND coluna_kanban IS NULL;

-- Para entrevistas com candidatura, busca a coluna da candidatura
-- Se a candidatura tiver coluna_kanban, usa ela
UPDATE entrevistas e
INNER JOIN candidaturas c ON e.candidatura_id = c.id
SET e.coluna_kanban = COALESCE(c.coluna_kanban, 'entrevistas')
WHERE e.candidatura_id IS NOT NULL
  AND e.coluna_kanban IS NULL
  AND c.coluna_kanban IS NOT NULL;

-- Para entrevistas com candidatura mas sem coluna na candidatura,
-- usa 'entrevistas' como padrão
UPDATE entrevistas e
INNER JOIN candidaturas c ON e.candidatura_id = c.id
SET e.coluna_kanban = 'entrevistas'
WHERE e.candidatura_id IS NOT NULL
  AND e.coluna_kanban IS NULL
  AND (c.coluna_kanban IS NULL OR c.coluna_kanban = '');

-- Exibe resumo das atualizações
SELECT 
    coluna_kanban,
    COUNT(*) as quantidade,
    SUM(CASE WHEN candidatura_id IS NULL THEN 1 ELSE 0 END) as entrevistas_manuais,
    SUM(CASE WHEN candidatura_id IS NOT NULL THEN 1 ELSE 0 END) as entrevistas_com_candidatura
FROM entrevistas
WHERE coluna_kanban IS NOT NULL
GROUP BY coluna_kanban
ORDER BY quantidade DESC;

-- Exibe total geral
SELECT 
    COUNT(*) as total_entrevistas_com_coluna,
    SUM(CASE WHEN candidatura_id IS NULL THEN 1 ELSE 0 END) as total_entrevistas_manuais,
    SUM(CASE WHEN candidatura_id IS NOT NULL THEN 1 ELSE 0 END) as total_entrevistas_com_candidatura
FROM entrevistas
WHERE coluna_kanban IS NOT NULL;

