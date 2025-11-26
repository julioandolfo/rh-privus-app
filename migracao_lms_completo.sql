-- =====================================================
-- MIGRAÇÃO COMPLETA DO SISTEMA LMS - ESCOLA PRIVUS
-- =====================================================
-- Execute este script no banco de dados para criar toda a estrutura do LMS

-- =====================================================
-- 1. TABELAS PRINCIPAIS DO LMS
-- =====================================================

-- Tabela de categorias de cursos
CREATE TABLE IF NOT EXISTS categorias_cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    icone VARCHAR(100) NULL,
    cor VARCHAR(20) NULL DEFAULT '#009ef7',
    ordem INT DEFAULT 0,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de cursos
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL,
    setor_id INT NULL,
    cargo_id INT NULL,
    categoria_id INT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    imagem_capa VARCHAR(255) NULL,
    duracao_estimada INT NULL COMMENT 'Duração estimada em minutos',
    nivel_dificuldade ENUM('iniciante', 'intermediario', 'avancado') DEFAULT 'iniciante',
    status ENUM('rascunho', 'publicado', 'arquivado') DEFAULT 'rascunho',
    data_inicio DATE NULL,
    data_fim DATE NULL,
    requisitos JSON NULL COMMENT 'Array de IDs de cursos pré-requisitos',
    pontos_recompensa INT DEFAULT 0,
    
    -- Campos de curso obrigatório
    obrigatorio BOOLEAN DEFAULT FALSE,
    prazo_dias INT NULL COMMENT 'Prazo em dias para conclusão',
    prazo_tipo ENUM('data_fixa', 'dias_apos_atribuicao', 'dias_apos_admissao') DEFAULT 'dias_apos_atribuicao',
    data_limite DATE NULL COMMENT 'Data limite fixa',
    alertar_email BOOLEAN DEFAULT TRUE,
    alertar_push BOOLEAN DEFAULT TRUE,
    alertar_sistema BOOLEAN DEFAULT TRUE,
    dias_antes_alertar JSON NULL COMMENT 'Array de dias antes do prazo [7, 3, 1]',
    alertar_apos_vencimento BOOLEAN DEFAULT TRUE,
    frequencia_alertas_vencido ENUM('diario', 'semanal', 'mensal') DEFAULT 'semanal',
    
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE SET NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias_cursos(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_obrigatorio (obrigatorio),
    INDEX idx_categoria (categoria_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de aulas
CREATE TABLE IF NOT EXISTS aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    ordem INT DEFAULT 0,
    tipo_conteudo ENUM('video_youtube', 'video_upload', 'pdf', 'texto') NOT NULL,
    url_youtube VARCHAR(255) NULL COMMENT 'ID ou URL do vídeo YouTube',
    arquivo_video VARCHAR(255) NULL COMMENT 'Path do arquivo de vídeo',
    arquivo_pdf VARCHAR(255) NULL COMMENT 'Path do arquivo PDF',
    conteudo_texto LONGTEXT NULL COMMENT 'HTML/JSON para conteúdo de texto',
    duracao_minutos INT NULL,
    duracao_segundos INT NULL COMMENT 'Duração exata em segundos',
    status ENUM('rascunho', 'publicado') DEFAULT 'rascunho',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_curso (curso_id),
    INDEX idx_ordem (ordem),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de campos personalizados para aulas de texto
CREATE TABLE IF NOT EXISTS campos_personalizados_aula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aula_id INT NOT NULL,
    tipo_campo ENUM('texto', 'textarea', 'imagem', 'video', 'arquivo', 'checkbox', 'radio', 'select', 'tabela', 'lista') NOT NULL,
    nome_campo VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    placeholder VARCHAR(255) NULL,
    obrigatorio BOOLEAN DEFAULT FALSE,
    opcoes JSON NULL COMMENT 'Opções para select/radio/checkbox',
    ordem INT DEFAULT 0,
    configuracao JSON NULL COMMENT 'Configurações adicionais do campo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    INDEX idx_aula (aula_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de progresso do colaborador
CREATE TABLE IF NOT EXISTS progresso_colaborador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    curso_id INT NOT NULL,
    aula_id INT NOT NULL,
    status ENUM('nao_iniciado', 'em_andamento', 'concluido', 'pausado') DEFAULT 'nao_iniciado',
    percentual_conclusao DECIMAL(5,2) DEFAULT 0.00,
    tempo_assistido INT DEFAULT 0 COMMENT 'Tempo assistido em segundos',
    ultima_posicao INT DEFAULT 0 COMMENT 'Última posição do vídeo em segundos',
    data_inicio DATETIME NULL,
    data_conclusao DATETIME NULL,
    data_ultimo_acesso DATETIME NULL,
    nota_final DECIMAL(5,2) NULL,
    tentativas INT DEFAULT 0,
    
    -- Campos de segurança
    tempo_minimo_requerido INT NULL COMMENT 'Tempo mínimo em segundos',
    percentual_minimo_requerido DECIMAL(5,2) DEFAULT 100.00,
    tempo_total_assistido INT DEFAULT 0 COMMENT 'Tempo total realmente assistido',
    tempo_total_conteudo INT NULL COMMENT 'Duração total do conteúdo',
    data_inicio_real DATETIME NULL,
    tentativas_conclusao INT DEFAULT 0,
    bloqueado_por_fraude BOOLEAN DEFAULT FALSE,
    motivo_bloqueio TEXT NULL,
    hash_validacao VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_progresso (colaborador_id, curso_id, aula_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_curso (curso_id),
    INDEX idx_status (status),
    INDEX idx_bloqueado (bloqueado_por_fraude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de avaliações
CREATE TABLE IF NOT EXISTS avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    aula_id INT NULL COMMENT 'NULL = avaliação do curso completo',
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    tipo ENUM('quiz', 'questionario', 'projeto') DEFAULT 'quiz',
    pontuacao_minima DECIMAL(5,2) DEFAULT 70.00 COMMENT 'Percentual mínimo para aprovação',
    tentativas_maximas INT DEFAULT 3,
    configuracao JSON NOT NULL COMMENT 'Questões e respostas em JSON',
    obrigatoria BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE SET NULL,
    INDEX idx_curso (curso_id),
    INDEX idx_aula (aula_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de respostas de avaliação
CREATE TABLE IF NOT EXISTS respostas_avaliacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    respostas JSON NOT NULL COMMENT 'Respostas do colaborador',
    pontuacao DECIMAL(5,2) DEFAULT 0.00,
    percentual_acerto DECIMAL(5,2) DEFAULT 0.00,
    tentativa_numero INT DEFAULT 1,
    status ENUM('aprovado', 'reprovado', 'pendente') DEFAULT 'pendente',
    data_resposta DATETIME NOT NULL,
    tempo_gasto INT NULL COMMENT 'Tempo gasto em segundos',
    
    FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_avaliacao (avaliacao_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de certificados
CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    curso_id INT NOT NULL,
    codigo_unico VARCHAR(100) UNIQUE NOT NULL,
    data_emissao DATE NOT NULL,
    data_validade DATE NULL,
    arquivo_pdf VARCHAR(255) NULL,
    hash_verificacao VARCHAR(255) NOT NULL,
    status ENUM('ativo', 'expirado', 'revogado') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_curso (curso_id),
    INDEX idx_codigo (codigo_unico),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de badges/conquistas
CREATE TABLE IF NOT EXISTS badges_conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    icone VARCHAR(100) NULL,
    cor VARCHAR(20) DEFAULT '#ffc700',
    tipo ENUM('curso_completo', 'sequencia', 'desempenho', 'personalizado') NOT NULL,
    regras JSON NULL COMMENT 'Condições para conquista',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de badges do colaborador
CREATE TABLE IF NOT EXISTS colaborador_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    badge_id INT NOT NULL,
    curso_id INT NULL COMMENT 'Curso relacionado se aplicável',
    data_conquista DATETIME NOT NULL,
    notificacao_enviada BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges_conquistas(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL,
    UNIQUE KEY unique_badge_colaborador (colaborador_id, badge_id, curso_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_badge (badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de comentários em aulas
CREATE TABLE IF NOT EXISTS comentarios_aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aula_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    comentario TEXT NOT NULL,
    resposta_para_id INT NULL COMMENT 'ID do comentário respondido',
    status ENUM('ativo', 'oculto') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (resposta_para_id) REFERENCES comentarios_aulas(id) ON DELETE SET NULL,
    INDEX idx_aula (aula_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de favoritos
CREATE TABLE IF NOT EXISTS favoritos_cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    curso_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorito (colaborador_id, curso_id),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. TABELAS DE SEGURANÇA E ANTI-FRAUDE
-- =====================================================

-- Tabela de sessões de aula
CREATE TABLE IF NOT EXISTS lms_sessoes_aula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    progresso_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    aula_id INT NOT NULL,
    curso_id INT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NULL,
    tempo_total_segundos INT DEFAULT 0,
    tempo_assistido_segundos INT DEFAULT 0 COMMENT 'Tempo realmente assistido',
    posicao_inicial INT DEFAULT 0,
    posicao_final INT DEFAULT 0,
    percentual_assistido DECIMAL(5,2) DEFAULT 0.00,
    eventos JSON NULL COMMENT 'Array de eventos',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    dispositivo ENUM('mobile', 'desktop', 'tablet') NULL,
    navegador VARCHAR(100) NULL,
    sessao_ativa BOOLEAN DEFAULT TRUE,
    suspeita_fraude BOOLEAN DEFAULT FALSE,
    motivo_suspeita TEXT NULL,
    
    FOREIGN KEY (progresso_id) REFERENCES progresso_colaborador(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_progresso (progresso_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_suspeita (suspeita_fraude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de eventos do player
CREATE TABLE IF NOT EXISTS lms_eventos_player (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessao_id INT NOT NULL,
    progresso_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    aula_id INT NOT NULL,
    tipo_evento ENUM('play', 'pause', 'seek', 'ended', 'timeupdate', 'focus', 'blur', 'visibilitychange', 'interaction') NOT NULL,
    posicao_video INT NOT NULL COMMENT 'Posição em segundos',
    duracao_total INT NULL,
    timestamp_evento DATETIME NOT NULL,
    tempo_decorrido INT NULL COMMENT 'Tempo desde início da sessão',
    dados_adicionais JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    FOREIGN KEY (sessao_id) REFERENCES lms_sessoes_aula(id) ON DELETE CASCADE,
    FOREIGN KEY (progresso_id) REFERENCES progresso_colaborador(id) ON DELETE CASCADE,
    INDEX idx_sessao (sessao_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_timestamp (timestamp_evento),
    INDEX idx_colaborador_aula (colaborador_id, aula_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de auditoria de conclusão
CREATE TABLE IF NOT EXISTS lms_auditoria_conclusao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    progresso_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    aula_id INT NOT NULL,
    curso_id INT NOT NULL,
    acao ENUM('tentativa_conclusao', 'conclusao_aprovada', 'conclusao_rejeitada', 'bloqueio_fraude', 'aprovacao_manual') NOT NULL,
    motivo TEXT NULL,
    dados_validacao JSON NULL,
    resultado_validacao JSON NULL,
    score_risco INT NULL COMMENT 'Score de risco calculado (0-100)',
    aprovado_por_usuario_id INT NULL,
    data_acao DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    FOREIGN KEY (progresso_id) REFERENCES progresso_colaborador(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_acao (acao),
    INDEX idx_data (data_acao),
    INDEX idx_score_risco (score_risco)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações de segurança
CREATE TABLE IF NOT EXISTS lms_configuracoes_seguranca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NULL COMMENT 'NULL = configuração global',
    aula_id INT NULL COMMENT 'NULL = configuração do curso',
    tipo_conteudo ENUM('video_youtube', 'video_upload', 'pdf', 'texto') NOT NULL,
    
    -- Validações de tempo
    tempo_minimo_percentual DECIMAL(5,2) DEFAULT 80.00 COMMENT 'Mínimo % do conteúdo',
    tempo_minimo_segundos INT NULL COMMENT 'Tempo mínimo absoluto',
    validar_tempo_real BOOLEAN DEFAULT TRUE,
    tolerancia_velocidade DECIMAL(3,2) DEFAULT 2.00 COMMENT 'Velocidade máxima (2x = 2.00)',
    
    -- Validações de interação
    requer_interacao BOOLEAN DEFAULT FALSE,
    minimo_interacoes INT DEFAULT 0,
    validar_janela_ativa BOOLEAN DEFAULT TRUE,
    validar_foco BOOLEAN DEFAULT TRUE,
    
    -- Validações de sequência
    bloquear_pular BOOLEAN DEFAULT TRUE,
    requer_sequencial BOOLEAN DEFAULT TRUE,
    permitir_revisao BOOLEAN DEFAULT TRUE,
    
    -- Validações de avaliação
    requer_avaliacao BOOLEAN DEFAULT FALSE,
    nota_minima DECIMAL(5,2) NULL,
    
    -- Detecção de fraude
    detectar_velocidade_anormal BOOLEAN DEFAULT TRUE,
    detectar_multiplas_abas BOOLEAN DEFAULT TRUE,
    detectar_automatizacao BOOLEAN DEFAULT TRUE,
    alertar_suspeita BOOLEAN DEFAULT TRUE,
    
    -- Ações em caso de fraude
    acao_fraude ENUM('bloquear', 'alertar', 'revalidar', 'permitir') DEFAULT 'alertar',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_config (curso_id, aula_id, tipo_conteudo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TABELAS DE CURSOS OBRIGATÓRIOS E ALERTAS
-- =====================================================

-- Tabela de cursos obrigatórios atribuídos
CREATE TABLE IF NOT EXISTS cursos_obrigatorios_colaboradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    atribuido_por_usuario_id INT NULL,
    data_atribuicao DATE NOT NULL,
    data_limite DATE NOT NULL COMMENT 'Data limite calculada',
    status ENUM('pendente', 'em_andamento', 'concluido', 'vencido', 'cancelado') DEFAULT 'pendente',
    data_inicio DATE NULL,
    data_conclusao DATE NULL,
    ultimo_alerta_enviado DATE NULL,
    tentativas_alertas INT DEFAULT 0,
    notificacao_inicial_enviada BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (atribuido_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_curso_colaborador (curso_id, colaborador_id),
    INDEX idx_data_limite (data_limite),
    INDEX idx_status (status),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de regras automáticas de atribuição
CREATE TABLE IF NOT EXISTS cursos_obrigatorios_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tipo_regra ENUM('novo_colaborador', 'cargo', 'setor', 'promocao', 'mudanca_setor', 'mudanca_cargo') NOT NULL,
    valor_regra VARCHAR(255) NULL COMMENT 'ID do cargo/setor ou NULL para regras gerais',
    prazo_dias INT NOT NULL COMMENT 'Prazo em dias após o evento',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_tipo_regra (tipo_regra),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de alertas agendados
CREATE TABLE IF NOT EXISTS alertas_cursos_obrigatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_obrigatorio_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    curso_id INT NOT NULL,
    tipo_alerta ENUM('inicial', 'lembrete', 'vencimento_proximo', 'vencido', 'escalacao') NOT NULL,
    dias_restantes INT NULL COMMENT 'Dias restantes (negativo se vencido)',
    canal ENUM('email', 'push', 'sistema') NOT NULL,
    enviado BOOLEAN DEFAULT FALSE,
    data_envio DATETIME NULL,
    data_agendada DATETIME NOT NULL,
    tentativas INT DEFAULT 0,
    erro TEXT NULL,
    
    FOREIGN KEY (curso_obrigatorio_id) REFERENCES cursos_obrigatorios_colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_enviado (enviado),
    INDEX idx_data_agendada (data_agendada),
    INDEX idx_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. INSERIR DADOS INICIAIS
-- =====================================================

-- Inserir categorias padrão
INSERT INTO categorias_cursos (nome, descricao, icone, cor, ordem) VALUES
('Cultura Organizacional', 'Cursos sobre valores, missão e cultura da empresa', 'profile-circle', '#009ef7', 1),
('Segurança do Trabalho', 'Cursos sobre segurança e prevenção de acidentes', 'shield-check', '#f1416c', 2),
('Treinamento Técnico', 'Cursos técnicos específicos da área', 'setting-2', '#7239ea', 3),
('Desenvolvimento Pessoal', 'Cursos de desenvolvimento e habilidades pessoais', 'profile-user', '#ffc700', 4),
('Compliance', 'Cursos sobre normas e regulamentações', 'file-up', '#50cd89', 5)
ON DUPLICATE KEY UPDATE nome=VALUES(nome);

-- Inserir configurações de segurança padrão
INSERT INTO lms_configuracoes_seguranca (curso_id, aula_id, tipo_conteudo, tempo_minimo_percentual, validar_tempo_real, validar_janela_ativa, bloquear_pular) VALUES
(NULL, NULL, 'video_youtube', 80.00, TRUE, TRUE, TRUE),
(NULL, NULL, 'video_upload', 80.00, TRUE, TRUE, TRUE),
(NULL, NULL, 'pdf', 60.00, TRUE, TRUE, TRUE),
(NULL, NULL, 'texto', 70.00, FALSE, FALSE, FALSE)
ON DUPLICATE KEY UPDATE tempo_minimo_percentual=VALUES(tempo_minimo_percentual);

-- =====================================================
-- FIM DA MIGRAÇÃO
-- =====================================================

