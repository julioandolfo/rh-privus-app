-- ============================================
-- Script ROBUSTO para Limpar Colaboradores de Teste
-- ============================================
-- Esta versão trata erros de tabelas inexistentes
-- Use esta versão se encontrar erros de "Table doesn't exist"
-- ============================================

-- Desabilitar verificação de foreign keys temporariamente
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. LIMPAR TABELAS RELACIONADAS A COLABORADORES
-- ============================================

-- Chat Interno (verifica se tabelas existem antes de deletar)
SET @sql = '';
SELECT GROUP_CONCAT(
    CONCAT('DELETE FROM ', table_name, ' WHERE ', 
        CASE 
            WHEN table_name = 'chat_mensagens' THEN 'enviado_por_colaborador_id IS NOT NULL'
            WHEN table_name = 'chat_historico_acoes' THEN 'realizado_por_colaborador_id IS NOT NULL'
            WHEN table_name = 'chat_resumos_ia' THEN 'conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL)'
            WHEN table_name = 'chat_sla_historico' THEN 'conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL)'
            WHEN table_name = 'chat_participantes' THEN 'conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL)'
            WHEN table_name = 'chat_conversas' THEN 'colaborador_id IS NOT NULL'
            WHEN table_name = 'chat_preferencias_usuario' THEN 'colaborador_id IS NOT NULL'
            ELSE '1=0'
        END
    ) SEPARATOR '; '
) INTO @sql
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name LIKE 'chat_%'
AND table_name IN ('chat_mensagens', 'chat_historico_acoes', 'chat_resumos_ia', 'chat_sla_historico', 'chat_participantes', 'chat_conversas', 'chat_preferencias_usuario');

-- Executa apenas se houver tabelas
SET @sql = IFNULL(@sql, 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ocorrências
DELETE FROM ocorrencias_comentarios WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_anexos WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_historico WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_advertencias WHERE colaborador_id IS NOT NULL;
DELETE FROM ocorrencias WHERE colaborador_id IS NOT NULL;

-- Engajamento - PDIs
DELETE FROM pdi_acoes WHERE pdi_id IN (SELECT id FROM pdis);
DELETE FROM pdi_objetivos WHERE pdi_id IN (SELECT id FROM pdis);
DELETE FROM pdis WHERE colaborador_id IS NOT NULL;

-- Engajamento - Outros
DELETE FROM reunioes_1on1 WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_satisfacao_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_satisfacao_envios WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_rapidas_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_rapidas_envios WHERE colaborador_id IS NOT NULL;
DELETE FROM emocoes WHERE colaborador_id IS NOT NULL;

-- Feed e Feedbacks
DELETE FROM feed_comentarios WHERE colaborador_id IS NOT NULL;
DELETE FROM feed_curtidas WHERE colaborador_id IS NOT NULL;
DELETE FROM feed WHERE colaborador_id IS NOT NULL;
DELETE FROM feedback_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM feedbacks WHERE remetente_colaborador_id IS NOT NULL OR destinatario_colaborador_id IS NOT NULL;

-- Pagamentos e Benefícios
DELETE FROM fechamentos_pagamento_bonus WHERE colaborador_id IS NOT NULL;
DELETE FROM fechamentos_pagamento_itens WHERE colaborador_id IS NOT NULL;
DELETE FROM horas_extras WHERE colaborador_id IS NOT NULL;
DELETE FROM promocoes WHERE colaborador_id IS NOT NULL;
DELETE FROM documentos_pagamento WHERE colaborador_id IS NOT NULL;
DELETE FROM colaboradores_bonus WHERE colaborador_id IS NOT NULL;

-- Onboarding (atualiza referências para NULL)
UPDATE onboarding SET colaborador_id = NULL WHERE colaborador_id IS NOT NULL;
UPDATE onboarding SET mentor_id = NULL WHERE mentor_id IS NOT NULL;

-- Notificações
DELETE FROM notificacoes WHERE colaborador_id IS NOT NULL;
DELETE FROM notificacoes_historico WHERE colaborador_id IS NOT NULL;
DELETE FROM push_notifications WHERE colaborador_id IS NOT NULL;
DELETE FROM push_notification_preferences WHERE colaborador_id IS NOT NULL;
DELETE FROM onesignal_subscriptions WHERE colaborador_id IS NOT NULL;

-- Anotações
DELETE FROM anotacoes_visualizacoes WHERE colaborador_id IS NOT NULL;

-- Pontuação
DELETE FROM pontuacao WHERE colaborador_id IS NOT NULL;
DELETE FROM pontuacao_colaboradores WHERE colaborador_id IS NOT NULL;

-- Demissões
DELETE FROM demissoes WHERE colaborador_id IS NOT NULL;

-- ============================================
-- 2. LIMPAR AUTO-REFERÊNCIAS (lider_id)
-- ============================================
UPDATE colaboradores SET lider_id = NULL WHERE lider_id IS NOT NULL;

-- ============================================
-- 3. LIMPAR REFERÊNCIAS EM USUÁRIOS
-- ============================================
UPDATE usuarios SET colaborador_id = NULL WHERE colaborador_id IS NOT NULL;

-- ============================================
-- 4. EXCLUIR TODOS OS COLABORADORES
-- ============================================
DELETE FROM colaboradores;

-- ============================================
-- 5. REABILITAR VERIFICAÇÃO DE FOREIGN KEYS
-- ============================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================
SELECT COUNT(*) as total_colaboradores FROM colaboradores;
SELECT COUNT(*) as usuarios_com_colaborador FROM usuarios WHERE colaborador_id IS NOT NULL;
SELECT COUNT(*) as ocorrencias_restantes FROM ocorrencias WHERE colaborador_id IS NOT NULL;
SELECT COUNT(*) as pdis_restantes FROM pdis WHERE colaborador_id IS NOT NULL;
SELECT COUNT(*) as chat_conversas_restantes FROM chat_conversas WHERE colaborador_id IS NOT NULL;

-- ============================================
-- FIM DO SCRIPT
-- ============================================

