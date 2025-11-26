-- ============================================
-- MIGRAÇÃO: Sistema de Ocorrência Apenas Informativa
-- Permite marcar ocorrências como apenas informativas (sem impacto financeiro ou em banco de horas)
-- ============================================

-- 1. Adicionar campo apenas_informativa na tabela ocorrencias
ALTER TABLE ocorrencias
ADD COLUMN apenas_informativa BOOLEAN DEFAULT 0 
    COMMENT 'Se TRUE, a ocorrência é apenas informativa e não gera desconto nem afeta banco de horas' 
    AFTER desconta_banco_horas,
ADD INDEX idx_apenas_informativa (apenas_informativa);

-- 2. Atualizar ocorrências existentes que não têm desconto configurado para NULL (opcional)
-- Isso garante que ocorrências antigas sem impacto sejam tratadas corretamente
-- UPDATE ocorrencias SET apenas_informativa = 0 WHERE apenas_informativa IS NULL;

