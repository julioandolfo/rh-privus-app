-- Migração: Sistema de Demissões
-- Tabela para registrar demissões de colaboradores

CREATE TABLE IF NOT EXISTS demissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    data_demissao DATE NOT NULL,
    motivo TEXT NULL,
    tipo_demissao ENUM('sem_justa_causa', 'justa_causa', 'pedido_demissao', 'aposentadoria', 'falecimento', 'outro') DEFAULT 'sem_justa_causa',
    usuario_id INT NULL COMMENT 'Usuário que registrou a demissão',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_demissao (data_demissao),
    INDEX idx_tipo_demissao (tipo_demissao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

