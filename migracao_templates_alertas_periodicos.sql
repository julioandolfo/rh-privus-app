-- Script de Migração: Templates de Email para Alertas Periódicos
-- Execute este script para adicionar os templates de alertas

-- Insere ou atualiza templates de alertas periódicos
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES

-- Template: Alerta de Inatividade
('alerta_inatividade', 'Alerta de Inatividade', 'Sentimos sua falta! 😊', 
'<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #1e88e5; margin-bottom: 10px;">Sentimos sua falta! 😊</h1>
    </div>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">Olá <strong>{nome_completo}</strong>,</p>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        Notamos que você não acessa o sistema há <strong>{dias_inativo} dias</strong> 
        (último acesso em <strong>{data_ultimo_acesso}</strong>).
    </p>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        Sua presença é importante para nós! Há várias novidades esperando por você:
    </p>
    
    <ul style="font-size: 15px; line-height: 1.8; color: #555; padding-left: 20px;">
        <li>📊 Acompanhe seu desempenho e progresso</li>
        <li>🎓 Novos cursos e treinamentos disponíveis</li>
        <li>💬 Mensagens e feedbacks da equipe</li>
        <li>🎯 Metas e objetivos atualizados</li>
        <li>📢 Comunicados importantes</li>
    </ul>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{sistema_url}" 
           style="display: inline-block; padding: 15px 40px; background-color: #1e88e5; color: white; 
                  text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">
            Acessar Sistema
        </a>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6; color: #666; margin-top: 30px;">
        Caso esteja com dificuldades para acessar, entre em contato com o RH.
    </p>
    
    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
    
    <p style="font-size: 13px; color: #999; text-align: center;">
        <strong>{empresa_nome}</strong><br>
        Este é um email automático, por favor não responda.
    </p>
</div>',
'Olá {nome_completo},

Notamos que você não acessa o sistema há {dias_inativo} dias (último acesso em {data_ultimo_acesso}).

Sua presença é importante para nós! Há várias novidades esperando por você:

- Acompanhe seu desempenho e progresso
- Novos cursos e treinamentos disponíveis  
- Mensagens e feedbacks da equipe
- Metas e objetivos atualizados
- Comunicados importantes

Acesse agora: {sistema_url}

Caso esteja com dificuldades para acessar, entre em contato com o RH.

---
{empresa_nome}
Este é um email automático, por favor não responda.',
1,
'["nome_completo", "dias_inativo", "data_ultimo_acesso", "sistema_url", "empresa_nome"]',
'Enviado automaticamente quando um colaborador não acessa o sistema há vários dias.'),

-- Template: Alerta de Emoções não Registradas
('alerta_emocoes', 'Alerta - Emoções não Registradas', 'Como você está se sentindo? 💙', 
'<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #7c4dff; margin-bottom: 10px;">Como você está se sentindo? 💙</h1>
    </div>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">Olá <strong>{nome_completo}</strong>,</p>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        Notamos que você não registra suas emoções há <strong>{dias_sem_registro} dias</strong> 
        (último registro em <strong>{data_ultimo_registro}</strong>).
    </p>
    
    <p style="font-size: 16px; line-height: 1.6; color: #333;">
        <strong>Por que registrar suas emoções é importante?</strong>
    </p>
    
    <ul style="font-size: 15px; line-height: 1.8; color: #555; padding-left: 20px;">
        <li>😊 Ajuda você a acompanhar seu bem-estar emocional</li>
        <li>📈 Permite identificar padrões e tendências</li>
        <li>💪 Contribui para um ambiente de trabalho mais saudável</li>
        <li>🤝 Ajuda a equipe de RH a oferecer o suporte necessário</li>
        <li>⏱️ Leva apenas 30 segundos!</li>
    </ul>
    
    <div style="background-color: #f5f5f5; padding: 20px; border-radius: 8px; margin: 25px 0;">
        <p style="font-size: 15px; margin: 0; color: #555;">
            <strong>💡 Dica:</strong> Reserve um momento no início ou fim do dia para registrar como você está se sentindo. 
            Isso ajuda a criar uma rotina de autocuidado!
        </p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{sistema_url}" 
           style="display: inline-block; padding: 15px 40px; background-color: #7c4dff; color: white; 
                  text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">
            Registrar Emoção Agora
        </a>
    </div>
    
    <p style="font-size: 14px; line-height: 1.6; color: #666; margin-top: 30px; text-align: center;">
        Seus dados são confidenciais e tratados com total privacidade.
    </p>
    
    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
    
    <p style="font-size: 13px; color: #999; text-align: center;">
        <strong>{empresa_nome}</strong><br>
        Este é um email automático, por favor não responda.
    </p>
</div>',
'Olá {nome_completo},

Notamos que você não registra suas emoções há {dias_sem_registro} dias (último registro em {data_ultimo_registro}).

Por que registrar suas emoções é importante?

- Ajuda você a acompanhar seu bem-estar emocional
- Permite identificar padrões e tendências
- Contribui para um ambiente de trabalho mais saudável
- Ajuda a equipe de RH a oferecer o suporte necessário
- Leva apenas 30 segundos!

Dica: Reserve um momento no início ou fim do dia para registrar como você está se sentindo. 
Isso ajuda a criar uma rotina de autocuidado!

Acesse agora: {sistema_url}

Seus dados são confidenciais e tratados com total privacidade.

---
{empresa_nome}
Este é um email automático, por favor não responda.',
1,
'["nome_completo", "dias_sem_registro", "data_ultimo_registro", "sistema_url", "empresa_nome"]',
'Enviado automaticamente quando um colaborador não registra emoções há vários dias.')

ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    ativo = VALUES(ativo),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao);
