-- ============================================
-- CORREÇÃO: Caminhos dos currículos
-- Data: 2026-02-10
-- ============================================

-- Remove o /rh/ inicial dos caminhos que começam com /rh/uploads/
-- Exemplo: /rh/uploads/candidaturas/8/curriculo.pdf -> /uploads/candidaturas/8/curriculo.pdf
UPDATE candidaturas_anexos 
SET caminho_arquivo = REPLACE(caminho_arquivo, '/rh/uploads/', '/uploads/')
WHERE caminho_arquivo LIKE '/rh/uploads/%';

-- Garante que todos os caminhos comecem com / se ainda não começam
UPDATE candidaturas_anexos 
SET caminho_arquivo = CONCAT('/', caminho_arquivo)
WHERE caminho_arquivo LIKE 'uploads/%' 
  AND caminho_arquivo NOT LIKE '/%';

-- Verifica os resultados
SELECT id, nome_arquivo, caminho_arquivo 
FROM candidaturas_anexos 
WHERE caminho_arquivo LIKE '%uploads/candidaturas%'
ORDER BY id DESC
LIMIT 20;
