-- Migração: Sistema de Personalização do Dashboard
-- Execute este script no banco de dados

-- Tabela para armazenar configurações do dashboard por usuário
CREATE TABLE IF NOT EXISTS dashboard_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    card_id VARCHAR(100) NOT NULL COMMENT 'ID único do card (ex: card_total_colaboradores)',
    posicao_x INT DEFAULT 0 COMMENT 'Posição X no grid',
    posicao_y INT DEFAULT 0 COMMENT 'Posição Y no grid',
    largura INT DEFAULT 3 COMMENT 'Largura do card (1-12 colunas)',
    altura INT DEFAULT 3 COMMENT 'Altura do card em linhas',
    visivel TINYINT(1) DEFAULT 1 COMMENT 'Se o card está visível',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_usuario_card (usuario_id, card_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

