-- =============================================================
-- Migração: Adicionar campo Região aos Colaboradores
-- Para uso em templates de contratos
-- =============================================================

-- Adiciona coluna regiao na tabela colaboradores
ALTER TABLE colaboradores 
ADD COLUMN regiao VARCHAR(100) NULL COMMENT 'Região do colaborador para uso em contratos' AFTER estado_endereco;

-- Cria índice para buscas rápidas por região
CREATE INDEX idx_regiao ON colaboradores(regiao);

-- =============================================================
-- Após executar esta migração:
-- 
-- 1. O campo 'Região' estará disponível no cadastro/edição de colaboradores
-- 2. A variável {{colaborador.regiao}} poderá ser usada nos templates de contrato
-- 3. Se o template usar {{colaborador.regiao}} e o campo estiver vazio,
--    o sistema pedirá para preencher ao gerar o contrato
-- =============================================================
