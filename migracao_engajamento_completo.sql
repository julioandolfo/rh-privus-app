-- Migração: Sistema Completo de Engajamento
-- Execute este script no banco de dados

-- ============================================
-- 1. REUNIÕES 1:1
-- ============================================
CREATE TABLE IF NOT EXISTS reunioes_1on1 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lider_id INT NOT NULL COMMENT 'Colaborador líder',
    liderado_id INT NOT NULL COMMENT 'Colaborador liderado',
    data_reuniao DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    status ENUM('agendada', 'realizada', 'cancelada', 'reagendada') DEFAULT 'agendada',
    assuntos_tratados TEXT NULL,
    proximos_passos TEXT NULL,
    avaliacao_liderado TINYINT NULL COMMENT 'Avaliação do liderado (1-5)',
    avaliacao_lider TINYINT NULL COMMENT 'Avaliação do líder (1-5)',
    observacoes TEXT NULL,
    created_by INT NULL COMMENT 'Usuário que criou a reunião',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lider_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (liderado_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_lider (lider_id),
    INDEX idx_liderado (liderado_id),
    INDEX idx_data_reuniao (data_reuniao),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. CELEBRAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS celebracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NULL COMMENT 'Colaborador que criou a celebração',
    remetente_usuario_id INT NULL COMMENT 'Usuário que criou a celebração',
    destinatario_id INT NOT NULL COMMENT 'Colaborador que recebe a celebração',
    tipo ENUM('aniversario', 'promocao', 'conquista', 'reconhecimento', 'outro') DEFAULT 'reconhecimento',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    imagem VARCHAR(500) NULL,
    data_celebração DATE NOT NULL,
    status ENUM('ativo', 'oculto', 'removido') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (remetente_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (remetente_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (destinatario_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_remetente (remetente_id),
    INDEX idx_destinatario (destinatario_id),
    INDEX idx_data_celebração (data_celebração),
    INDEX idx_status (status),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. PESQUISAS DE SATISFAÇÃO (Dinâmicas)
-- ============================================
CREATE TABLE IF NOT EXISTS pesquisas_satisfacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    tipo ENUM('satisfacao', 'clima', 'feedback', 'outro') DEFAULT 'satisfacao',
    data_inicio DATE NOT NULL,
    data_fim DATE NULL COMMENT 'NULL = sem data de término',
    publico_alvo ENUM('todos', 'empresa', 'setor', 'cargo', 'especifico') DEFAULT 'todos',
    empresa_id INT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    participantes_ids TEXT NULL COMMENT 'JSON com IDs de colaboradores específicos',
    status ENUM('rascunho', 'ativa', 'finalizada', 'cancelada') DEFAULT 'rascunho',
    link_token VARCHAR(100) UNIQUE NULL COMMENT 'Token único para link de resposta rápida',
    enviar_email TINYINT(1) DEFAULT 1 COMMENT 'Enviar email ao publicar',
    enviar_push TINYINT(1) DEFAULT 1 COMMENT 'Enviar notificação push ao publicar',
    anonima TINYINT(1) DEFAULT 0 COMMENT 'Se as respostas são anônimas',
    created_by INT NOT NULL COMMENT 'Usuário que criou a pesquisa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_link_token (link_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos dinâmicos da pesquisa
CREATE TABLE IF NOT EXISTS pesquisas_satisfacao_campos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesquisa_id INT NOT NULL,
    tipo ENUM('texto', 'textarea', 'numero', 'data', 'hora', 'email', 'telefone', 'multipla_escolha', 'escala_1_5', 'escala_1_10', 'sim_nao', 'checkbox_multiplo', 'arquivo') NOT NULL,
    label VARCHAR(255) NOT NULL COMMENT 'Rótulo do campo',
    descricao TEXT NULL COMMENT 'Descrição/ajuda do campo',
    obrigatorio TINYINT(1) DEFAULT 0,
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    opcoes TEXT NULL COMMENT 'JSON com opções (para múltipla escolha, checkbox, etc)',
    placeholder VARCHAR(255) NULL,
    valor_padrao TEXT NULL,
    validacao TEXT NULL COMMENT 'JSON com regras de validação',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesquisa_id) REFERENCES pesquisas_satisfacao(id) ON DELETE CASCADE,
    INDEX idx_pesquisa (pesquisa_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respostas dinâmicas
CREATE TABLE IF NOT EXISTS pesquisas_satisfacao_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesquisa_id INT NOT NULL,
    campo_id INT NOT NULL,
    colaborador_id INT NULL COMMENT 'NULL se anônima',
    resposta TEXT NULL COMMENT 'Resposta em texto ou JSON',
    arquivo_path VARCHAR(500) NULL COMMENT 'Caminho do arquivo se tipo = arquivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesquisa_id) REFERENCES pesquisas_satisfacao(id) ON DELETE CASCADE,
    FOREIGN KEY (campo_id) REFERENCES pesquisas_satisfacao_campos(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    INDEX idx_pesquisa (pesquisa_id),
    INDEX idx_campo (campo_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle de quem recebeu a pesquisa
CREATE TABLE IF NOT EXISTS pesquisas_satisfacao_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesquisa_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    token_resposta VARCHAR(100) UNIQUE NULL COMMENT 'Token único para link de resposta deste colaborador',
    email_enviado TINYINT(1) DEFAULT 0,
    push_enviado TINYINT(1) DEFAULT 0,
    link_acessado TINYINT(1) DEFAULT 0,
    respondida TINYINT(1) DEFAULT 0,
    data_resposta DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesquisa_id) REFERENCES pesquisas_satisfacao(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_pesquisa_colaborador (pesquisa_id, colaborador_id),
    INDEX idx_pesquisa (pesquisa_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_token_resposta (token_resposta),
    INDEX idx_respondida (respondida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. PESQUISAS RÁPIDAS (Dinâmicas)
-- ============================================
CREATE TABLE IF NOT EXISTS pesquisas_rapidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    pergunta TEXT NOT NULL,
    tipo_resposta ENUM('sim_nao', 'multipla_escolha', 'texto_curto', 'escala_1_5', 'escala_1_10', 'numero') DEFAULT 'sim_nao',
    opcoes TEXT NULL COMMENT 'JSON com opções (para múltipla escolha)',
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NULL COMMENT 'NULL = sem data de término',
    publico_alvo ENUM('todos', 'empresa', 'setor', 'cargo', 'especifico') DEFAULT 'todos',
    empresa_id INT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    participantes_ids TEXT NULL COMMENT 'JSON com IDs de colaboradores específicos',
    status ENUM('rascunho', 'ativa', 'finalizada', 'cancelada') DEFAULT 'rascunho',
    link_token VARCHAR(100) UNIQUE NULL COMMENT 'Token único para link de resposta rápida',
    enviar_email TINYINT(1) DEFAULT 1,
    enviar_push TINYINT(1) DEFAULT 1,
    anonima TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_link_token (link_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respostas de pesquisas rápidas
CREATE TABLE IF NOT EXISTS pesquisas_rapidas_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesquisa_id INT NOT NULL,
    colaborador_id INT NULL COMMENT 'NULL se anônima',
    resposta TEXT NOT NULL COMMENT 'Resposta em texto ou JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesquisa_id) REFERENCES pesquisas_rapidas(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    UNIQUE KEY uk_pesquisa_colaborador (pesquisa_id, colaborador_id),
    INDEX idx_pesquisa (pesquisa_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle de envios
CREATE TABLE IF NOT EXISTS pesquisas_rapidas_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesquisa_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    token_resposta VARCHAR(100) UNIQUE NULL COMMENT 'Token único para link de resposta deste colaborador',
    email_enviado TINYINT(1) DEFAULT 0,
    push_enviado TINYINT(1) DEFAULT 0,
    link_acessado TINYINT(1) DEFAULT 0,
    respondida TINYINT(1) DEFAULT 0,
    data_resposta DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pesquisa_id) REFERENCES pesquisas_rapidas(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_pesquisa_colaborador (pesquisa_id, colaborador_id),
    INDEX idx_pesquisa (pesquisa_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_token_resposta (token_resposta),
    INDEX idx_respondida (respondida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. PDIs (Planos de Desenvolvimento Individual)
-- ============================================
CREATE TABLE IF NOT EXISTS pdis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    objetivo_geral TEXT NULL,
    data_inicio DATE NOT NULL,
    data_fim_prevista DATE NULL,
    data_fim_real DATE NULL,
    status ENUM('rascunho', 'ativo', 'concluido', 'cancelado', 'pausado') DEFAULT 'rascunho',
    progresso_percentual INT DEFAULT 0 COMMENT '0-100',
    criado_por INT NOT NULL COMMENT 'Usuário que criou o PDI',
    enviar_email TINYINT(1) DEFAULT 1 COMMENT 'Enviar email ao criar/atualizar',
    enviar_push TINYINT(1) DEFAULT 1 COMMENT 'Enviar notificação push',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdi_objetivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdi_id INT NOT NULL,
    objetivo TEXT NOT NULL,
    descricao TEXT NULL,
    prazo DATE NULL,
    status ENUM('pendente', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'pendente',
    data_conclusao DATE NULL,
    observacoes TEXT NULL,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pdi_id) REFERENCES pdis(id) ON DELETE CASCADE,
    INDEX idx_pdi (pdi_id),
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdi_acoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdi_id INT NOT NULL,
    objetivo_id INT NULL COMMENT 'NULL se ação não vinculada a objetivo específico',
    acao TEXT NOT NULL,
    descricao TEXT NULL,
    prazo DATE NULL,
    status ENUM('pendente', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'pendente',
    data_conclusao DATE NULL,
    evidencia TEXT NULL COMMENT 'Texto ou caminho de arquivo',
    observacoes TEXT NULL,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pdi_id) REFERENCES pdis(id) ON DELETE CASCADE,
    FOREIGN KEY (objetivo_id) REFERENCES pdi_objetivos(id) ON DELETE SET NULL,
    INDEX idx_pdi (pdi_id),
    INDEX idx_objetivo (objetivo_id),
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. HISTÓRICO DE ACESSOS
-- ============================================
CREATE TABLE IF NOT EXISTS acessos_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    colaborador_id INT NULL,
    data_acesso DATE NOT NULL,
    hora_acesso TIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_acesso (data_acesso),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CONFIGURAÇÕES DE PERMISSÕES E NOTIFICAÇÕES
-- ============================================
CREATE TABLE IF NOT EXISTS engajamento_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo ENUM('reunioes_1on1', 'celebracoes', 'pesquisas_satisfacao', 'pesquisas_rapidas', 'pdis') NOT NULL,
    ativo TINYINT(1) DEFAULT 1 COMMENT 'Se o módulo está ativo',
    enviar_email TINYINT(1) DEFAULT 1 COMMENT 'Enviar emails por padrão',
    enviar_push TINYINT(1) DEFAULT 1 COMMENT 'Enviar push por padrão',
    roles_permitidos TEXT NULL COMMENT 'JSON com roles que podem usar (NULL = todos)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO engajamento_config (modulo, ativo, enviar_email, enviar_push, roles_permitidos) VALUES
('reunioes_1on1', 1, 1, 1, NULL),
('celebracoes', 1, 1, 1, NULL),
('pesquisas_satisfacao', 1, 1, 1, NULL),
('pesquisas_rapidas', 1, 1, 1, NULL),
('pdis', 1, 1, 1, NULL)
ON DUPLICATE KEY UPDATE modulo=VALUES(modulo);

