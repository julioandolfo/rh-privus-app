-- Migração: Sistema de Endomarketing
-- Datas comemorativas e ações/eventos

-- 1. Tabela de datas comemorativas
CREATE TABLE IF NOT EXISTS datas_comemorativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL COMMENT 'Nome da data comemorativa',
    descricao TEXT NULL,
    data_comemoracao DATE NOT NULL COMMENT 'Data da comemoração (ano será ignorado, apenas dia/mês)',
    tipo ENUM('nacional', 'internacional', 'empresa', 'setor', 'personalizada') DEFAULT 'nacional',
    recorrente TINYINT(1) DEFAULT 1 COMMENT 'Se repete todo ano',
    ativo TINYINT(1) DEFAULT 1,
    empresa_id INT NULL COMMENT 'Se tipo = empresa, vincula à empresa',
    setor_id INT NULL COMMENT 'Se tipo = setor, vincula ao setor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE,
    INDEX idx_data_comemoracao (data_comemoracao),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de ações/eventos de endomarketing
CREATE TABLE IF NOT EXISTS endomarketing_acoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_comemorativa_id INT NULL COMMENT 'Vinculado a uma data comemorativa',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    tipo_acao ENUM('evento', 'premiacao', 'comunicacao', 'decoracao', 'brinde', 'reuniao', 'outro') DEFAULT 'evento',
    data_inicio DATE NOT NULL,
    data_fim DATE NULL COMMENT 'Se for evento com duração',
    orcamento DECIMAL(10,2) NULL,
    responsavel_id INT NULL COMMENT 'Usuário responsável',
    status ENUM('planejado', 'em_andamento', 'concluido', 'cancelado', 'adiado') DEFAULT 'planejado',
    publico_alvo ENUM('todos', 'empresa', 'setor', 'cargo', 'especifico') DEFAULT 'todos',
    empresa_id INT NULL COMMENT 'Se público = empresa',
    setor_id INT NULL COMMENT 'Se público = setor',
    cargo_id INT NULL COMMENT 'Se público = cargo',
    participantes TEXT NULL COMMENT 'JSON com IDs de colaboradores específicos se público = especifico',
    resultado TEXT NULL COMMENT 'Resultado/aprendizados da ação',
    fotos TEXT NULL COMMENT 'JSON com caminhos das fotos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (data_comemorativa_id) REFERENCES datas_comemorativas(id) ON DELETE SET NULL,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE CASCADE,
    INDEX idx_data_comemorativa (data_comemorativa_id),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_status (status),
    INDEX idx_tipo_acao (tipo_acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de acompanhamento de ações (checklist/tarefas)
CREATE TABLE IF NOT EXISTS endomarketing_acoes_tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acao_id INT NOT NULL,
    tarefa VARCHAR(255) NOT NULL,
    responsavel_id INT NULL COMMENT 'Usuário responsável pela tarefa',
    prazo DATE NULL,
    concluida TINYINT(1) DEFAULT 0,
    data_conclusao DATETIME NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (acao_id) REFERENCES endomarketing_acoes(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_acao (acao_id),
    INDEX idx_concluida (concluida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Inserir datas comemorativas mais famosas
INSERT INTO datas_comemorativas (nome, descricao, data_comemoracao, tipo, recorrente, ativo) VALUES
('Ano Novo', 'Celebração do início do ano', '2024-01-01', 'nacional', 1, 1),
('Carnaval', 'Festa popular brasileira', '2024-02-13', 'nacional', 1, 1),
('Dia Internacional da Mulher', 'Celebração das conquistas das mulheres', '2024-03-08', 'internacional', 1, 1),
('Páscoa', 'Celebração religiosa', '2024-03-31', 'nacional', 1, 1),
('Dia do Trabalhador', 'Dia Internacional do Trabalho', '2024-05-01', 'internacional', 1, 1),
('Dia das Mães', 'Homenagem às mães', '2024-05-12', 'nacional', 1, 1),
('Dia dos Namorados', 'Dia de São Valentim no Brasil', '2024-06-12', 'nacional', 1, 1),
('Dia dos Pais', 'Homenagem aos pais', '2024-08-11', 'nacional', 1, 1),
('Independência do Brasil', 'Dia da Independência', '2024-09-07', 'nacional', 1, 1),
('Dia das Crianças', 'Celebração das crianças', '2024-10-12', 'nacional', 1, 1),
('Dia do Professor', 'Homenagem aos professores', '2024-10-15', 'nacional', 1, 1),
('Halloween', 'Dia das Bruxas', '2024-10-31', 'internacional', 1, 1),
('Finados', 'Dia de Finados', '2024-11-02', 'nacional', 1, 1),
('Proclamação da República', 'Dia da Proclamação da República', '2024-11-15', 'nacional', 1, 1),
('Dia da Consciência Negra', 'Celebração da cultura afro-brasileira', '2024-11-20', 'nacional', 1, 1),
('Black Friday', 'Dia de promoções', '2024-11-29', 'internacional', 1, 1),
('Natal', 'Celebração do nascimento de Jesus', '2024-12-25', 'nacional', 1, 1),
('Réveillon', 'Celebração de fim de ano', '2024-12-31', 'nacional', 1, 1)
ON DUPLICATE KEY UPDATE nome=VALUES(nome);

