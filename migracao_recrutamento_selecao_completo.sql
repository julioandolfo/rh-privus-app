-- ============================================
-- MIGRAÇÃO COMPLETA: Sistema de Recrutamento e Seleção
-- Inclui: Vagas, Candidatos, Candidaturas, Etapas, Automações, Landing Pages
-- ============================================

-- ============================================
-- 1. VAGAS
-- ============================================
CREATE TABLE IF NOT EXISTS vagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    requisitos_obrigatorios TEXT NULL,
    requisitos_desejaveis TEXT NULL,
    competencias_tecnicas TEXT NULL,
    competencias_comportamentais TEXT NULL,
    tipo_contrato ENUM('CLT', 'PJ', 'Estágio', 'Temporário', 'Freelance') DEFAULT 'CLT',
    modalidade ENUM('Presencial', 'Remoto', 'Híbrido') DEFAULT 'Presencial',
    salario_min DECIMAL(10,2) NULL,
    salario_max DECIMAL(10,2) NULL,
    beneficios JSON NULL COMMENT 'Array de benefícios: transporte, alimentação, saúde, etc',
    localizacao VARCHAR(255) NULL,
    quantidade_vagas INT DEFAULT 1,
    quantidade_preenchida INT DEFAULT 0,
    status ENUM('aberta', 'pausada', 'fechada', 'cancelada') DEFAULT 'aberta',
    publicar_portal BOOLEAN DEFAULT TRUE,
    usar_landing_page_customizada BOOLEAN DEFAULT FALSE COMMENT 'Usar landing page customizada ao invés do template padrão',
    data_abertura DATE NULL,
    data_fechamento DATE NULL,
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_status (status),
    INDEX idx_empresa (empresa_id),
    INDEX idx_publicar_portal (publicar_portal),
    INDEX idx_status_publicar (status, publicar_portal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. LANDING PAGES DE VAGAS (Editáveis)
-- ============================================
CREATE TABLE IF NOT EXISTS vagas_landing_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    titulo_pagina VARCHAR(255) NULL COMMENT 'Título da página (SEO)',
    meta_descricao TEXT NULL COMMENT 'Meta description (SEO)',
    logo_empresa VARCHAR(500) NULL COMMENT 'Caminho do logo',
    imagem_hero VARCHAR(500) NULL COMMENT 'Imagem principal/banner',
    cor_primaria VARCHAR(7) DEFAULT '#009ef7' COMMENT 'Cor primária (hex)',
    cor_secundaria VARCHAR(7) DEFAULT '#f1416c' COMMENT 'Cor secundária (hex)',
    layout ENUM('padrao', 'moderno', 'minimalista', 'criativo') DEFAULT 'padrao',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_vaga (vaga_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Componentes da landing page (ordem editável)
CREATE TABLE IF NOT EXISTS vagas_landing_page_componentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_page_id INT NOT NULL,
    tipo_componente ENUM('hero', 'sobre_vaga', 'requisitos', 'beneficios', 'processo_seletivo', 'depoimentos', 'cta', 'formulario', 'custom') NOT NULL,
    titulo VARCHAR(255) NULL,
    conteudo TEXT NULL COMMENT 'Conteúdo HTML ou texto',
    imagem VARCHAR(500) NULL,
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    visivel BOOLEAN DEFAULT TRUE,
    configuracao JSON NULL COMMENT 'Configurações específicas do componente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landing_page_id) REFERENCES vagas_landing_pages(id) ON DELETE CASCADE,
    INDEX idx_landing_page (landing_page_id),
    INDEX idx_ordem (ordem),
    INDEX idx_visivel (visivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CANDIDATOS
-- ============================================
CREATE TABLE IF NOT EXISTS candidatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL,
    data_nascimento DATE NULL,
    endereco TEXT NULL,
    cidade VARCHAR(100) NULL,
    estado VARCHAR(2) NULL,
    linkedin VARCHAR(255) NULL,
    portfolio VARCHAR(255) NULL,
    observacoes TEXT NULL,
    status ENUM('ativo', 'inativo', 'contratado', 'desistente') DEFAULT 'ativo',
    origem ENUM('portal', 'indicacao', 'linkedin', 'outro') DEFAULT 'portal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_origem (origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CANDIDATURAS
-- ============================================
CREATE TABLE IF NOT EXISTS candidaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    candidato_id INT NOT NULL,
    status ENUM('nova', 'triagem', 'entrevista', 'avaliacao', 'aprovada', 'reprovada', 'desistente') DEFAULT 'nova',
    etapa_atual_id INT NULL COMMENT 'ID da etapa atual do processo',
    coluna_kanban VARCHAR(50) NULL COMMENT 'Coluna atual no Kanban',
    prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
    nota_geral DECIMAL(3,1) NULL COMMENT 'Nota geral do candidato (0-10)',
    observacoes TEXT NULL,
    recrutador_responsavel INT NULL,
    token_acompanhamento VARCHAR(100) UNIQUE NULL COMMENT 'Token único para acompanhamento sem login',
    data_candidatura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aprovacao DATE NULL,
    data_reprovacao DATE NULL,
    motivo_reprovacao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    FOREIGN KEY (recrutador_responsavel) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_vaga (vaga_id),
    INDEX idx_candidato (candidato_id),
    INDEX idx_status (status),
    INDEX idx_recrutador (recrutador_responsavel),
    INDEX idx_coluna_kanban (coluna_kanban),
    INDEX idx_token_acompanhamento (token_acompanhamento),
    UNIQUE KEY uk_vaga_candidato (vaga_id, candidato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. ANEXOS DE CANDIDATURAS
-- ============================================
CREATE TABLE IF NOT EXISTS candidaturas_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    tipo ENUM('curriculo', 'carta_apresentacao', 'portfolio', 'outro') DEFAULT 'curriculo',
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NULL,
    tamanho_bytes INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ETAPAS DO PROCESSO SELETIVO (Configuráveis)
-- ============================================
CREATE TABLE IF NOT EXISTS processo_seletivo_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NULL COMMENT 'NULL = etapa padrão para todas as vagas',
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL COMMENT 'Identificador único da etapa',
    tipo ENUM('triagem', 'entrevista_rh', 'entrevista_gestor', 'entrevista_tecnica', 'entrevista_diretoria', 'teste_tecnico', 'formulario_cultura', 'dinamica_grupo', 'aprovacao', 'outro') NOT NULL,
    ordem INT DEFAULT 0,
    obrigatoria BOOLEAN DEFAULT TRUE,
    permite_pular BOOLEAN DEFAULT FALSE,
    tempo_medio_minutos INT NULL COMMENT 'Tempo médio estimado',
    descricao TEXT NULL,
    cor_kanban VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Cor da coluna no Kanban',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    INDEX idx_vaga (vaga_id),
    INDEX idx_codigo (codigo),
    INDEX idx_ordem (ordem),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relacionamento Vaga ↔ Etapas (jornada configurável por vaga)
CREATE TABLE IF NOT EXISTS vagas_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    etapa_id INT NOT NULL,
    ordem INT DEFAULT 0 COMMENT 'Ordem específica para esta vaga',
    obrigatoria BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_vaga_etapa (vaga_id, etapa_id),
    INDEX idx_vaga (vaga_id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. PROGRESSO DO CANDIDATO POR ETAPA
-- ============================================
CREATE TABLE IF NOT EXISTS candidaturas_etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    etapa_id INT NOT NULL,
    status ENUM('pendente', 'em_andamento', 'concluida', 'reprovada', 'pulada') DEFAULT 'pendente',
    data_inicio DATETIME NULL,
    data_conclusao DATETIME NULL,
    avaliador_id INT NULL COMMENT 'Usuário que avaliou',
    nota DECIMAL(3,1) NULL COMMENT 'Nota da etapa (0-10)',
    feedback TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id),
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_status (status),
    UNIQUE KEY uk_candidatura_etapa (candidatura_id, etapa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. ENTREVISTAS
-- ============================================
CREATE TABLE IF NOT EXISTS entrevistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    etapa_id INT NULL,
    tipo ENUM('telefone', 'video', 'presencial', 'grupo') DEFAULT 'presencial',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    entrevistador_id INT NOT NULL,
    data_agendada DATETIME NOT NULL,
    duracao_minutos INT DEFAULT 60,
    link_videoconferencia VARCHAR(500) NULL,
    localizacao VARCHAR(255) NULL,
    status ENUM('agendada', 'realizada', 'cancelada', 'reagendada', 'nao_compareceu') DEFAULT 'agendada',
    data_realizacao DATETIME NULL,
    avaliacao_entrevistador TEXT NULL,
    nota_entrevistador DECIMAL(3,1) NULL,
    feedback_candidato TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id) ON DELETE SET NULL,
    FOREIGN KEY (entrevistador_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_entrevistador (entrevistador_id),
    INDEX idx_data_agendada (data_agendada),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. FORMULÁRIOS DE CULTURA
-- ============================================
CREATE TABLE IF NOT EXISTS formularios_cultura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    etapa_id INT NULL COMMENT 'Etapa onde será aplicado',
    ativo BOOLEAN DEFAULT TRUE,
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos dos formulários de cultura
CREATE TABLE IF NOT EXISTS formularios_cultura_campos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    tipo_campo ENUM('text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'escala') NOT NULL,
    label VARCHAR(200) NOT NULL,
    placeholder VARCHAR(200) NULL,
    obrigatorio BOOLEAN DEFAULT FALSE,
    valor_padrao TEXT NULL,
    opcoes JSON NULL COMMENT 'Para select/radio: array de opções',
    escala_min INT NULL COMMENT 'Para tipo escala',
    escala_max INT NULL COMMENT 'Para tipo escala',
    escala_label_min VARCHAR(50) NULL,
    escala_label_max VARCHAR(50) NULL,
    peso DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Peso na pontuação final',
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (formulario_id) REFERENCES formularios_cultura(id) ON DELETE CASCADE,
    INDEX idx_formulario (formulario_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respostas dos formulários de cultura
CREATE TABLE IF NOT EXISTS formularios_cultura_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    formulario_id INT NOT NULL,
    campo_id INT NOT NULL,
    resposta TEXT NOT NULL,
    pontuacao DECIMAL(5,2) NULL COMMENT 'Pontuação calculada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (formulario_id) REFERENCES formularios_cultura(id),
    FOREIGN KEY (campo_id) REFERENCES formularios_cultura_campos(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_formulario (formulario_id),
    UNIQUE KEY uk_candidatura_campo (candidatura_id, campo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. KANBAN - COLUNAS CONFIGURÁVEIS
-- ============================================
CREATE TABLE IF NOT EXISTS kanban_colunas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único',
    cor VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Cor da coluna (hex)',
    icone VARCHAR(50) NULL COMMENT 'Ícone (classe CSS)',
    ordem INT DEFAULT 0,
    limite_cards INT NULL COMMENT 'Limite de cards na coluna (NULL = sem limite)',
    regras_transicao JSON NULL COMMENT 'De quais colunas pode receber cards',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ordem (ordem),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. AUTOMAÇÕES DO KANBAN
-- ============================================
CREATE TABLE IF NOT EXISTS kanban_automatizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coluna_id INT NULL COMMENT 'NULL = automação global',
    etapa_id INT NULL COMMENT 'NULL = automação por coluna',
    nome VARCHAR(255) NOT NULL,
    tipo ENUM('email_candidato', 'email_recrutador', 'email_gestor', 'push_candidato', 'push_recrutador', 'notificacao_sistema', 'criar_tarefa', 'criar_colaborador', 'enviar_rejeicao', 'enviar_aprovacao', 'agendar_entrevista', 'calcular_nota', 'mover_automaticamente', 'adicionar_banco_talentos', 'fechar_vaga', 'lembrete', 'relatorio') NOT NULL,
    condicoes JSON NULL COMMENT 'Condições para executar (ex: dias_sem_atualizacao, nota_minima, etc)',
    configuracao JSON NULL COMMENT 'Configurações específicas (templates, destinatários, etc)',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (coluna_id) REFERENCES kanban_colunas(id) ON DELETE CASCADE,
    FOREIGN KEY (etapa_id) REFERENCES processo_seletivo_etapas(id) ON DELETE CASCADE,
    INDEX idx_coluna (coluna_id),
    INDEX idx_etapa (etapa_id),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. ONBOARDING
-- ============================================
CREATE TABLE IF NOT EXISTS onboarding (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    colaborador_id INT NULL COMMENT 'Criado após contratação',
    status ENUM('contratado', 'documentacao', 'treinamento', 'integracao', 'acompanhamento', 'concluido') DEFAULT 'contratado',
    coluna_kanban VARCHAR(50) NULL COMMENT 'Coluna atual no Kanban',
    data_inicio DATE NOT NULL,
    data_previsao_conclusao DATE NULL,
    data_conclusao DATE NULL,
    responsavel_id INT NOT NULL COMMENT 'RH responsável',
    mentor_id INT NULL COMMENT 'Colaborador mentor/buddy',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id),
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
    FOREIGN KEY (mentor_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_coluna_kanban (coluna_kanban)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarefas do onboarding
CREATE TABLE IF NOT EXISTS onboarding_tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    etapa VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    tipo ENUM('documento', 'treinamento', 'reuniao', 'configuracao', 'outro') NOT NULL,
    status ENUM('pendente', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'pendente',
    responsavel_id INT NULL COMMENT 'Quem deve executar',
    data_vencimento DATE NULL,
    data_conclusao DATETIME NULL,
    anexos JSON NULL COMMENT 'Array de caminhos de arquivos',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES onboarding(id) ON DELETE CASCADE,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_onboarding (onboarding_id),
    INDEX idx_status (status),
    INDEX idx_etapa (etapa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. HISTÓRICO E AUDITORIA
-- ============================================
CREATE TABLE IF NOT EXISTS candidaturas_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    usuario_id INT NULL,
    acao ENUM('criada', 'status_alterado', 'etapa_concluida', 'entrevista_agendada', 'entrevista_realizada', 'aprovada', 'reprovada', 'comentario', 'moved_kanban') NOT NULL,
    campo_alterado VARCHAR(100) NULL,
    valor_anterior TEXT NULL,
    valor_novo TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários em candidaturas
CREATE TABLE IF NOT EXISTS candidaturas_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidatura_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    tipo ENUM('comentario', 'feedback', 'observacao') DEFAULT 'comentario',
    anexos JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_candidatura (candidatura_id),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. INSERIR ETAPAS PADRÃO
-- ============================================
INSERT INTO processo_seletivo_etapas (nome, codigo, tipo, ordem, obrigatoria, descricao, cor_kanban, ativo) VALUES
('Novos Candidatos', 'novos_candidatos', 'triagem', 1, TRUE, 'Candidatos que acabaram de se candidatar', '#009ef7', TRUE),
('Triagem', 'triagem', 'triagem', 2, TRUE, 'Análise inicial de currículo e requisitos', '#ffc700', TRUE),
('Entrevista RH', 'entrevista_rh', 'entrevista_rh', 3, FALSE, 'Entrevista com o RH', '#7239ea', TRUE),
('Entrevista Técnica', 'entrevista_tecnica', 'entrevista_tecnica', 4, FALSE, 'Avaliação técnica', '#50cd89', TRUE),
('Entrevista Gestor', 'entrevista_gestor', 'entrevista_gestor', 5, FALSE, 'Entrevista com o gestor do setor', '#f1416c', TRUE),
('Avaliação', 'avaliacao', 'aprovacao', 6, TRUE, 'Avaliação final e decisão', '#ff9800', TRUE),
('Aprovados', 'aprovados', 'aprovacao', 7, TRUE, 'Candidatos aprovados para contratação', '#50cd89', TRUE),
('Reprovados', 'reprovados', 'outro', 8, TRUE, 'Candidatos reprovados', '#6c757d', TRUE);

-- ============================================
-- 15. INSERIR COLUNAS PADRÃO DO KANBAN
-- ============================================
INSERT INTO kanban_colunas (nome, codigo, cor, icone, ordem, ativo) VALUES
('Novos Candidatos', 'novos_candidatos', '#009ef7', 'profile-user', 1, TRUE),
('Em Análise', 'em_analise', '#ffc700', 'notepad', 2, TRUE),
('Entrevistas', 'entrevistas', '#7239ea', 'message-text', 3, TRUE),
('Avaliação', 'avaliacao', '#ff9800', 'chart-simple', 4, TRUE),
('Aprovados', 'aprovados', '#50cd89', 'check-circle', 5, TRUE),
('Reprovados', 'reprovados', '#6c757d', 'cross-circle', 6, TRUE);

-- ============================================
-- 16. INSERIR AUTOMAÇÕES PADRÃO
-- ============================================
-- Automação: Email de confirmação ao candidato (coluna: novos_candidatos)
INSERT INTO kanban_automatizacoes (coluna_id, nome, tipo, condicoes, configuracao, ativo) VALUES
((SELECT id FROM kanban_colunas WHERE codigo = 'novos_candidatos'), 'Email de Confirmação ao Candidato', 'email_candidato', '{"ao_entrar_coluna": true}', '{"template": "confirmacao_candidatura", "assunto": "Recebemos sua candidatura!"}', TRUE),
((SELECT id FROM kanban_colunas WHERE codigo = 'novos_candidatos'), 'Notificar Recrutador', 'notificacao_sistema', '{"ao_entrar_coluna": true}', '{"destinatarios": ["recrutador_responsavel"]}', TRUE),
((SELECT id FROM kanban_colunas WHERE codigo = 'aprovados'), 'Criar Processo de Onboarding', 'criar_tarefa', '{"ao_entrar_coluna": true}', '{"tipo": "onboarding"}', TRUE),
((SELECT id FROM kanban_colunas WHERE codigo = 'aprovados'), 'Email de Aprovação', 'enviar_aprovacao', '{"ao_entrar_coluna": true}', '{"template": "aprovacao"}', TRUE),
((SELECT id FROM kanban_colunas WHERE codigo = 'reprovados'), 'Email de Rejeição', 'enviar_rejeicao', '{"ao_entrar_coluna": true}', '{"template": "rejeicao"}', TRUE);

-- ============================================
-- 17. CONFIGURAÇÃO DO PORTAL PÚBLICO DE VAGAS
-- ============================================
CREATE TABLE IF NOT EXISTS portal_vagas_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo_pagina VARCHAR(255) DEFAULT 'Trabalhe Conosco',
    descricao_pagina TEXT NULL,
    cor_primaria VARCHAR(7) DEFAULT '#009ef7',
    cor_secundaria VARCHAR(7) DEFAULT '#50cd89',
    logo_url VARCHAR(500) NULL,
    imagem_hero_url VARCHAR(500) NULL,
    texto_hero TEXT NULL,
    texto_cta VARCHAR(100) DEFAULT 'Ver Vagas',
    mostrar_filtros BOOLEAN DEFAULT TRUE,
    itens_por_pagina INT DEFAULT 12,
    ordem_exibicao VARCHAR(50) DEFAULT 'data_criacao',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================

