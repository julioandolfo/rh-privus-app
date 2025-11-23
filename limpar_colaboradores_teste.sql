-- ============================================
-- Script para Limpar Colaboradores de Teste
-- ============================================
-- ATENÇÃO: Este script exclui TODOS os colaboradores e seus relacionamentos
-- Use com cuidado! Faça backup antes de executar.
-- ============================================

-- Desabilitar verificação de foreign keys temporariamente para evitar erros
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. LIMPAR TABELAS RELACIONADAS A COLABORADORES
-- ============================================

-- Chat Interno (ordem importante devido a foreign keys)
DELETE FROM chat_mensagens WHERE enviado_por_colaborador_id IS NOT NULL;
DELETE FROM chat_historico_acoes WHERE realizado_por_colaborador_id IS NOT NULL;
DELETE FROM chat_resumos_ia WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL);
DELETE FROM chat_sla_historico WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL);
DELETE FROM chat_participantes WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IS NOT NULL);
DELETE FROM chat_conversas WHERE colaborador_id IS NOT NULL;
DELETE FROM chat_preferencias_usuario WHERE colaborador_id IS NOT NULL;

-- Ocorrências
DELETE FROM ocorrencias_comentarios WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_anexos WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_historico WHERE ocorrencia_id IN (SELECT id FROM ocorrencias);
DELETE FROM ocorrencias_advertencias WHERE colaborador_id IS NOT NULL;
DELETE FROM ocorrencias WHERE colaborador_id IS NOT NULL;

-- Engajamento
DELETE FROM pdi_acoes WHERE pdi_id IN (SELECT id FROM pdis);
DELETE FROM pdi_objetivos WHERE pdi_id IN (SELECT id FROM pdis);
DELETE FROM pdis WHERE colaborador_id IS NOT NULL;
DELETE FROM reunioes_1on1 WHERE lider_id IS NOT NULL OR liderado_id IS NOT NULL;
DELETE FROM celebracoes WHERE remetente_id IS NOT NULL OR destinatario_id IS NOT NULL;
DELETE FROM pesquisas_satisfacao_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_satisfacao_envios WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_rapidas_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM pesquisas_rapidas_envios WHERE colaborador_id IS NOT NULL;
DELETE FROM emocoes WHERE colaborador_id IS NOT NULL;

-- Feed e Feedbacks
DELETE FROM feed_comentarios WHERE colaborador_id IS NOT NULL;
DELETE FROM feed_curtidas WHERE colaborador_id IS NOT NULL;
DELETE FROM feed_posts WHERE colaborador_id IS NOT NULL;
DELETE FROM feedback_respostas WHERE colaborador_id IS NOT NULL;
DELETE FROM feedbacks WHERE remetente_colaborador_id IS NOT NULL OR destinatario_colaborador_id IS NOT NULL;

-- Pagamentos e Benefícios
DELETE FROM fechamentos_pagamento_documentos_historico WHERE item_id IN (SELECT id FROM fechamentos_pagamento_itens WHERE colaborador_id IS NOT NULL);
DELETE FROM fechamentos_pagamento_bonus WHERE colaborador_id IS NOT NULL;
DELETE FROM fechamentos_pagamento_itens WHERE colaborador_id IS NOT NULL;
DELETE FROM horas_extras WHERE colaborador_id IS NOT NULL;
DELETE FROM promocoes WHERE colaborador_id IS NOT NULL;
DELETE FROM colaboradores_bonus WHERE colaborador_id IS NOT NULL;

-- Onboarding (pode ter colaborador_id)
UPDATE onboarding SET colaborador_id = NULL WHERE colaborador_id IS NOT NULL;
UPDATE onboarding SET mentor_id = NULL WHERE mentor_id IS NOT NULL;

-- Notificações
DELETE FROM notificacoes_sistema WHERE colaborador_id IS NOT NULL;
DELETE FROM push_notifications_history WHERE colaborador_id IS NOT NULL;
DELETE FROM push_notification_preferences WHERE colaborador_id IS NOT NULL;
DELETE FROM push_subscriptions WHERE colaborador_id IS NOT NULL;
DELETE FROM onesignal_subscriptions WHERE colaborador_id IS NOT NULL;

-- Anotações
DELETE FROM anotacoes_visualizacoes WHERE colaborador_id IS NOT NULL;

-- Pontuação
DELETE FROM pontos_historico WHERE colaborador_id IS NOT NULL;
DELETE FROM pontos_total WHERE colaborador_id IS NOT NULL;

-- Demissões
DELETE FROM demissoes WHERE colaborador_id IS NOT NULL;

-- ============================================
-- 2. LIMPAR AUTO-REFERÊNCIAS (lider_id)
-- ============================================
-- Primeiro, remove referências de liderança
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
-- Execute estas queries para verificar se ficou algum registro órfão:

-- SELECT COUNT(*) as total_colaboradores FROM colaboradores;
-- SELECT COUNT(*) as usuarios_com_colaborador FROM usuarios WHERE colaborador_id IS NOT NULL;
-- SELECT COUNT(*) as ocorrencias_restantes FROM ocorrencias WHERE colaborador_id IS NOT NULL;
-- SELECT COUNT(*) as pdis_restantes FROM pdis WHERE colaborador_id IS NOT NULL;
-- SELECT COUNT(*) as chat_conversas_restantes FROM chat_conversas WHERE colaborador_id IS NOT NULL;

-- ============================================
-- FIM DO SCRIPT
-- ============================================

