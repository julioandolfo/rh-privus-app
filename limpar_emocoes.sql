-- Query para limpar todos os registros de emoções
-- ATENÇÃO: Esta operação é irreversível!

-- Opção 1: Limpar TODOS os registros de emoções
DELETE FROM emocoes;

-- Opção 2: Limpar apenas emoções de uma data específica (exemplo: 2024-01-15)
-- DELETE FROM emocoes WHERE data_registro = '2024-01-15';

-- Opção 3: Limpar emoções de um período específico (exemplo: janeiro de 2024)
-- DELETE FROM emocoes WHERE data_registro >= '2024-01-01' AND data_registro < '2024-02-01';

-- Opção 4: Limpar emoções de um usuário específico (substitua USER_ID pelo ID do usuário)
-- DELETE FROM emocoes WHERE usuario_id = USER_ID;

-- Opção 5: Limpar emoções de um colaborador específico (substitua COLABORADOR_ID pelo ID do colaborador)
-- DELETE FROM emocoes WHERE colaborador_id = COLABORADOR_ID;

-- Opção 6: Limpar emoções antigas (anteriores a uma data específica, exemplo: antes de 2024-01-01)
-- DELETE FROM emocoes WHERE data_registro < '2024-01-01';

-- Após executar, você pode resetar o AUTO_INCREMENT se necessário:
-- ALTER TABLE emocoes AUTO_INCREMENT = 1;

