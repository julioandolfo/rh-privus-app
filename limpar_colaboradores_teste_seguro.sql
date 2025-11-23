-- ============================================
-- Script SEGURO para Limpar Colaboradores de Teste
-- ============================================
-- Esta versão permite filtrar por empresa ou data
-- Use esta versão se quiser manter alguns colaboradores
-- ============================================

-- ============================================
-- CONFIGURAÇÃO: Defina os filtros aqui
-- ============================================

-- Opção 1: Limpar colaboradores de uma empresa específica
-- Descomente e ajuste o ID da empresa:
-- SET @empresa_id_filtro = 1;

-- Opção 2: Limpar colaboradores criados antes de uma data específica
-- Descomente e ajuste a data (formato: 'YYYY-MM-DD'):
-- SET @data_limite = '2024-01-01';

-- Opção 3: Limpar colaboradores de teste (com nomes específicos)
-- Descomente e ajuste os nomes:
-- SET @nomes_teste = 'Teste%,%Teste%,%Teste';

-- Opção 4: Limpar TODOS os colaboradores (CUIDADO!)
-- Descomente para limpar tudo:
-- SET @limpar_todos = 1;

-- ============================================
-- NÃO ALTERE NADA ABAIXO DESTA LINHA
-- ============================================

-- Criar tabela temporária com IDs dos colaboradores a serem excluídos
CREATE TEMPORARY TABLE IF NOT EXISTS colaboradores_a_excluir AS
SELECT id FROM colaboradores WHERE 1=0; -- Inicializa vazia

-- Popular tabela temporária baseado nos filtros
-- Se nenhum filtro estiver definido, não exclui nada (segurança)

-- Filtro por empresa
INSERT INTO colaboradores_a_excluir (id)
SELECT id FROM colaboradores 
WHERE (@empresa_id_filtro IS NOT NULL AND empresa_id = @empresa_id_filtro)
AND id NOT IN (SELECT id FROM colaboradores_a_excluir);

-- Filtro por data
INSERT INTO colaboradores_a_excluir (id)
SELECT id FROM colaboradores 
WHERE (@data_limite IS NOT NULL AND DATE(created_at) < @data_limite)
AND id NOT IN (SELECT id FROM colaboradores_a_excluir);

-- Filtro por nomes de teste
INSERT INTO colaboradores_a_excluir (id)
SELECT id FROM colaboradores 
WHERE (@nomes_teste IS NOT NULL AND nome_completo LIKE @nomes_teste)
AND id NOT IN (SELECT id FROM colaboradores_a_excluir);

-- Filtro para limpar todos (apenas se explicitamente definido)
INSERT INTO colaboradores_a_excluir (id)
SELECT id FROM colaboradores 
WHERE (@limpar_todos = 1)
AND id NOT IN (SELECT id FROM colaboradores_a_excluir);

-- ============================================
-- VERIFICAÇÃO ANTES DE EXCLUIR
-- ============================================
-- Execute esta query para ver quantos colaboradores serão excluídos:
-- SELECT COUNT(*) as total_a_excluir FROM colaboradores_a_excluir;
-- SELECT c.* FROM colaboradores c INNER JOIN colaboradores_a_excluir e ON c.id = e.id;

-- ============================================
-- DESCOMENTE AS LINHAS ABAIXO PARA EXECUTAR
-- ============================================

/*
SET FOREIGN_KEY_CHECKS = 0;

-- Chat Interno (ordem importante devido a foreign keys)
DELETE FROM chat_mensagens WHERE enviado_por_colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM chat_historico_acoes WHERE realizado_por_colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM chat_resumos_ia WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM chat_sla_historico WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM chat_participantes WHERE conversa_id IN (SELECT id FROM chat_conversas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM chat_conversas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM chat_preferencias_usuario WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Ocorrências
DELETE FROM ocorrencias_comentarios WHERE ocorrencia_id IN (SELECT id FROM ocorrencias WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM ocorrencias_anexos WHERE ocorrencia_id IN (SELECT id FROM ocorrencias WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM ocorrencias_historico WHERE ocorrencia_id IN (SELECT id FROM ocorrencias WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM ocorrencias_advertencias WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM ocorrencias WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Engajamento
DELETE FROM pdi_acoes WHERE pdi_id IN (SELECT id FROM pdis WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM pdi_objetivos WHERE pdi_id IN (SELECT id FROM pdis WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM pdis WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM reunioes_1on1 WHERE lider_id IN (SELECT id FROM colaboradores_a_excluir) OR liderado_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM celebracoes WHERE remetente_id IN (SELECT id FROM colaboradores_a_excluir) OR destinatario_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM pesquisas_satisfacao_respostas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM pesquisas_satisfacao_envios WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM pesquisas_rapidas_respostas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM pesquisas_rapidas_envios WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM emocoes WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Feed e Feedbacks
DELETE FROM feed_comentarios WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM feed_curtidas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM feed_posts WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM feedback_respostas WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM feedbacks WHERE remetente_colaborador_id IN (SELECT id FROM colaboradores_a_excluir) OR destinatario_colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Pagamentos e Benefícios
DELETE FROM fechamentos_pagamento_documentos_historico WHERE item_id IN (SELECT id FROM fechamentos_pagamento_itens WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir));
DELETE FROM fechamentos_pagamento_bonus WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM fechamentos_pagamento_itens WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM horas_extras WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM promocoes WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM colaboradores_bonus WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Onboarding
UPDATE onboarding SET colaborador_id = NULL WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
UPDATE onboarding SET mentor_id = NULL WHERE mentor_id IN (SELECT id FROM colaboradores_a_excluir);

-- Notificações
DELETE FROM notificacoes_sistema WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM push_notifications_history WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM push_notification_preferences WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM push_subscriptions WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM onesignal_subscriptions WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Anotações
DELETE FROM anotacoes_visualizacoes WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Pontuação
DELETE FROM pontos_historico WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);
DELETE FROM pontos_total WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Demissões
DELETE FROM demissoes WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Limpar auto-referências (lider_id)
UPDATE colaboradores SET lider_id = NULL WHERE lider_id IN (SELECT id FROM colaboradores_a_excluir);

-- Limpar referências em usuários
UPDATE usuarios SET colaborador_id = NULL WHERE colaborador_id IN (SELECT id FROM colaboradores_a_excluir);

-- Excluir os colaboradores
DELETE FROM colaboradores WHERE id IN (SELECT id FROM colaboradores_a_excluir);

SET FOREIGN_KEY_CHECKS = 1;

-- Limpar tabela temporária
DROP TEMPORARY TABLE IF EXISTS colaboradores_a_excluir;
*/

-- ============================================
-- FIM DO SCRIPT
-- ============================================

