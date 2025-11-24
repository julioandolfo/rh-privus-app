-- Migração: Sistema de Contratos com Integração Autentique
-- Tabelas para gestão de contratos e assinaturas eletrônicas

-- Tabela de Templates de Contrato
CREATE TABLE IF NOT EXISTS contratos_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    conteudo_html LONGTEXT NOT NULL COMMENT 'Conteúdo HTML do template com variáveis',
    variaveis_disponiveis TEXT NULL COMMENT 'JSON com variáveis usadas no template',
    ativo TINYINT(1) DEFAULT 1,
    criado_por_usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_ativo (ativo),
    INDEX idx_criado_por (criado_por_usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Contratos
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    template_id INT NULL COMMENT 'Template usado (pode ser NULL se foi criado sem template)',
    titulo VARCHAR(255) NOT NULL,
    descricao_funcao TEXT NULL COMMENT 'Descrição da função do colaborador neste contrato',
    conteudo_final_html LONGTEXT NOT NULL COMMENT 'Conteúdo HTML final com variáveis substituídas',
    pdf_path VARCHAR(500) NULL COMMENT 'Caminho do PDF gerado',
    status ENUM('rascunho', 'enviado', 'aguardando', 'assinado', 'cancelado', 'expirado') DEFAULT 'rascunho',
    autentique_document_id VARCHAR(255) NULL COMMENT 'ID do documento no Autentique',
    autentique_token VARCHAR(500) NULL COMMENT 'Token do documento no Autentique',
    criado_por_usuario_id INT NOT NULL,
    data_criacao DATE NULL,
    data_vencimento DATE NULL COMMENT 'Data de vencimento do contrato',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES contratos_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status),
    INDEX idx_template (template_id),
    INDEX idx_criado_por (criado_por_usuario_id),
    INDEX idx_data_criacao (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Signatários (Colaborador + Testemunhas + RH)
CREATE TABLE IF NOT EXISTS contratos_signatarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    tipo ENUM('colaborador', 'testemunha', 'rh') NOT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NULL COMMENT 'CPF formatado',
    autentique_signer_id VARCHAR(255) NULL COMMENT 'ID do signatário no Autentique',
    assinado TINYINT(1) DEFAULT 0,
    data_assinatura DATETIME NULL,
    link_publico VARCHAR(500) NULL COMMENT 'Link público de assinatura',
    link_expiracao DATETIME NULL COMMENT 'Data de expiração do link público',
    ordem_assinatura INT DEFAULT 0 COMMENT 'Ordem em que deve assinar (0 = primeiro)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    INDEX idx_contrato (contrato_id),
    INDEX idx_tipo (tipo),
    INDEX idx_assinado (assinado),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Histórico de Eventos (Webhooks Autentique)
CREATE TABLE IF NOT EXISTS contratos_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    tipo_evento VARCHAR(100) NOT NULL COMMENT 'document.signed, signer.signed, document.viewed, etc',
    dados_json LONGTEXT NULL COMMENT 'JSON completo do evento recebido',
    processado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
    INDEX idx_contrato (contrato_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_processado (processado),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações do Autentique
CREATE TABLE IF NOT EXISTS autentique_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(500) NOT NULL COMMENT 'API Key do Autentique',
    sandbox TINYINT(1) DEFAULT 1 COMMENT '1 = sandbox, 0 = produção',
    webhook_url VARCHAR(500) NULL COMMENT 'URL configurada no Autentique para webhooks',
    webhook_secret VARCHAR(255) NULL COMMENT 'Secret do webhook (se houver)',
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

