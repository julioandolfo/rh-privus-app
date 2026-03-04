-- Migração: adiciona colunas de controle de entrega de e-mail e gestão de testemunhas
-- Data: 2026-03-04

-- Colunas de status de entrega do e-mail do Autentique
ALTER TABLE contratos_signatarios
    ADD COLUMN IF NOT EXISTS falha_envio      TINYINT(1)   NOT NULL DEFAULT 0    COMMENT '1 = e-mail retornou bounce/recusado',
    ADD COLUMN IF NOT EXISTS motivo_falha     TEXT         NULL                  COMMENT 'Mensagem de erro retornada pelo servidor de e-mail',
    ADD COLUMN IF NOT EXISTS email_enviado_em DATETIME     NULL                  COMMENT 'Quando o Autentique enviou o e-mail',
    ADD COLUMN IF NOT EXISTS substituido_por  INT          NULL                  COMMENT 'ID do signatário que substituiu este (se for substituição)',
    ADD COLUMN IF NOT EXISTS substituido_em   DATETIME     NULL                  COMMENT 'Data/hora da substituição',
    ADD CONSTRAINT fk_signatario_substituto
        FOREIGN KEY (substituido_por) REFERENCES contratos_signatarios(id)
        ON DELETE SET NULL;

-- Índice para facilitar buscas por falha
ALTER TABLE contratos_signatarios
    ADD INDEX IF NOT EXISTS idx_falha_envio (falha_envio);
