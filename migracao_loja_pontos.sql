-- =============================================
-- Migração: Loja de Pontos
-- Sistema completo de troca de pontos por produtos
-- =============================================

-- Tabela de Configurações da Loja
CREATE TABLE IF NOT EXISTS loja_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descricao VARCHAR(255),
    tipo ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão
INSERT INTO loja_config (chave, valor, descricao, tipo) VALUES
('loja_ativa', '1', 'Ativa ou desativa a loja de pontos', 'boolean'),
('aprovacao_obrigatoria', '1', 'Resgates precisam de aprovação do admin', 'boolean'),
('limite_resgates_mes', '0', 'Limite de resgates por mês por colaborador (0 = sem limite)', 'number'),
('mensagem_loja_fechada', 'A loja está temporariamente fechada. Volte em breve!', 'Mensagem exibida quando a loja está fechada', 'text'),
('notificar_admin_resgate', '1', 'Notificar admins sobre novos resgates', 'boolean'),
('notificar_colaborador_status', '1', 'Notificar colaborador sobre mudança de status do resgate', 'boolean'),
('dias_novidade', '7', 'Quantos dias um produto é considerado novidade', 'number'),
('estoque_baixo_limite', '5', 'Limite para exibir alerta de estoque baixo', 'number')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Tabela de Categorias
CREATE TABLE IF NOT EXISTS loja_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    icone VARCHAR(100) DEFAULT 'ki-category',
    cor VARCHAR(20) DEFAULT 'primary',
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias padrão
INSERT INTO loja_categorias (nome, descricao, icone, cor, ordem, ativo) VALUES
('Eletrônicos', 'Gadgets, acessórios e dispositivos eletrônicos', 'ki-technology-2', 'primary', 1, 1),
('Vale-Presente', 'Vales para lojas e serviços', 'ki-gift', 'success', 2, 1),
('Experiências', 'Ingressos, viagens e experiências únicas', 'ki-rocket', 'warning', 3, 1),
('Casa e Decoração', 'Itens para casa e decoração', 'ki-home-2', 'info', 4, 1),
('Bem-Estar', 'Produtos de saúde e bem-estar', 'ki-heart', 'danger', 5, 1),
('Outros', 'Outros produtos e prêmios', 'ki-abstract-26', 'dark', 99, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS loja_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    descricao_curta VARCHAR(255),
    imagem VARCHAR(255),
    pontos_necessarios INT NOT NULL DEFAULT 0,
    estoque INT NULL COMMENT 'NULL = ilimitado',
    limite_por_colaborador INT NULL COMMENT 'NULL = sem limite',
    disponivel_de DATE NULL,
    disponivel_ate DATE NULL,
    destaque TINYINT(1) DEFAULT 0,
    novidade TINYINT(1) DEFAULT 1,
    ativo TINYINT(1) DEFAULT 1,
    total_resgates INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES loja_categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_categoria (categoria_id),
    INDEX idx_ativo (ativo),
    INDEX idx_destaque (destaque),
    INDEX idx_pontos (pontos_necessarios)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Resgates
CREATE TABLE IF NOT EXISTS loja_resgates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT DEFAULT 1,
    pontos_unitario INT NOT NULL,
    pontos_total INT NOT NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'preparando', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    observacao_colaborador TEXT,
    observacao_admin TEXT,
    motivo_rejeicao TEXT,
    aprovado_por INT NULL,
    data_aprovacao DATETIME NULL,
    preparado_por INT NULL,
    data_preparacao DATETIME NULL,
    enviado_por INT NULL,
    data_envio DATETIME NULL,
    codigo_rastreio VARCHAR(100) NULL,
    entregue_por INT NULL,
    data_entrega DATETIME NULL,
    cancelado_por INT NULL,
    data_cancelamento DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES loja_produtos(id) ON DELETE RESTRICT,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (preparado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (entregue_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_produto (produto_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Wishlist (Lista de Desejos)
CREATE TABLE IF NOT EXISTS loja_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    produto_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wishlist (colaborador_id, produto_id),
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES loja_produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Avaliações de Produtos (opcional, para futuro)
CREATE TABLE IF NOT EXISTS loja_avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    produto_id INT NOT NULL,
    resgate_id INT NOT NULL,
    nota INT NOT NULL CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_avaliacao (resgate_id),
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES loja_produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (resgate_id) REFERENCES loja_resgates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Log de Ações (auditoria)
CREATE TABLE IF NOT EXISTS loja_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(50) NOT NULL,
    entidade VARCHAR(50) NOT NULL,
    entidade_id INT NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_entidade (entidade, entidade_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona ação de resgate na configuração de pontos (débito)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('resgate_loja', 'Resgate de produto na loja (débito)', 0, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Adiciona ação de estorno de resgate (crédito)
INSERT INTO pontos_config (acao, descricao, pontos, ativo) 
VALUES ('estorno_resgate', 'Estorno de resgate cancelado/rejeitado (crédito)', 0, 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- View para estatísticas da loja
CREATE OR REPLACE VIEW vw_loja_estatisticas AS
SELECT 
    (SELECT COUNT(*) FROM loja_produtos WHERE ativo = 1) as total_produtos_ativos,
    (SELECT COUNT(*) FROM loja_categorias WHERE ativo = 1) as total_categorias_ativas,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'pendente') as resgates_pendentes,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'aprovado') as resgates_aprovados,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'preparando') as resgates_preparando,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'enviado') as resgates_enviados,
    (SELECT COUNT(*) FROM loja_resgates WHERE status = 'entregue') as resgates_entregues,
    (SELECT COUNT(*) FROM loja_resgates WHERE DATE(created_at) = CURDATE()) as resgates_hoje,
    (SELECT COALESCE(SUM(pontos_total), 0) FROM loja_resgates WHERE status NOT IN ('cancelado', 'rejeitado')) as pontos_gastos_total,
    (SELECT COALESCE(SUM(pontos_total), 0) FROM loja_resgates WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status NOT IN ('cancelado', 'rejeitado')) as pontos_gastos_mes;

-- Produtos de exemplo
INSERT INTO loja_produtos (categoria_id, nome, descricao, descricao_curta, pontos_necessarios, estoque, destaque, novidade, ativo) VALUES
(1, 'Fone de Ouvido Bluetooth', 'Fone de ouvido sem fio com cancelamento de ruído, bateria de longa duração e qualidade de som premium.', 'Fone bluetooth com cancelamento de ruído', 500, 10, 1, 1, 1),
(1, 'Power Bank 10000mAh', 'Carregador portátil de alta capacidade, compatível com todos os smartphones.', 'Carregador portátil 10000mAh', 300, 20, 0, 1, 1),
(2, 'Vale iFood R$50', 'Crédito de R$50 para uso no aplicativo iFood.', 'Crédito iFood R$50', 200, NULL, 1, 1, 1),
(2, 'Vale Amazon R$100', 'Crédito de R$100 para compras na Amazon.', 'Crédito Amazon R$100', 400, NULL, 0, 1, 1),
(3, 'Day Off', 'Um dia de folga para usar quando quiser (mediante aprovação do gestor).', 'Um dia de folga', 1000, 5, 1, 1, 1),
(3, 'Ingresso Cinema', 'Par de ingressos para qualquer filme em cartaz.', 'Par de ingressos cinema', 150, 30, 0, 1, 1),
(4, 'Caneca Personalizada', 'Caneca de cerâmica com design exclusivo da empresa.', 'Caneca personalizada', 100, 50, 0, 1, 1),
(5, 'Kit Spa em Casa', 'Kit completo para relaxamento: sais de banho, velas aromáticas e máscara facial.', 'Kit relaxamento spa', 350, 15, 0, 1, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Exibe resumo
SELECT 'Loja de Pontos criada com sucesso!' as resultado;
SELECT * FROM loja_config;
SELECT * FROM loja_categorias;
SELECT COUNT(*) as total_produtos FROM loja_produtos;
