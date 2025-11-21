-- Migração: Melhorias no Sistema de Ocorrências
-- Execute este script no banco de dados

-- 1. Criar tabela de tipos de ocorrências
CREATE TABLE IF NOT EXISTS tipos_ocorrencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    categoria ENUM('pontualidade', 'comportamento', 'desempenho', 'outros') DEFAULT 'outros',
    permite_tempo_atraso BOOLEAN DEFAULT FALSE,
    permite_tipo_ponto BOOLEAN DEFAULT FALSE,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_categoria (categoria),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Inserir tipos padrão de ocorrências
INSERT INTO tipos_ocorrencias (nome, codigo, categoria, permite_tempo_atraso, permite_tipo_ponto, status) VALUES
('Atraso na Entrada', 'atraso_entrada', 'pontualidade', TRUE, TRUE, 'ativo'),
('Atraso no Retorno do Almoço', 'atraso_almoco', 'pontualidade', TRUE, TRUE, 'ativo'),
('Atraso no Retorno do Café', 'atraso_cafe', 'pontualidade', TRUE, TRUE, 'ativo'),
('Saída Antecipada', 'saida_antecipada', 'pontualidade', FALSE, TRUE, 'ativo'),
('Falta', 'falta', 'pontualidade', FALSE, FALSE, 'ativo'),
('Ausência Injustificada', 'ausencia_injustificada', 'pontualidade', FALSE, FALSE, 'ativo'),
('Falha Operacional', 'falha_operacional', 'desempenho', FALSE, FALSE, 'ativo'),
('Desempenho Baixo', 'desempenho_baixo', 'desempenho', FALSE, FALSE, 'ativo'),
('Comportamento Inadequado', 'comportamento_inadequado', 'comportamento', FALSE, FALSE, 'ativo'),
('Advertência', 'advertencia', 'comportamento', FALSE, FALSE, 'ativo'),
('Elogio', 'elogio', 'outros', FALSE, FALSE, 'ativo');

-- 3. Adicionar campos à tabela ocorrencias
ALTER TABLE ocorrencias 
ADD COLUMN tipo_ocorrencia_id INT NULL AFTER tipo,
ADD COLUMN tempo_atraso_minutos INT NULL AFTER tipo_ocorrencia_id,
ADD COLUMN tipo_ponto ENUM('entrada', 'almoco', 'cafe', 'saida') NULL AFTER tempo_atraso_minutos,
ADD COLUMN hora_ocorrencia TIME NULL AFTER data_ocorrencia,
ADD FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE SET NULL,
ADD INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
ADD INDEX idx_tipo_ponto (tipo_ponto);

-- 4. Migrar dados existentes (associar tipos antigos aos novos)
UPDATE ocorrencias o
LEFT JOIN tipos_ocorrencias t ON (
    CASE o.tipo
        WHEN 'atraso' THEN t.codigo = 'atraso_entrada'
        WHEN 'falta' THEN t.codigo = 'falta'
        WHEN 'ausência injustificada' THEN t.codigo = 'ausencia_injustificada'
        WHEN 'falha operacional' THEN t.codigo = 'falha_operacional'
        WHEN 'desempenho baixo' THEN t.codigo = 'desempenho_baixo'
        WHEN 'comportamento inadequado' THEN t.codigo = 'comportamento_inadequado'
        WHEN 'advertência' THEN t.codigo = 'advertencia'
        WHEN 'elogio' THEN t.codigo = 'elogio'
        ELSE NULL
    END
)
SET o.tipo_ocorrencia_id = t.id
WHERE t.id IS NOT NULL;

