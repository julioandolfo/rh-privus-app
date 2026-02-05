-- Migração: Adicionar tipo 'representante' na tabela de signatários
-- Este tipo é usado para sócios/diretores da empresa que assinam os contratos

-- Altera o ENUM para incluir 'representante'
ALTER TABLE contratos_signatarios 
MODIFY COLUMN tipo ENUM('colaborador', 'testemunha', 'rh', 'representante') NOT NULL;

-- Adiciona índice para buscar representantes
ALTER TABLE contratos_signatarios ADD INDEX IF NOT EXISTS idx_tipo_representante (tipo);
