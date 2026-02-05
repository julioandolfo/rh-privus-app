-- =============================================
-- Migração: Configurações OpenAI e Geração de Vagas com IA
-- Descrição: Cria tabelas para configuração da OpenAI e histórico de gerações
-- Data: 2026-02-05
-- =============================================

-- Tabela de Configurações da OpenAI
CREATE TABLE IF NOT EXISTS openai_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(255) NOT NULL COMMENT 'Chave de API da OpenAI',
    modelo VARCHAR(50) NOT NULL DEFAULT 'gpt-4o-mini' COMMENT 'Modelo a ser usado (gpt-4o, gpt-4o-mini, gpt-3.5-turbo)',
    temperatura DECIMAL(2,1) NOT NULL DEFAULT 0.7 COMMENT 'Criatividade do modelo (0.0 a 1.0)',
    max_tokens INT NOT NULL DEFAULT 2000 COMMENT 'Máximo de tokens por requisição',
    ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se a integração está ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL COMMENT 'ID do usuário que atualizou',
    FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Histórico de Gerações com IA
CREATE TABLE IF NOT EXISTS vagas_geradas_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NULL COMMENT 'ID da vaga criada (após salvar)',
    usuario_id INT NOT NULL COMMENT 'Usuário que gerou',
    empresa_id INT NOT NULL COMMENT 'Empresa selecionada',
    descricao_entrada TEXT NOT NULL COMMENT 'Descrição fornecida pelo usuário',
    resposta_ia LONGTEXT NOT NULL COMMENT 'JSON completo retornado pela IA',
    modelo_usado VARCHAR(50) NOT NULL COMMENT 'Modelo OpenAI usado',
    tokens_usados INT NULL COMMENT 'Tokens consumidos',
    custo_estimado DECIMAL(10,6) NULL COMMENT 'Custo estimado em USD',
    tempo_geracao_ms INT NULL COMMENT 'Tempo de geração em milissegundos',
    campos_editados JSON NULL COMMENT 'Campos que o usuário editou após gerar',
    qualidade_score INT NULL COMMENT 'Score de qualidade da vaga (0-100)',
    foi_salva TINYINT(1) DEFAULT 0 COMMENT 'Se a vaga foi efetivamente criada',
    template_usado VARCHAR(50) NULL COMMENT 'Template de prompt usado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_vaga (vaga_id),
    INDEX idx_data (created_at),
    INDEX idx_foi_salva (foi_salva)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Templates de Prompts
CREATE TABLE IF NOT EXISTS openai_prompt_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único do template',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome do template',
    descricao TEXT NULL COMMENT 'Descrição do template',
    categoria VARCHAR(50) NOT NULL COMMENT 'Categoria (tecnologia, administrativo, operacional, vendas, etc)',
    prompt_sistema TEXT NOT NULL COMMENT 'Prompt do sistema',
    prompt_usuario TEXT NOT NULL COMMENT 'Template do prompt do usuário',
    exemplo TEXT NULL COMMENT 'Exemplo de entrada para este template',
    ativo TINYINT(1) DEFAULT 1 COMMENT 'Se o template está ativo',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    vezes_usado INT DEFAULT 0 COMMENT 'Contador de uso',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_ativo (ativo),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Limite de Uso (Rate Limiting)
CREATE TABLE IF NOT EXISTS openai_rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'ID do usuário',
    data_uso DATE NOT NULL COMMENT 'Data do uso',
    quantidade INT DEFAULT 1 COMMENT 'Quantidade de gerações no dia',
    limite_diario INT DEFAULT 10 COMMENT 'Limite diário permitido',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_data (usuario_id, data_uso),
    INDEX idx_data (data_uso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere templates padrão
INSERT INTO openai_prompt_templates (codigo, nome, descricao, categoria, prompt_sistema, prompt_usuario, exemplo, ativo, ordem) VALUES
(
    'vaga_tecnologia',
    'Vaga de Tecnologia',
    'Template otimizado para vagas da área de tecnologia e desenvolvimento',
    'tecnologia',
    'Você é um especialista em recrutamento de tecnologia. Crie vagas detalhadas, técnicas e atrativas para desenvolvedores e profissionais de TI.',
    'Crie uma vaga completa de tecnologia baseada na seguinte descrição:\n\n{descricao}\n\nRetorne APENAS um JSON válido com a estrutura especificada.',
    'Desenvolvedor Full Stack Python/React para startup de fintech. Remoto, salário 8k-12k, experiência com AWS e Docker',
    1,
    1
),
(
    'vaga_administrativa',
    'Vaga Administrativa',
    'Template otimizado para vagas administrativas e de escritório',
    'administrativo',
    'Você é um especialista em recrutamento para áreas administrativas. Crie vagas profissionais e claras para funções de escritório e gestão.',
    'Crie uma vaga completa administrativa baseada na seguinte descrição:\n\n{descricao}\n\nRetorne APENAS um JSON válido com a estrutura especificada.',
    'Assistente Administrativo para empresa de logística. Presencial em São Paulo, experiência com Excel e atendimento',
    1,
    2
),
(
    'vaga_vendas',
    'Vaga de Vendas',
    'Template otimizado para vagas comerciais e de vendas',
    'vendas',
    'Você é um especialista em recrutamento comercial. Crie vagas motivadoras e focadas em resultados para profissionais de vendas.',
    'Crie uma vaga completa de vendas baseada na seguinte descrição:\n\n{descricao}\n\nRetorne APENAS um JSON válido com a estrutura especificada.',
    'Vendedor Externo para revenda de veículos. Comissão atrativa, carteira própria desejável, experiência em vendas B2C',
    1,
    3
),
(
    'vaga_operacional',
    'Vaga Operacional',
    'Template otimizado para vagas operacionais e de produção',
    'operacional',
    'Você é um especialista em recrutamento operacional. Crie vagas claras e objetivas para funções operacionais e de produção.',
    'Crie uma vaga completa operacional baseada na seguinte descrição:\n\n{descricao}\n\nRetorne APENAS um JSON válido com a estrutura especificada.',
    'Operador de Empilhadeira para centro de distribuição. Turno noturno, CNH B obrigatória, experiência mínima 1 ano',
    1,
    4
),
(
    'vaga_generica',
    'Vaga Genérica',
    'Template padrão para qualquer tipo de vaga',
    'geral',
    'Você é um especialista em Recursos Humanos. Crie vagas profissionais, completas e atrativas para candidatos qualificados.',
    'Crie uma vaga completa baseada na seguinte descrição:\n\n{descricao}\n\nRetorne APENAS um JSON válido com a estrutura especificada.',
    'Gerente de Projetos para empresa de construção civil. Híbrido, experiência com gestão de equipes e prazos',
    1,
    5
);

-- Configuração padrão (comentada - deve ser inserida após obter a chave API)
-- INSERT INTO openai_config (api_key, modelo, temperatura, max_tokens, ativo) 
-- VALUES ('sua-chave-api-aqui', 'gpt-4o-mini', 0.7, 2000, 1);

-- =============================================
-- Fim da Migração
-- =============================================
