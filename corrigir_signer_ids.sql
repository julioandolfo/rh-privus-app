-- =============================================================
-- Script para limpar autentique_signer_id incorretos
-- Os IDs foram atribuídos por posição, mas a API do Autentique
-- retorna o dono da conta como primeiro signature, causando 
-- um desalinhamento (off-by-one).
-- 
-- Este script limpa os IDs errados. 
-- Depois, ao clicar em "Sincronizar com Autentique" em cada contrato,
-- os IDs corretos serão preenchidos automaticamente por match de email.
-- =============================================================

-- Remove os signer_ids incorretos para que o sync refaça o match por email
UPDATE contratos_signatarios 
SET autentique_signer_id = NULL 
WHERE contrato_id IN (
    SELECT id FROM contratos WHERE autentique_document_id IS NOT NULL
);

-- Verifica resultado
SELECT cs.contrato_id, cs.tipo, cs.nome, cs.email, cs.autentique_signer_id, cs.assinado
FROM contratos_signatarios cs
INNER JOIN contratos c ON c.id = cs.contrato_id
WHERE c.autentique_document_id IS NOT NULL
ORDER BY cs.contrato_id, cs.ordem_assinatura;
