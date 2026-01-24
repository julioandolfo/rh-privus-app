-- Script de Migração: Sistema de Eventos
-- Execute este script para criar as tabelas do sistema de eventos

-- Tabela principal de eventos
CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Informações do evento
    titulo VARCHAR(255) NOT NULL COMMENT 'Título do evento',
    descricao TEXT NULL COMMENT 'Descrição detalhada do evento',
    local VARCHAR(255) NULL COMMENT 'Local do evento (físico ou virtual)',
    link_virtual VARCHAR(500) NULL COMMENT 'Link para reunião virtual (Meet, Zoom, etc)',
    
    -- Data e horário
    data_evento DATE NOT NULL COMMENT 'Data do evento',
    hora_inicio TIME NOT NULL COMMENT 'Hora de início',
    hora_fim TIME NULL COMMENT 'Hora de término (opcional)',
    
    -- Configurações
    tipo ENUM('reuniao', 'treinamento', 'confraternizacao', 'palestra', 'workshop', 'outro') NOT NULL DEFAULT 'reuniao' COMMENT 'Tipo do evento',
    status ENUM('agendado', 'em_andamento', 'concluido', 'cancelado') NOT NULL DEFAULT 'agendado' COMMENT 'Status do evento',
    confirmacao_obrigatoria TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se participantes devem confirmar presença',
    
    -- Relacionamentos
    empresa_id INT NULL COMMENT 'Empresa relacionada',
    criado_por_usuario_id INT NULL COMMENT 'Usuário que criou o evento',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_eventos_data (data_evento),
    INDEX idx_eventos_status (status),
    INDEX idx_eventos_empresa (empresa_id),
    INDEX idx_eventos_criador (criado_por_usuario_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eventos do sistema';

-- Tabela de participantes dos eventos
CREATE TABLE IF NOT EXISTS eventos_participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relacionamentos
    evento_id INT NOT NULL COMMENT 'ID do evento',
    colaborador_id INT NOT NULL COMMENT 'ID do colaborador convidado',
    
    -- Status de confirmação
    status_confirmacao ENUM('pendente', 'confirmado', 'recusado', 'talvez') NOT NULL DEFAULT 'pendente' COMMENT 'Status da confirmação',
    motivo_recusa TEXT NULL COMMENT 'Motivo caso recuse o convite',
    
    -- Token para confirmação via email
    token_confirmacao VARCHAR(64) NULL COMMENT 'Token único para confirmação via link',
    
    -- Controle de presença real
    presente TINYINT(1) NULL COMMENT 'Se realmente compareceu ao evento',
    
    -- Datas
    data_confirmacao DATETIME NULL COMMENT 'Data/hora da confirmação',
    data_convite_enviado DATETIME NULL COMMENT 'Data/hora do envio do convite',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_participantes_evento (evento_id),
    INDEX idx_participantes_colaborador (colaborador_id),
    INDEX idx_participantes_status (status_confirmacao),
    INDEX idx_participantes_token (token_confirmacao),
    
    -- Chave única para evitar duplicatas
    UNIQUE KEY uk_evento_colaborador (evento_id, colaborador_id),
    
    -- Chaves estrangeiras
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Participantes dos eventos';

-- Insere template de email para convite de evento
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
('convite_evento', 'Convite para Evento', 'Você foi convidado: {titulo_evento}', 
'<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #009ef7; margin-bottom: 10px;">Convite para Evento</h1>
    </div>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">Olá <strong>{nome_completo}</strong>,</p>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        Você foi convidado(a) para participar do seguinte evento:
    </p>
    
    <div style="background-color: #f8f9fa; border-left: 4px solid #009ef7; padding: 20px; margin: 25px 0; border-radius: 4px;">
        <h2 style="margin-top: 0; color: #009ef7;">{titulo_evento}</h2>
        
        <p style="margin-bottom: 10px;">
            <strong>📅 Data:</strong> {data_evento}
        </p>
        <p style="margin-bottom: 10px;">
            <strong>🕐 Horário:</strong> {horario_evento}
        </p>
        <p style="margin-bottom: 10px;">
            <strong>📍 Local:</strong> {local_evento}
        </p>
        <p style="margin-bottom: 10px;">
            <strong>📋 Tipo:</strong> {tipo_evento}
        </p>
        
        {descricao_html}
        
        {link_virtual_html}
    </div>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        <strong>Por favor, confirme sua presença clicando em uma das opções abaixo:</strong>
    </p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{link_confirmar}" 
           style="display: inline-block; padding: 12px 30px; background-color: #50cd89; color: white; 
                  text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; margin: 5px;">
            ✓ Confirmar Presença
        </a>
        <a href="{link_recusar}" 
           style="display: inline-block; padding: 12px 30px; background-color: #f1416c; color: white; 
                  text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; margin: 5px;">
            ✗ Não Poderei Comparecer
        </a>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6; color: #666; margin-top: 30px;">
        Você também pode acessar o sistema para gerenciar seus eventos:
    </p>
    
    <div style="text-align: center; margin: 20px 0;">
        <a href="{sistema_url}" 
           style="display: inline-block; padding: 10px 25px; background-color: #009ef7; color: white; 
                  text-decoration: none; border-radius: 5px; font-size: 14px;">
            Acessar Sistema
        </a>
    </div>
    
    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
    
    <p style="font-size: 13px; color: #999; text-align: center;">
        <strong>{empresa_nome}</strong><br>
        Este é um email automático, por favor não responda.
    </p>
</div>',
'Olá {nome_completo},

Você foi convidado(a) para participar do seguinte evento:

{titulo_evento}

📅 Data: {data_evento}
🕐 Horário: {horario_evento}
📍 Local: {local_evento}
📋 Tipo: {tipo_evento}

{descricao_texto}

Para confirmar sua presença, acesse: {link_confirmar}
Para recusar, acesse: {link_recusar}

Ou acesse o sistema: {sistema_url}

---
{empresa_nome}
Este é um email automático, por favor não responda.',
1,
'["nome_completo", "titulo_evento", "data_evento", "horario_evento", "local_evento", "tipo_evento", "descricao_html", "descricao_texto", "link_virtual_html", "link_confirmar", "link_recusar", "sistema_url", "empresa_nome"]',
'Enviado quando um colaborador é convidado para um evento.')
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao);
