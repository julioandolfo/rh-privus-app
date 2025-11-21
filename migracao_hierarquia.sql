-- Migração: Sistema de Hierarquia de Colaboradores
-- Execute este script no banco de dados

-- 1. Criar tabela de níveis hierárquicos
CREATE TABLE IF NOT EXISTS niveis_hierarquicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nivel INT NOT NULL COMMENT 'Nível na hierarquia (1 = mais alto, maior número = mais baixo)',
    descricao TEXT,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_nivel (nivel),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Inserir níveis padrão
INSERT INTO niveis_hierarquicos (nome, codigo, nivel, descricao, status) VALUES
('Diretoria', 'DIRETORIA', 1, 'Nível mais alto da hierarquia', 'ativo'),
('Gerência', 'GERENCIA', 2, 'Nível de gerência', 'ativo'),
('Supervisão', 'SUPERVISAO', 3, 'Nível de supervisão', 'ativo'),
('Coordenação', 'COORDENACAO', 4, 'Nível de coordenação', 'ativo'),
('Liderança', 'LIDERANCA', 5, 'Nível de liderança', 'ativo'),
('Operacional', 'OPERACIONAL', 6, 'Nível operacional', 'ativo');

-- 3. Adicionar campos à tabela colaboradores
ALTER TABLE colaboradores 
ADD COLUMN nivel_hierarquico_id INT NULL AFTER cargo_id,
ADD COLUMN lider_id INT NULL AFTER nivel_hierarquico_id,
ADD FOREIGN KEY (nivel_hierarquico_id) REFERENCES niveis_hierarquicos(id) ON DELETE SET NULL,
ADD FOREIGN KEY (lider_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
ADD INDEX idx_nivel_hierarquico (nivel_hierarquico_id),
ADD INDEX idx_lider (lider_id);

