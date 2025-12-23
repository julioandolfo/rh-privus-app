-- Adiciona coluna CONTRATADO no kanban de seleção
INSERT INTO kanban_colunas (nome, codigo, cor, icone, ordem, ativo) 
SELECT 'Contratado', 'contratado', '#50cd89', 'check-circle', 6, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM kanban_colunas WHERE codigo = 'contratado'
);

-- Atualiza ordem das outras colunas
UPDATE kanban_colunas SET ordem = 7 WHERE codigo = 'reprovados' AND ordem = 6;

