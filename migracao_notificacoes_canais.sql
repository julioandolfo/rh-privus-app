-- ============================================
-- MIGRAÇÃO: Controle de Canais de Notificação por Tipo de Ocorrência
-- Permite ativar/desativar sistema, email e push separadamente
-- ============================================

-- Adicionar campos para controlar canais de notificação do colaborador
ALTER TABLE tipos_ocorrencias
ADD COLUMN notificar_colaborador_sistema BOOLEAN DEFAULT TRUE AFTER notificar_colaborador,
ADD COLUMN notificar_colaborador_email BOOLEAN DEFAULT TRUE AFTER notificar_colaborador_sistema,
ADD COLUMN notificar_colaborador_push BOOLEAN DEFAULT TRUE AFTER notificar_colaborador_email;

-- Adicionar campos para controlar canais de notificação do gestor
ALTER TABLE tipos_ocorrencias
ADD COLUMN notificar_gestor_sistema BOOLEAN DEFAULT TRUE AFTER notificar_gestor,
ADD COLUMN notificar_gestor_email BOOLEAN DEFAULT TRUE AFTER notificar_gestor_sistema,
ADD COLUMN notificar_gestor_push BOOLEAN DEFAULT TRUE AFTER notificar_gestor_email;

-- Adicionar campos para controlar canais de notificação do RH
ALTER TABLE tipos_ocorrencias
ADD COLUMN notificar_rh_sistema BOOLEAN DEFAULT TRUE AFTER notificar_rh,
ADD COLUMN notificar_rh_email BOOLEAN DEFAULT TRUE AFTER notificar_rh_sistema,
ADD COLUMN notificar_rh_push BOOLEAN DEFAULT TRUE AFTER notificar_rh_email;

-- Atualizar registros existentes para manter comportamento atual (todos os canais ativos)
UPDATE tipos_ocorrencias SET
    notificar_colaborador_sistema = notificar_colaborador,
    notificar_colaborador_email = notificar_colaborador,
    notificar_colaborador_push = notificar_colaborador,
    notificar_gestor_sistema = notificar_gestor,
    notificar_gestor_email = notificar_gestor,
    notificar_gestor_push = notificar_gestor,
    notificar_rh_sistema = notificar_rh,
    notificar_rh_email = notificar_rh,
    notificar_rh_push = notificar_rh;

