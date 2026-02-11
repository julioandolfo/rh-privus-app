-- ============================================
-- VERIFICAÇÃO: Caminho específico do currículo
-- Data: 2026-02-10
-- ============================================

-- Verifica o caminho atual do currículo da candidatura 8
SELECT 
    a.id as anexo_id,
    a.candidatura_id,
    a.nome_arquivo,
    a.caminho_arquivo,
    CASE 
        WHEN a.caminho_arquivo LIKE '/rh/%' THEN 'ERRADO: Tem /rh/ no início'
        WHEN a.caminho_arquivo LIKE '/%' THEN 'CORRETO: Começa com /'
        ELSE 'ERRADO: Não começa com /'
    END as status_caminho,
    CONCAT('https://privus.com.br/rh', a.caminho_arquivo) as url_gerada
FROM candidaturas_anexos a
WHERE a.candidatura_id = 8
ORDER BY a.id DESC;

-- Se precisar corrigir manualmente este específico:
-- UPDATE candidaturas_anexos 
-- SET caminho_arquivo = '/uploads/candidaturas/8/curriculo_1770783024.pdf'
-- WHERE candidatura_id = 8 AND nome_arquivo LIKE '%curriculo_1770783024%';
