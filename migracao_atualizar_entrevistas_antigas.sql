-- Migração: Atualizar entrevistas antigas para aparecerem no kanban
-- Define coluna_kanban padrão para entrevistas que não têm

-- Primeiro, busca o código da coluna com id = 1
SET @codigo_coluna_1 = (SELECT codigo FROM kanban_colunas WHERE id = 1 LIMIT 1);

-- Se não encontrar id = 1, usa 'novos_candidatos' como padrão (primeira coluna)
SET @codigo_coluna_1 = COALESCE(@codigo_coluna_1, 'novos_candidatos');

-- Atualiza entrevistas que têm coluna_kanban = 'entrevista' (sem 's') ou NULL
-- para usar o código da coluna id = 1
UPDATE entrevistas 
SET coluna_kanban = @codigo_coluna_1
WHERE (coluna_kanban = 'entrevista' OR coluna_kanban IS NULL)
  AND candidatura_id IS NULL;

-- Atualiza entrevistas manuais (sem candidatura) que não têm coluna_kanban
-- Coloca na coluna id = 1
UPDATE entrevistas 
SET coluna_kanban = @codigo_coluna_1
WHERE candidatura_id IS NULL 
  AND coluna_kanban IS NULL;

-- Para entrevistas com candidatura que têm coluna_kanban = 'entrevista' (sem 's')
-- Atualiza para usar o código da coluna id = 1
UPDATE entrevistas e
INNER JOIN candidaturas c ON e.candidatura_id = c.id
SET e.coluna_kanban = @codigo_coluna_1
WHERE e.candidatura_id IS NOT NULL
  AND e.coluna_kanban = 'entrevista';

-- Para entrevistas com candidatura, busca a coluna da candidatura
-- Se a candidatura tiver coluna_kanban, usa ela
UPDATE entrevistas e
INNER JOIN candidaturas c ON e.candidatura_id = c.id
SET e.coluna_kanban = COALESCE(c.coluna_kanban, @codigo_coluna_1)
WHERE e.candidatura_id IS NOT NULL
  AND e.coluna_kanban IS NULL
  AND c.coluna_kanban IS NOT NULL;

-- Para entrevistas com candidatura mas sem coluna na candidatura,
-- usa o código da coluna id = 1 como padrão
UPDATE entrevistas e
INNER JOIN candidaturas c ON e.candidatura_id = c.id
SET e.coluna_kanban = @codigo_coluna_1
WHERE e.candidatura_id IS NOT NULL
  AND e.coluna_kanban IS NULL
  AND (c.coluna_kanban IS NULL OR c.coluna_kanban = '');

-- Exibe qual código foi usado (coluna id = 1)
SELECT 
    id,
    nome,
    codigo as codigo_usado,
    'Esta é a coluna onde as entrevistas foram colocadas' as observacao
FROM kanban_colunas 
WHERE id = 1;

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

