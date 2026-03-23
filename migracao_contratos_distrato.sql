-- Distrato automático ao desligar colaborador
-- Execute uma vez. Se a coluna já existir, ignore o erro ou use o ALTER automático do PHP ao acessar Templates.

ALTER TABLE contratos_templates
    ADD COLUMN padrao_distrato TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Template usado no distrato automático ao desligar'
    AFTER ativo;

ALTER TABLE contratos
    ADD COLUMN demissao_id INT NULL
    COMMENT 'Demissão que originou distrato automático'
    AFTER colaborador_id;
