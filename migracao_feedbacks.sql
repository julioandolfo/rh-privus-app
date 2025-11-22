-- Migração: Sistema de Feedbacks
-- Tabelas para sistema de feedbacks entre colaboradores

-- Tabela de itens de avaliação da empresa
CREATE TABLE IF NOT EXISTS feedback_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL COMMENT 'Nome do item (ex: Alinhamento Cultural)',
    descricao TEXT NULL COMMENT 'Descrição do item',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir itens padrão de avaliação
INSERT INTO feedback_itens (nome, descricao, ordem, status) VALUES
('Alinhamento Cultural', 'Alinhamento com a cultura e valores da empresa', 1, 'ativo'),
('Competência Técnica', 'Possui o conhecimento técnico necessário para desempenhar suas atividades', 2, 'ativo'),
('Trabalho em Equipe', 'Avalia a habilidade do colaborador em colaborar com os outros membros da equipe', 3, 'ativo'),
('Capacidade de Resolução de Problemas', 'Refere-se à habilidade de identificar, analisar e resolver problemas de maneira eficiente', 4, 'ativo'),
('Liderança', 'Avalia se é capaz de motivar e inspirar os membros da equipe', 5, 'ativo'),
('Comunicação Interpessoal', 'A habilidade de se comunicar de forma clara, aberta e respeitosa com os outros', 6, 'ativo');

-- Tabela principal de feedbacks
CREATE TABLE IF NOT EXISTS feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_usuario_id INT NULL COMMENT 'Usuário que enviou o feedback',
    remetente_colaborador_id INT NULL COMMENT 'Colaborador que enviou o feedback',
    destinatario_usuario_id INT NULL COMMENT 'Usuário que recebeu o feedback',
    destinatario_colaborador_id INT NULL COMMENT 'Colaborador que recebeu o feedback',
    template_id INT NULL COMMENT 'ID do template usado (0 = nenhum)',
    conteudo TEXT NOT NULL COMMENT 'Conteúdo do feedback',
    anonimo BOOLEAN DEFAULT FALSE COMMENT 'Se o feedback é anônimo',
    presencial BOOLEAN DEFAULT FALSE COMMENT 'Se o feedback foi dado presencialmente',
    anotacoes_internas TEXT NULL COMMENT 'Anotações privadas do remetente',
    status ENUM('ativo', 'arquivado', 'removido') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (remetente_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (destinatario_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (destinatario_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    INDEX idx_remetente_usuario (remetente_usuario_id),
    INDEX idx_remetente_colaborador (remetente_colaborador_id),
    INDEX idx_destinatario_usuario (destinatario_usuario_id),
    INDEX idx_destinatario_colaborador (destinatario_colaborador_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de avaliações por item (estrelas)
CREATE TABLE IF NOT EXISTS feedback_avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    item_id INT NOT NULL,
    nota INT NOT NULL COMMENT 'Nota de 1 a 5 (estrelas)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES feedback_itens(id) ON DELETE CASCADE,
    UNIQUE KEY uk_feedback_item (feedback_id, item_id),
    INDEX idx_feedback (feedback_id),
    INDEX idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de respostas/comentários (thread de conversa)
CREATE TABLE IF NOT EXISTS feedback_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    usuario_id INT NULL COMMENT 'Usuário que respondeu',
    colaborador_id INT NULL COMMENT 'Colaborador que respondeu',
    resposta TEXT NOT NULL COMMENT 'Conteúdo da resposta',
    resposta_pai_id INT NULL COMMENT 'ID da resposta pai (para threads)',
    status ENUM('ativo', 'removido') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (resposta_pai_id) REFERENCES feedback_respostas(id) ON DELETE CASCADE,
    INDEX idx_feedback (feedback_id),
    INDEX idx_resposta_pai (resposta_pai_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir ação de pontos para enviar feedback (se tabela existir)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('enviar_feedback', 'Enviar feedback para colaborador', 30, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), pontos = VALUES(pontos), ativo = VALUES(ativo);

