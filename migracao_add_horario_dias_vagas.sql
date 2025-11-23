-- Adiciona campos de horário e dias de trabalho na tabela vagas
ALTER TABLE vagas 
ADD COLUMN horario_trabalho VARCHAR(100) NULL COMMENT 'Ex: 08:00 às 18:00' AFTER localizacao,
ADD COLUMN dias_trabalho VARCHAR(100) NULL COMMENT 'Ex: Segunda a Sexta' AFTER horario_trabalho;

