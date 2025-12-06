-- ============================================
-- MIGRAÇÃO: Sistema de Flags Automáticas
-- Implementa sistema de flags com validade de 30 dias
-- ============================================

-- 1. Criar tabela de flags
CREATE TABLE IF NOT EXISTS ocorrencias_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    ocorrencia_id INT NOT NULL,
    tipo_flag ENUM('falta_nao_justificada', 'falta_compromisso_pessoal', 'ma_conduta') NOT NULL,
    data_flag DATE NOT NULL COMMENT 'Data em que a flag foi recebida',
    data_validade DATE NOT NULL COMMENT 'Data de expiração (30 dias após data_flag)',
    status ENUM('ativa', 'expirada') DEFAULT 'ativa',
    observacoes TEXT NULL COMMENT 'Observações sobre a flag',
    created_by INT NOT NULL COMMENT 'Usuário que criou a flag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_ocorrencia (ocorrencia_id),
    INDEX idx_data_flag (data_flag),
    INDEX idx_data_validade (data_validade),
    INDEX idx_status (status),
    INDEX idx_colaborador_status (colaborador_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Criar tabela de histórico de flags (para auditoria)
CREATE TABLE IF NOT EXISTS ocorrencias_flags_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flag_id INT NOT NULL,
    acao ENUM('criada', 'expirada', 'renovada', 'cancelada') NOT NULL,
    usuario_id INT NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flag_id) REFERENCES ocorrencias_flags(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_flag (flag_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Adicionar campo na tabela tipos_ocorrencias para indicar se gera flag
ALTER TABLE tipos_ocorrencias
ADD COLUMN gera_flag BOOLEAN DEFAULT FALSE 
    COMMENT 'Se TRUE, gera flag automaticamente ao criar ocorrência deste tipo' 
    AFTER conta_advertencia,
ADD COLUMN tipo_flag ENUM('falta_nao_justificada', 'falta_compromisso_pessoal', 'ma_conduta') NULL 
    COMMENT 'Tipo de flag gerada (se gera_flag = TRUE)' 
    AFTER gera_flag;

-- 4. Atualizar tipos de ocorrências existentes para gerar flags
-- Falta não justificada
UPDATE tipos_ocorrencias 
SET gera_flag = TRUE, tipo_flag = 'falta_nao_justificada' 
WHERE codigo = 'falta' OR codigo = 'ausencia_injustificada';

-- Má conduta
UPDATE tipos_ocorrencias 
SET gera_flag = TRUE, tipo_flag = 'ma_conduta' 
WHERE codigo = 'comportamento_inadequado';

-- 5. Criar índice composto para busca eficiente de flags ativas
CREATE INDEX idx_flags_ativas ON ocorrencias_flags(colaborador_id, status, data_validade);

-- 6. Criar view para estatísticas de flags por colaborador
CREATE OR REPLACE VIEW vw_flags_estatisticas AS
SELECT 
    c.id as colaborador_id,
    c.nome_completo,
    COUNT(CASE WHEN f.status = 'ativa' THEN 1 END) as flags_ativas,
    COUNT(CASE WHEN f.status = 'expirada' THEN 1 END) as flags_expiradas,
    COUNT(f.id) as total_flags,
    MAX(CASE WHEN f.status = 'ativa' THEN f.data_validade END) as proxima_expiracao,
    MIN(CASE WHEN f.status = 'ativa' THEN f.data_flag END) as primeira_flag_ativa
FROM colaboradores c
LEFT JOIN ocorrencias_flags f ON c.id = f.colaborador_id
WHERE c.status = 'ativo'
GROUP BY c.id, c.nome_completo;

