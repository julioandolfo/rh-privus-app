-- Migração para adicionar campos de horário nas ocorrências
-- Campos: horario_esperado e horario_real (TIME)
-- Servem apenas para visualização do horário de entrada esperado vs real

ALTER TABLE ocorrencias 
ADD COLUMN IF NOT EXISTS horario_esperado TIME NULL COMMENT 'Horário esperado de entrada/saída (apenas visualização)' AFTER tempo_atraso_minutos,
ADD COLUMN IF NOT EXISTS horario_real TIME NULL COMMENT 'Horário real de entrada/saída (apenas visualização)' AFTER horario_esperado;

