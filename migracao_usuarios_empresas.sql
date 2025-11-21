-- Script de Migração: Adiciona suporte a múltiplas empresas por usuário
-- Execute este script para permitir que usuários sejam associados a múltiplas empresas

-- Cria tabela de relacionamento muitos-para-muitos
CREATE TABLE IF NOT EXISTS usuarios_empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migra dados existentes da coluna empresa_id para a nova tabela
INSERT INTO usuarios_empresas (usuario_id, empresa_id)
SELECT id, empresa_id 
FROM usuarios 
WHERE empresa_id IS NOT NULL
ON DUPLICATE KEY UPDATE usuario_id = usuario_id;

-- Mantém a coluna empresa_id na tabela usuarios para compatibilidade (pode ser removida depois se necessário)
-- A coluna empresa_id pode ser usada como empresa principal ou para manter compatibilidade com código legado

