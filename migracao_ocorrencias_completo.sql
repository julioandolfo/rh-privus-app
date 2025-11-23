-- ============================================
-- MIGRAÇÃO COMPLETA: Sistema de Ocorrências Avançado
-- Implementa todas as melhorias exceto integração com ponto (#13)
-- ============================================

-- 1. Adicionar campos à tabela tipos_ocorrencias
ALTER TABLE tipos_ocorrencias
ADD COLUMN severidade ENUM('leve', 'moderada', 'grave', 'critica') DEFAULT 'moderada' AFTER categoria,
ADD COLUMN requer_aprovacao BOOLEAN DEFAULT FALSE AFTER severidade,
ADD COLUMN conta_advertencia BOOLEAN DEFAULT FALSE AFTER requer_aprovacao,
ADD COLUMN calcula_desconto BOOLEAN DEFAULT FALSE AFTER conta_advertencia,
ADD COLUMN valor_desconto DECIMAL(10,2) NULL AFTER calcula_desconto,
ADD COLUMN template_descricao TEXT NULL AFTER valor_desconto,
ADD COLUMN validacoes_customizadas JSON NULL AFTER template_descricao,
ADD COLUMN notificar_colaborador BOOLEAN DEFAULT TRUE AFTER validacoes_customizadas,
ADD COLUMN notificar_gestor BOOLEAN DEFAULT TRUE AFTER notificar_colaborador,
ADD COLUMN notificar_rh BOOLEAN DEFAULT TRUE AFTER notificar_gestor,
ADD INDEX idx_severidade (severidade),
ADD INDEX idx_requer_aprovacao (requer_aprovacao);

-- 2. Criar tabela de campos dinâmicos para tipos de ocorrências
CREATE TABLE IF NOT EXISTS tipos_ocorrencias_campos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_ocorrencia_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    tipo_campo ENUM('text', 'textarea', 'number', 'date', 'time', 'select', 'checkbox', 'radio') NOT NULL,
    label VARCHAR(200) NOT NULL,
    placeholder VARCHAR(200) NULL,
    obrigatorio BOOLEAN DEFAULT FALSE,
    valor_padrao TEXT NULL,
    opcoes JSON NULL COMMENT 'Para select/radio: array de opções',
    validacao JSON NULL COMMENT 'Regras de validação customizadas',
    ordem INT DEFAULT 0,
    condicao_exibir JSON NULL COMMENT 'Condições para exibir este campo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE CASCADE,
    INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Adicionar campos à tabela ocorrencias
ALTER TABLE ocorrencias
ADD COLUMN severidade ENUM('leve', 'moderada', 'grave', 'critica') NULL AFTER tipo_ponto,
ADD COLUMN status_aprovacao ENUM('pendente', 'aprovada', 'rejeitada') DEFAULT 'aprovada' AFTER severidade,
ADD COLUMN aprovado_por INT NULL AFTER status_aprovacao,
ADD COLUMN data_aprovacao DATETIME NULL AFTER aprovado_por,
ADD COLUMN observacao_aprovacao TEXT NULL AFTER data_aprovacao,
ADD COLUMN valor_desconto DECIMAL(10,2) NULL AFTER observacao_aprovacao,
ADD COLUMN tags JSON NULL COMMENT 'Array de tags' AFTER valor_desconto,
ADD COLUMN campos_dinamicos JSON NULL COMMENT 'Valores dos campos dinâmicos' AFTER tags,
ADD INDEX idx_severidade (severidade),
ADD INDEX idx_status_aprovacao (status_aprovacao),
ADD INDEX idx_aprovado_por (aprovado_por),
ADD FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- 4. Criar tabela de anexos de ocorrências
CREATE TABLE IF NOT EXISTS ocorrencias_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ocorrencia_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NULL,
    tamanho_bytes INT NULL,
    descricao VARCHAR(500) NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_ocorrencia (ocorrencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Criar tabela de comentários/respostas em ocorrências
CREATE TABLE IF NOT EXISTS ocorrencias_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ocorrencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    tipo ENUM('comentario', 'resposta', 'defesa') DEFAULT 'comentario',
    anexos JSON NULL COMMENT 'Array de caminhos de arquivos anexados',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_ocorrencia (ocorrencia_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Criar tabela de histórico/auditoria de ocorrências
CREATE TABLE IF NOT EXISTS ocorrencias_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ocorrencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao ENUM('criada', 'editada', 'aprovada', 'rejeitada', 'cancelada', 'comentada') NOT NULL,
    campo_alterado VARCHAR(100) NULL,
    valor_anterior TEXT NULL,
    valor_novo TEXT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_ocorrencia (ocorrencia_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Criar tabela de advertências progressivas
CREATE TABLE IF NOT EXISTS ocorrencias_advertencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT NOT NULL,
    ocorrencia_id INT NOT NULL,
    tipo_advertencia ENUM('verbal', 'escrita', 'suspensao', 'demissao') NOT NULL,
    nivel INT DEFAULT 1 COMMENT 'Nível da advertência (1, 2, 3...)',
    data_advertencia DATE NOT NULL,
    data_validade DATE NULL COMMENT 'Data até quando a advertência é válida',
    observacoes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_ocorrencia (ocorrencia_id),
    INDEX idx_tipo (tipo_advertencia),
    INDEX idx_data_validade (data_validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Criar tabela de regras de advertências progressivas
CREATE TABLE IF NOT EXISTS ocorrencias_regras_advertencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_ocorrencia_id INT NULL COMMENT 'NULL = regra geral',
    quantidade_ocorrencias INT NOT NULL COMMENT 'Quantidade de ocorrências para aplicar regra',
    periodo_dias INT NULL COMMENT 'Período em dias para contar ocorrências (NULL = sem limite)',
    acao ENUM('verbal', 'escrita', 'suspensao', 'demissao') NOT NULL,
    nivel_advertencia INT DEFAULT 1,
    dias_validade INT NULL COMMENT 'Dias de validade da advertência',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE CASCADE,
    INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Criar tabela de tags de ocorrências (para categorização múltipla)
CREATE TABLE IF NOT EXISTS ocorrencias_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    cor VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Cor em hexadecimal',
    descricao VARCHAR(200) NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Criar tabela de templates de descrição
CREATE TABLE IF NOT EXISTS ocorrencias_templates_descricao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_ocorrencia_id INT NULL COMMENT 'NULL = template geral',
    nome VARCHAR(100) NOT NULL,
    template TEXT NOT NULL COMMENT 'Template com variáveis {variavel}',
    variaveis_disponiveis JSON NULL COMMENT 'Lista de variáveis disponíveis',
    ativo BOOLEAN DEFAULT TRUE,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_ocorrencia_id) REFERENCES tipos_ocorrencias(id) ON DELETE CASCADE,
    INDEX idx_tipo_ocorrencia (tipo_ocorrencia_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Criar tabela de notificações de ocorrências
CREATE TABLE IF NOT EXISTS ocorrencias_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ocorrencia_id INT NOT NULL,
    usuario_id INT NULL COMMENT 'NULL = notificação para colaborador',
    colaborador_id INT NULL COMMENT 'NULL = notificação para usuário',
    tipo ENUM('email', 'push', 'sistema') NOT NULL,
    status ENUM('pendente', 'enviada', 'lida', 'erro') DEFAULT 'pendente',
    assunto VARCHAR(200) NULL,
    mensagem TEXT NULL,
    enviado_em DATETIME NULL,
    lido_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ocorrencia_id) REFERENCES ocorrencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
    INDEX idx_ocorrencia (ocorrencia_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_colaborador (colaborador_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Inserir tags padrão
INSERT INTO ocorrencias_tags (nome, cor, descricao) VALUES
('urgente', '#dc3545', 'Ocorrência urgente que requer atenção imediata'),
('reincidente', '#fd7e14', 'Colaborador com histórico de ocorrências similares'),
('primeira-vez', '#28a745', 'Primeira ocorrência deste tipo'),
('documentado', '#17a2b8', 'Ocorrência com documentação anexada'),
('resolvido', '#6c757d', 'Ocorrência já resolvida'),
('pendente-acao', '#ffc107', 'Aguardando ação do colaborador ou gestor');

-- 13. Inserir regras padrão de advertências progressivas
INSERT INTO ocorrencias_regras_advertencias (tipo_ocorrencia_id, quantidade_ocorrencias, periodo_dias, acao, nivel_advertencia, dias_validade) VALUES
(NULL, 3, 30, 'verbal', 1, 90),
(NULL, 5, 30, 'escrita', 2, 180),
(NULL, 7, 30, 'suspensao', 3, 365),
(NULL, 10, 30, 'demissao', 4, NULL);

-- 14. Atualizar ocorrências existentes com severidade padrão
UPDATE ocorrencias SET severidade = 'moderada' WHERE severidade IS NULL;

-- 15. Criar view para estatísticas de ocorrências por colaborador
CREATE OR REPLACE VIEW vw_ocorrencias_estatisticas AS
SELECT 
    c.id as colaborador_id,
    c.nome_completo,
    COUNT(o.id) as total_ocorrencias,
    SUM(CASE WHEN o.severidade = 'leve' THEN 1 ELSE 0 END) as ocorrencias_leves,
    SUM(CASE WHEN o.severidade = 'moderada' THEN 1 ELSE 0 END) as ocorrencias_moderadas,
    SUM(CASE WHEN o.severidade = 'grave' THEN 1 ELSE 0 END) as ocorrencias_graves,
    SUM(CASE WHEN o.severidade = 'critica' THEN 1 ELSE 0 END) as ocorrencias_criticas,
    SUM(CASE WHEN o.status_aprovacao = 'pendente' THEN 1 ELSE 0 END) as ocorrencias_pendentes,
    COUNT(DISTINCT DATE_FORMAT(o.data_ocorrencia, '%Y-%m')) as meses_com_ocorrencias,
    MAX(o.data_ocorrencia) as ultima_ocorrencia,
    COUNT(DISTINCT a.id) as total_advertencias
FROM colaboradores c
LEFT JOIN ocorrencias o ON c.id = o.colaborador_id
LEFT JOIN ocorrencias_advertencias a ON c.id = a.colaborador_id
WHERE c.status = 'ativo'
GROUP BY c.id, c.nome_completo;

