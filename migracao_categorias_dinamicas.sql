-- ============================================
-- MIGRAÇÃO: Categorias Dinâmicas de Tipos de Ocorrências
-- Converte categorias de ENUM para tabela dinâmica
-- ============================================

-- 1. Criar tabela de categorias
CREATE TABLE IF NOT EXISTS ocorrencias_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    cor VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Cor em hexadecimal para badges',
    descricao VARCHAR(200) NULL,
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Inserir categorias padrão baseadas no ENUM antigo
INSERT INTO ocorrencias_categorias (nome, codigo, cor, ordem) VALUES
('Pontualidade', 'pontualidade', '#ffc700', 1),
('Comportamento', 'comportamento', '#f1416c', 2),
('Desempenho', 'desempenho', '#009ef7', 3),
('Outros', 'outros', '#6c757d', 4);

-- 3. Adicionar coluna categoria_id na tabela tipos_ocorrencias
ALTER TABLE tipos_ocorrencias
ADD COLUMN categoria_id INT NULL AFTER categoria,
ADD INDEX idx_categoria_id (categoria_id),
ADD FOREIGN KEY (categoria_id) REFERENCES ocorrencias_categorias(id) ON DELETE SET NULL;

-- 4. Migrar dados existentes: mapear categoria (ENUM) para categoria_id
UPDATE tipos_ocorrencias t
INNER JOIN ocorrencias_categorias c ON t.categoria = c.codigo
SET t.categoria_id = c.id;

-- 5. Tornar categoria_id obrigatório após migração (opcional, pode manter NULL para compatibilidade)
-- ALTER TABLE tipos_ocorrencias MODIFY COLUMN categoria_id INT NOT NULL;

-- 6. Manter coluna categoria por enquanto para compatibilidade (pode ser removida depois)
-- ALTER TABLE tipos_ocorrencias DROP COLUMN categoria;

