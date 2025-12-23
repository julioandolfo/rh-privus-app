-- Query rápida: Corrige entrevistas com coluna_kanban = 'entrevista' para usar código da coluna id = 1

-- Busca o código da coluna com id = 1
SET @codigo_coluna_1 = (SELECT codigo FROM kanban_colunas WHERE id = 1 LIMIT 1);

-- Se não encontrar, usa 'novos_candidatos' como padrão
SET @codigo_coluna_1 = COALESCE(@codigo_coluna_1, 'novos_candidatos');

-- Atualiza todas as entrevistas que têm coluna_kanban = 'entrevista' (sem 's')
-- para usar o código correto da coluna id = 1
UPDATE entrevistas 
SET coluna_kanban = @codigo_coluna_1
WHERE coluna_kanban = 'entrevista';

-- Mostra quantas foram atualizadas
SELECT 
    CONCAT('Foram atualizadas ', ROW_COUNT(), ' entrevistas para a coluna: ', @codigo_coluna_1) as resultado;

-- Mostra a coluna usada
SELECT 
    id,
    nome,
    codigo,
    'Coluna onde as entrevistas foram colocadas' as observacao
FROM kanban_colunas 
WHERE id = 1;

