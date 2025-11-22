-- Migração: Sistema de Análise de Emoções
-- Tabela para registrar emoções diárias dos colaboradores/usuários

CREATE TABLE IF NOT EXISTS emocoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário do sistema',
    colaborador_id INT NULL COMMENT 'Colaborador (se não tiver usuário)',
    nivel_emocao TINYINT NOT NULL COMMENT '1=Muito triste, 2=Triste, 3=Neutro, 4=Feliz, 5=Muito feliz',
    descricao TEXT NULL COMMENT 'Descrição do que causou essa emoção',
    data_registro DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_registro (data_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

