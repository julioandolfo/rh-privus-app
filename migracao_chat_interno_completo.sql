-- ============================================
-- MIGRAÇÃO COMPLETA: Sistema de Chat Interno
-- Sistema de comunicação entre colaboradores e RH
-- Inclui: SLA, Métricas, Status Detalhados, Mensagens de Voz
-- ============================================

-- ============================================
-- 1. CHAT_CONVERSAS (Melhorado)
-- ============================================
CREATE TABLE IF NOT EXISTS chat_conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL COMMENT 'Colaborador que iniciou a conversa',
    titulo VARCHAR(255) NULL COMMENT 'Título da conversa (gerado automaticamente ou manual)',
    status ENUM('nova', 'aguardando_triagem', 'em_atendimento', 'aguardando_colaborador', 'aguardando_resposta', 'pendente_informacao', 'resolvida', 'fechada', 'arquivada') DEFAULT 'nova',
    prioridade ENUM('baixa', 'normal', 'alta', 'urgente') DEFAULT 'normal',
    categoria_id INT NULL COMMENT 'Categoria da conversa',
    subcategoria VARCHAR(100) NULL,
    tags JSON NULL COMMENT 'Tags para organização',
    
    -- Atribuição
    atribuido_para_usuario_id INT NULL COMMENT 'RH responsável pela conversa',
    
    -- Escalonamento
    escalado_para_usuario_id INT NULL COMMENT 'Escalado para supervisor',
    escalado_por_usuario_id INT NULL COMMENT 'Quem escalou',
    escalado_at TIMESTAMP NULL,
    motivo_escalacao TEXT NULL,
    
    -- Mensagens
    ultima_mensagem_at TIMESTAMP NULL COMMENT 'Data/hora da última mensagem',
    ultima_mensagem_por ENUM('colaborador', 'rh') NULL COMMENT 'Quem enviou a última mensagem',
    total_mensagens INT DEFAULT 0 COMMENT 'Contador de mensagens',
    total_mensagens_nao_lidas_colaborador INT DEFAULT 0 COMMENT 'Mensagens não lidas pelo colaborador',
    total_mensagens_nao_lidas_rh INT DEFAULT 0 COMMENT 'Mensagens não lidas pelo RH',
    
    -- Visualizações
    colaborador_visualizou_at TIMESTAMP NULL COMMENT 'Última vez que colaborador visualizou',
    rh_visualizou_at TIMESTAMP NULL COMMENT 'Última vez que RH visualizou',
    
    -- Métricas de Tempo
    tempo_primeira_resposta_segundos INT NULL COMMENT 'Tempo até primeira resposta do RH',
    tempo_resolucao_segundos INT NULL COMMENT 'Tempo total até fechamento',
    tempo_medio_resposta_segundos INT NULL COMMENT 'Média de tempo de resposta do RH',
    total_respostas_rh INT DEFAULT 0 COMMENT 'Contador de respostas do RH',
    
    -- SLA
    sla_primeira_resposta_vencimento TIMESTAMP NULL COMMENT 'Quando deve responder pela primeira vez',
    sla_resolucao_vencimento TIMESTAMP NULL COMMENT 'Quando deve resolver',
    sla_primeira_resposta_cumprido BOOLEAN NULL COMMENT 'SLA de primeira resposta foi cumprido',
    sla_resolucao_cumprido BOOLEAN NULL COMMENT 'SLA de resolução foi cumprido',
    sla_alerta_enviado BOOLEAN DEFAULT FALSE COMMENT 'Alerta de SLA próximo de vencer foi enviado',
    
    -- IA
    resumo_ia TEXT NULL COMMENT 'Resumo gerado pela IA',
    resumo_ia_gerado_at TIMESTAMP NULL COMMENT 'Data/hora do resumo gerado',
    
    -- Notas internas
    nota_interna TEXT NULL COMMENT 'Nota visível apenas para RH',
    
    -- Metadata
    metadata JSON NULL COMMENT 'Dados adicionais (ex: ocorrência criada, documentos anexados)',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fechada_at TIMESTAMP NULL COMMENT 'Data/hora de fechamento',
    fechada_por INT NULL COMMENT 'Usuário que fechou',
    
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (atribuido_para_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (escalado_para_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (escalado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (fechada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_atribuido (atribuido_para_usuario_id),
    INDEX idx_prioridade (prioridade),
    INDEX idx_ultima_mensagem (ultima_mensagem_at),
    INDEX idx_abertas (status, ultima_mensagem_at),
    INDEX idx_nao_lidas_colaborador (status, total_mensagens_nao_lidas_colaborador),
    INDEX idx_nao_lidas_rh (status, total_mensagens_nao_lidas_rh),
    INDEX idx_sla_vencimento (sla_primeira_resposta_vencimento, sla_resolucao_vencimento),
    INDEX idx_categoria (categoria_id),
    INDEX idx_escalado (escalado_para_usuario_id, escalado_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. CHAT_MENSAGENS (Melhorado com Voz)
-- ============================================
CREATE TABLE IF NOT EXISTS chat_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    enviado_por_usuario_id INT NULL COMMENT 'Usuário RH que enviou (NULL se foi colaborador)',
    enviado_por_colaborador_id INT NULL COMMENT 'Colaborador que enviou (NULL se foi RH)',
    tipo ENUM('texto', 'anexo', 'sistema', 'acao_rapida', 'voz') DEFAULT 'texto',
    mensagem TEXT NULL COMMENT 'Texto da mensagem',
    
    -- Anexos
    anexo_caminho VARCHAR(500) NULL COMMENT 'Caminho do arquivo anexado',
    anexo_nome_original VARCHAR(255) NULL COMMENT 'Nome original do arquivo',
    anexo_tipo_mime VARCHAR(100) NULL COMMENT 'Tipo MIME do arquivo',
    anexo_tamanho INT NULL COMMENT 'Tamanho em bytes',
    
    -- Mensagens de Voz
    voz_caminho VARCHAR(500) NULL COMMENT 'Caminho do arquivo de áudio',
    voz_duracao_segundos INT NULL COMMENT 'Duração do áudio em segundos',
    voz_transcricao TEXT NULL COMMENT 'Transcrição do áudio (opcional, via IA)',
    
    -- Ações rápidas
    acao_rapida_tipo VARCHAR(50) NULL COMMENT 'Tipo de ação rápida (ex: ocorrencia_criada)',
    acao_rapida_dados JSON NULL COMMENT 'Dados da ação rápida',
    
    -- Leitura
    lida_por_colaborador BOOLEAN DEFAULT FALSE COMMENT 'Colaborador leu a mensagem',
    lida_por_rh BOOLEAN DEFAULT FALSE COMMENT 'RH leu a mensagem',
    lida_por_colaborador_at TIMESTAMP NULL,
    lida_por_rh_at TIMESTAMP NULL,
    
    -- Edição/Deleção
    editada BOOLEAN DEFAULT FALSE COMMENT 'Mensagem foi editada',
    editada_at TIMESTAMP NULL,
    editada_por INT NULL,
    deletada BOOLEAN DEFAULT FALSE COMMENT 'Mensagem foi deletada (soft delete)',
    deletada_at TIMESTAMP NULL,
    deletada_por INT NULL,
    
    -- Reações (futuro)
    reacoes JSON NULL COMMENT 'Reações à mensagem (emoji, curtidas, etc)',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (enviado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (enviado_por_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    FOREIGN KEY (editada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (deletada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    INDEX idx_conversa (conversa_id),
    INDEX idx_enviado_por_usuario (enviado_por_usuario_id),
    INDEX idx_enviado_por_colaborador (enviado_por_colaborador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_created_at (created_at),
    INDEX idx_nao_lidas_colaborador (conversa_id, lida_por_colaborador, created_at),
    INDEX idx_nao_lidas_rh (conversa_id, lida_por_rh, created_at),
    INDEX idx_deletadas (conversa_id, deletada, created_at),
    INDEX idx_voz (tipo, voz_caminho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CHAT_PARTICIPANTES
-- ============================================
CREATE TABLE IF NOT EXISTS chat_participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'Usuário RH participando',
    adicionado_por INT NULL COMMENT 'Quem adicionou este participante',
    removido BOOLEAN DEFAULT FALSE COMMENT 'Participante foi removido',
    removido_at TIMESTAMP NULL,
    removido_por INT NULL,
    ultima_visualizacao TIMESTAMP NULL COMMENT 'Última vez que visualizou a conversa',
    notificacoes_ativas BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações desta conversa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (adicionado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (removido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_conversa_usuario_ativo (conversa_id, usuario_id, removido),
    INDEX idx_conversa (conversa_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_removidos (conversa_id, removido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CHAT_CATEGORIAS
-- ============================================
CREATE TABLE IF NOT EXISTS chat_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    cor VARCHAR(7) NULL COMMENT 'Cor hexadecimal para exibição',
    icone VARCHAR(50) NULL COMMENT 'Ícone para exibição',
    ativo BOOLEAN DEFAULT TRUE,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias padrão
INSERT INTO chat_categorias (nome, descricao, cor, icone, ordem) VALUES
('Solicitação', 'Solicitações gerais', '#009ef7', 'file-text', 1),
('Dúvida', 'Dúvidas e questionamentos', '#ffc700', 'question-mark', 2),
('Problema', 'Problemas e dificuldades', '#f1416c', 'alert-circle', 3),
('Férias', 'Solicitações de férias', '#50cd89', 'calendar', 4),
('Folha de Pagamento', 'Questões sobre folha', '#7239ea', 'wallet', 5),
('Benefícios', 'Questões sobre benefícios', '#ff9800', 'gift', 6),
('Documentos', 'Solicitação de documentos', '#181c32', 'file-up', 7),
('Outros', 'Outras categorias', '#a1a5b7', 'more', 99)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ============================================
-- 5. CHAT_SLA_CONFIG
-- ============================================
CREATE TABLE IF NOT EXISTS chat_sla_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL COMMENT 'NULL = configuração global',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da configuração',
    tempo_primeira_resposta_minutos INT NOT NULL DEFAULT 60 COMMENT 'Tempo para primeira resposta em minutos',
    tempo_resolucao_horas INT NOT NULL DEFAULT 24 COMMENT 'Tempo para resolução em horas',
    horario_inicio TIME NULL COMMENT 'Horário de início do atendimento',
    horario_fim TIME NULL COMMENT 'Horário de fim do atendimento',
    dias_semana JSON NULL COMMENT 'Dias da semana (1=segunda, 7=domingo)',
    aplicar_apenas_horario_comercial BOOLEAN DEFAULT TRUE COMMENT 'SLA só conta em horário comercial',
    alerta_antes_vencer_minutos INT DEFAULT 30 COMMENT 'Alerta X minutos antes de vencer',
    aplicar_por_prioridade BOOLEAN DEFAULT FALSE COMMENT 'Aplicar SLA diferente por prioridade',
    sla_prioridade_alta_minutos INT NULL COMMENT 'SLA para prioridade alta',
    sla_prioridade_urgente_minutos INT NULL COMMENT 'SLA para prioridade urgente',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuração padrão
INSERT INTO chat_sla_config (nome, tempo_primeira_resposta_minutos, tempo_resolucao_horas, horario_inicio, horario_fim, dias_semana, alerta_antes_vencer_minutos) VALUES
('Padrão', 60, 24, '08:00', '18:00', '[1,2,3,4,5]', 30)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Adicionar FK de categoria em chat_conversas
ALTER TABLE chat_conversas 
ADD FOREIGN KEY (categoria_id) REFERENCES chat_categorias(id) ON DELETE SET NULL;

-- ============================================
-- 6. CHAT_SLA_HISTORICO
-- ============================================
CREATE TABLE IF NOT EXISTS chat_sla_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    tipo_sla ENUM('primeira_resposta', 'resolucao') NOT NULL,
    tempo_limite_segundos INT NOT NULL COMMENT 'Tempo limite do SLA',
    tempo_realizado_segundos INT NULL COMMENT 'Tempo que levou',
    cumpriu BOOLEAN NULL COMMENT 'Se cumpriu o SLA',
    vencimento_previsto TIMESTAMP NOT NULL,
    vencimento_real TIMESTAMP NULL COMMENT 'Quando realmente venceu',
    alerta_enviado BOOLEAN DEFAULT FALSE,
    alerta_enviado_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    INDEX idx_conversa (conversa_id),
    INDEX idx_tipo (tipo_sla),
    INDEX idx_cumpriu (cumpriu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CHAT_CONFIGURACOES
-- ============================================
CREATE TABLE IF NOT EXISTS chat_configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NULL,
    tipo VARCHAR(50) DEFAULT 'string' COMMENT 'string, json, boolean, integer',
    descricao TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão
INSERT INTO chat_configuracoes (chave, valor, tipo, descricao) VALUES
('chat_ativo', 'true', 'boolean', 'Sistema de chat está ativo'),
('horario_atendimento_inicio', '08:00', 'string', 'Horário de início do atendimento'),
('horario_atendimento_fim', '18:00', 'string', 'Horário de fim do atendimento'),
('mensagem_automatica_fora_horario', 'Olá! Estamos fora do horário de atendimento. Retornaremos em breve.', 'string', 'Mensagem automática fora do horário'),
('notificacoes_push_ativas', 'true', 'boolean', 'Notificações push estão ativas'),
('notificacoes_sonoras_ativas', 'true', 'boolean', 'Efeitos sonoros estão ativos'),
('tempo_auto_fechamento_dias', '30', 'integer', 'Dias para fechar conversas inativas automaticamente'),
('chatgpt_api_key', '', 'string', 'API Key do ChatGPT'),
('chatgpt_modelo', 'gpt-4', 'string', 'Modelo do ChatGPT a usar'),
('chatgpt_ativo', 'false', 'boolean', 'Integração com ChatGPT está ativa'),
('chatgpt_temperatura', '0.7', 'string', 'Temperatura do modelo ChatGPT'),
('chatgpt_max_tokens', '500', 'integer', 'Máximo de tokens para resumo'),
('tempo_resposta_alerta_horas', '24', 'integer', 'Horas sem resposta para alertar'),
('max_tamanho_anexo_mb', '10', 'integer', 'Tamanho máximo de anexo em MB'),
('max_tamanho_voz_mb', '5', 'integer', 'Tamanho máximo de mensagem de voz em MB'),
('voz_transcricao_ativa', 'false', 'boolean', 'Transcrição automática de voz com IA'),
('voz_formatos_permitidos', '["mp3", "wav", "ogg", "m4a"]', 'json', 'Formatos de áudio permitidos'),
('auto_atribuicao_ativa', 'true', 'boolean', 'Atribuição automática de conversas'),
('distribuicao_tipo', 'round_robin', 'string', 'Tipo de distribuição (round_robin, menor_carga, especialidade)')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), descricao = VALUES(descricao);

-- ============================================
-- 8. CHAT_PREFERENCIAS_USUARIO
-- ============================================
CREATE TABLE IF NOT EXISTS chat_preferencias_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'Usuário RH',
    colaborador_id INT NULL COMMENT 'Colaborador',
    notificacoes_push BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações push',
    notificacoes_email BOOLEAN DEFAULT TRUE COMMENT 'Recebe notificações por email',
    notificacoes_sonoras BOOLEAN DEFAULT TRUE COMMENT 'Efeitos sonoros ativos',
    som_notificacao VARCHAR(50) DEFAULT 'padrao' COMMENT 'Som escolhido (padrao, suave, urgente, desligado)',
    status_online BOOLEAN DEFAULT FALSE COMMENT 'Status online (para RH)',
    status_mensagem VARCHAR(255) NULL COMMENT 'Mensagem de status (para RH)',
    auto_resposta TEXT NULL COMMENT 'Mensagem de auto-resposta (para RH)',
    auto_resposta_ativa BOOLEAN DEFAULT FALSE COMMENT 'Auto-resposta está ativa',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario (usuario_id),
    UNIQUE KEY uk_colaborador (colaborador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. CHAT_RESUMOS_IA
-- ============================================
CREATE TABLE IF NOT EXISTS chat_resumos_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    resumo TEXT NOT NULL COMMENT 'Resumo gerado pela IA',
    prompt_usado TEXT NULL COMMENT 'Prompt usado para gerar o resumo',
    modelo_usado VARCHAR(50) NULL COMMENT 'Modelo usado (ex: gpt-4)',
    tokens_usados INT NULL COMMENT 'Tokens consumidos',
    custo_estimado DECIMAL(10,6) NULL COMMENT 'Custo estimado em USD',
    gerado_por_usuario_id INT NULL COMMENT 'Usuário que solicitou o resumo',
    salvo BOOLEAN DEFAULT FALSE COMMENT 'Resumo foi salvo na conversa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (gerado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_conversa (conversa_id),
    INDEX idx_gerado_por (gerado_por_usuario_id),
    INDEX idx_salvo (conversa_id, salvo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. CHAT_HISTORICO_ACOES
-- ============================================
CREATE TABLE IF NOT EXISTS chat_historico_acoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL COMMENT 'Tipo de ação (atribuida, fechada, reaberta, escalada, etc)',
    realizado_por_usuario_id INT NULL COMMENT 'Usuário que realizou a ação',
    realizado_por_colaborador_id INT NULL COMMENT 'Colaborador que realizou a ação',
    dados_anteriores JSON NULL COMMENT 'Estado anterior',
    dados_novos JSON NULL COMMENT 'Novo estado',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES chat_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (realizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (realizado_por_colaborador_id) REFERENCES colaboradores(id) ON DELETE SET NULL,
    INDEX idx_conversa (conversa_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. CHAT_RESPOSTAS_RAPIDAS
-- ============================================
CREATE TABLE IF NOT EXISTS chat_respostas_rapidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL COMMENT 'NULL = resposta global',
    titulo VARCHAR(100) NOT NULL COMMENT 'Título da resposta rápida',
    mensagem TEXT NOT NULL COMMENT 'Texto da resposta',
    categoria_id INT NULL COMMENT 'Categoria relacionada',
    atalho VARCHAR(50) NULL COMMENT 'Atalho de teclado (ex: /ajuda)',
    uso_count INT DEFAULT 0 COMMENT 'Contador de uso',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES chat_categorias(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_ativo (ativo),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respostas rápidas padrão
INSERT INTO chat_respostas_rapidas (titulo, mensagem, atalho) VALUES
('Saudação', 'Olá! Como posso ajudá-lo hoje?', '/ola'),
('Agradecimento', 'Obrigado pelo contato! Fico à disposição para qualquer dúvida.', '/obrigado'),
('Solicitar Informações', 'Preciso de mais algumas informações para poder ajudá-lo melhor. Poderia me fornecer?', '/info'),
('Encaminhar', 'Vou encaminhar sua solicitação para o setor responsável. Retornaremos em breve.', '/encaminhar'),
('Resolvido', 'Fico feliz em saber que conseguimos resolver sua questão! Se precisar de mais alguma coisa, estou à disposição.', '/resolvido')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo);

-- ============================================
-- 12. CHAT_MENSAGENS_AUTOMATICAS
-- ============================================
CREATE TABLE IF NOT EXISTS chat_mensagens_automaticas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da mensagem automática',
    tipo ENUM('fora_horario', 'ausencia', 'feriado', 'personalizada') NOT NULL,
    mensagem TEXT NOT NULL COMMENT 'Texto da mensagem',
    condicoes JSON NULL COMMENT 'Condições para disparar (horário, dia, etc)',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensagens automáticas padrão
INSERT INTO chat_mensagens_automaticas (nome, tipo, mensagem, condicoes) VALUES
('Fora do Horário', 'fora_horario', 'Olá! Estamos fora do horário de atendimento (08:00 às 18:00). Retornaremos em breve!', '{"horario_inicio": "18:00", "horario_fim": "08:00"}'),
('Fim de Semana', 'personalizada', 'Olá! Estamos atendendo apenas em dias úteis. Retornaremos na segunda-feira!', '{"dias": [6, 7]}')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ============================================
-- 13. TRIGGERS PARA ATUALIZAÇÃO AUTOMÁTICA
-- ============================================

-- Remove triggers existentes se houver
DROP TRIGGER IF EXISTS trg_chat_mensagem_insert;
DROP TRIGGER IF EXISTS trg_chat_mensagem_lida;

-- Trigger: Atualiza contadores quando mensagem é criada
DELIMITER //
CREATE TRIGGER trg_chat_mensagem_insert AFTER INSERT ON chat_mensagens
FOR EACH ROW
BEGIN
    -- Atualiza contadores da conversa
    UPDATE chat_conversas SET
        total_mensagens = total_mensagens + 1,
        ultima_mensagem_at = NOW(),
        ultima_mensagem_por = IF(NEW.enviado_por_colaborador_id IS NOT NULL, 'colaborador', 'rh'),
        updated_at = NOW()
    WHERE id = NEW.conversa_id;
    
    -- Incrementa contador de não lidas
    IF NEW.enviado_por_colaborador_id IS NOT NULL THEN
        -- Mensagem do colaborador: incrementa não lidas do RH
        UPDATE chat_conversas SET
            total_mensagens_nao_lidas_rh = total_mensagens_nao_lidas_rh + 1,
            status = CASE 
                WHEN status = 'nova' THEN 'aguardando_triagem'
                WHEN status = 'aguardando_colaborador' THEN 'em_atendimento'
                ELSE status
            END
        WHERE id = NEW.conversa_id;
    ELSE
        -- Mensagem do RH: incrementa não lidas do colaborador
        UPDATE chat_conversas SET
            total_mensagens_nao_lidas_colaborador = total_mensagens_nao_lidas_colaborador + 1,
            status = CASE 
                WHEN status IN ('nova', 'aguardando_triagem') THEN 'em_atendimento'
                WHEN status = 'aguardando_resposta' THEN 'em_atendimento'
                ELSE status
            END
        WHERE id = NEW.conversa_id;
        
        -- Calcula tempo de primeira resposta se for primeira mensagem do RH
        UPDATE chat_conversas SET
            tempo_primeira_resposta_segundos = TIMESTAMPDIFF(SECOND, created_at, NOW()),
            total_respostas_rh = total_respostas_rh + 1
        WHERE id = NEW.conversa_id 
        AND total_respostas_rh = 0;
        
        -- Atualiza tempo médio de resposta
        UPDATE chat_conversas SET
            tempo_medio_resposta_segundos = (
                SELECT AVG(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at))
                FROM chat_mensagens m1
                INNER JOIN chat_mensagens m2 ON m1.conversa_id = m2.conversa_id
                WHERE m1.conversa_id = NEW.conversa_id
                AND m1.enviado_por_colaborador_id IS NOT NULL
                AND m2.enviado_por_usuario_id IS NOT NULL
                AND m2.created_at > m1.created_at
                AND m2.id = (
                    SELECT MIN(m3.id)
                    FROM chat_mensagens m3
                    WHERE m3.conversa_id = m1.conversa_id
                    AND m3.enviado_por_usuario_id IS NOT NULL
                    AND m3.created_at > m1.created_at
                )
            )
        WHERE id = NEW.conversa_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Atualiza quando mensagem é marcada como lida
DELIMITER //
CREATE TRIGGER trg_chat_mensagem_lida AFTER UPDATE ON chat_mensagens
FOR EACH ROW
BEGIN
    -- Se foi marcada como lida pelo colaborador
    IF OLD.lida_por_colaborador = FALSE AND NEW.lida_por_colaborador = TRUE THEN
        UPDATE chat_conversas SET
            total_mensagens_nao_lidas_colaborador = GREATEST(0, total_mensagens_nao_lidas_colaborador - 1),
            colaborador_visualizou_at = NOW()
        WHERE id = NEW.conversa_id;
    END IF;
    
    -- Se foi marcada como lida pelo RH
    IF OLD.lida_por_rh = FALSE AND NEW.lida_por_rh = TRUE THEN
        UPDATE chat_conversas SET
            total_mensagens_nao_lidas_rh = GREATEST(0, total_mensagens_nao_lidas_rh - 1),
            rh_visualizou_at = NOW()
        WHERE id = NEW.conversa_id;
    END IF;
END//
DELIMITER ;

-- ============================================
-- 14. VIEWS ÚTEIS
-- ============================================

-- Remove views existentes se houver
DROP VIEW IF EXISTS vw_chat_conversas_completo;
DROP VIEW IF EXISTS vw_chat_estatisticas_rh;
DROP VIEW IF EXISTS vw_chat_metricas_gerais;

-- View: Conversas com informações completas
CREATE OR REPLACE VIEW vw_chat_conversas_completo AS
SELECT 
    c.*,
    col.nome_completo as colaborador_nome,
    col.foto as colaborador_foto,
    col.email_pessoal as colaborador_email,
    col.cpf as colaborador_cpf,
    col.setor_id as colaborador_setor_id,
    s.nome_setor as colaborador_setor_nome,
    u.nome as atribuido_para_nome,
    u.email as atribuido_para_email,
    u.foto as atribuido_para_foto,
    cat.nome as categoria_nome,
    cat.cor as categoria_cor,
    COUNT(DISTINCT m.id) as total_mensagens_real,
    MAX(m.created_at) as ultima_mensagem_real_at,
    CASE 
        WHEN c.sla_primeira_resposta_vencimento IS NOT NULL AND c.sla_primeira_resposta_vencimento < NOW() AND c.tempo_primeira_resposta_segundos IS NULL THEN 'vencido'
        WHEN c.sla_primeira_resposta_vencimento IS NOT NULL AND c.sla_primeira_resposta_vencimento < DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND c.tempo_primeira_resposta_segundos IS NULL THEN 'proximo_vencer'
        ELSE 'ok'
    END as status_sla_primeira_resposta,
    CASE 
        WHEN c.sla_resolucao_vencimento IS NOT NULL AND c.sla_resolucao_vencimento < NOW() AND c.status NOT IN ('fechada', 'resolvida') THEN 'vencido'
        WHEN c.sla_resolucao_vencimento IS NOT NULL AND c.sla_resolucao_vencimento < DATE_ADD(NOW(), INTERVAL 2 HOUR) AND c.status NOT IN ('fechada', 'resolvida') THEN 'proximo_vencer'
        ELSE 'ok'
    END as status_sla_resolucao
FROM chat_conversas c
INNER JOIN colaboradores col ON c.colaborador_id = col.id
LEFT JOIN setores s ON col.setor_id = s.id
LEFT JOIN usuarios u ON c.atribuido_para_usuario_id = u.id
LEFT JOIN chat_categorias cat ON c.categoria_id = cat.id
LEFT JOIN chat_mensagens m ON c.id = m.conversa_id AND m.deletada = FALSE
GROUP BY c.id;

-- View: Estatísticas de chat por RH
CREATE OR REPLACE VIEW vw_chat_estatisticas_rh AS
SELECT 
    u.id as usuario_id,
    u.nome as usuario_nome,
    u.email as usuario_email,
    COUNT(DISTINCT c.id) as total_conversas,
    COUNT(DISTINCT CASE WHEN c.status = 'nova' THEN c.id END) as conversas_novas,
    COUNT(DISTINCT CASE WHEN c.status = 'aguardando_triagem' THEN c.id END) as conversas_aguardando_triagem,
    COUNT(DISTINCT CASE WHEN c.status = 'em_atendimento' THEN c.id END) as conversas_em_atendimento,
    COUNT(DISTINCT CASE WHEN c.status = 'aguardando_colaborador' THEN c.id END) as conversas_aguardando_colaborador,
    COUNT(DISTINCT CASE WHEN c.status = 'fechada' THEN c.id END) as conversas_fechadas,
    COUNT(DISTINCT CASE WHEN c.total_mensagens_nao_lidas_rh > 0 THEN c.id END) as conversas_nao_lidas,
    AVG(c.tempo_primeira_resposta_segundos) as tempo_medio_primeira_resposta_segundos,
    AVG(c.tempo_resolucao_segundos) as tempo_medio_resolucao_segundos,
    AVG(c.tempo_medio_resposta_segundos) as tempo_medio_resposta_segundos,
    COUNT(DISTINCT m.id) as total_mensagens_enviadas,
    SUM(CASE WHEN c.sla_primeira_resposta_cumprido = TRUE THEN 1 ELSE 0 END) as sla_primeira_resposta_cumprido,
    SUM(CASE WHEN c.sla_resolucao_cumprido = TRUE THEN 1 ELSE 0 END) as sla_resolucao_cumprido,
    COUNT(DISTINCT CASE WHEN c.sla_primeira_resposta_cumprido = FALSE THEN c.id END) as sla_primeira_resposta_nao_cumprido,
    COUNT(DISTINCT CASE WHEN c.sla_resolucao_cumprido = FALSE THEN c.id END) as sla_resolucao_nao_cumprido
FROM usuarios u
LEFT JOIN chat_conversas c ON c.atribuido_para_usuario_id = u.id
LEFT JOIN chat_mensagens m ON m.conversa_id = c.id AND m.enviado_por_usuario_id = u.id AND m.deletada = FALSE
WHERE u.role IN ('ADMIN', 'RH')
GROUP BY u.id, u.nome, u.email;

-- View: Métricas gerais do chat
CREATE OR REPLACE VIEW vw_chat_metricas_gerais AS
SELECT 
    COUNT(DISTINCT c.id) as total_conversas,
    COUNT(DISTINCT CASE WHEN c.status NOT IN ('fechada', 'arquivada') THEN c.id END) as conversas_abertas,
    COUNT(DISTINCT CASE WHEN c.status = 'nova' THEN c.id END) as conversas_novas,
    COUNT(DISTINCT CASE WHEN c.status = 'em_atendimento' THEN c.id END) as conversas_em_atendimento,
    COUNT(DISTINCT CASE WHEN c.total_mensagens_nao_lidas_rh > 0 THEN c.id END) as conversas_nao_lidas_rh,
    COUNT(DISTINCT CASE WHEN c.total_mensagens_nao_lidas_colaborador > 0 THEN c.id END) as conversas_nao_lidas_colaborador,
    AVG(c.tempo_primeira_resposta_segundos) as tempo_medio_primeira_resposta_segundos,
    AVG(c.tempo_resolucao_segundos) as tempo_medio_resolucao_segundos,
    AVG(c.tempo_medio_resposta_segundos) as tempo_medio_resposta_segundos,
    SUM(CASE WHEN c.sla_primeira_resposta_cumprido = TRUE THEN 1 ELSE 0 END) as sla_primeira_resposta_cumprido,
    SUM(CASE WHEN c.sla_resolucao_cumprido = TRUE THEN 1 ELSE 0 END) as sla_resolucao_cumprido,
    COUNT(DISTINCT CASE WHEN c.sla_primeira_resposta_vencimento < NOW() AND c.tempo_primeira_resposta_segundos IS NULL THEN c.id END) as sla_vencido_primeira_resposta,
    COUNT(DISTINCT CASE WHEN c.sla_resolucao_vencimento < NOW() AND c.status NOT IN ('fechada', 'resolvida') THEN c.id END) as sla_vencido_resolucao
FROM chat_conversas c;

-- ============================================
-- 15. ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================

CREATE INDEX idx_conversas_colaborador_status ON chat_conversas(colaborador_id, status, ultima_mensagem_at DESC);
CREATE INDEX idx_conversas_atribuido_status ON chat_conversas(atribuido_para_usuario_id, status, ultima_mensagem_at DESC);
CREATE INDEX idx_mensagens_conversa_tipo ON chat_mensagens(conversa_id, tipo, created_at DESC);
CREATE INDEX idx_participantes_usuario_ativo ON chat_participantes(usuario_id, removido, ultima_visualizacao DESC);
CREATE INDEX idx_conversas_sla_vencimento ON chat_conversas(sla_primeira_resposta_vencimento, sla_resolucao_vencimento, status);

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================
