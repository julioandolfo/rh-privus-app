-- Script de Migração: Template de Email para Comunicados
-- Execute este script para adicionar o template de comunicados

-- Insere ou atualiza template de comunicados
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
('novo_comunicado', 'Novo Comunicado', '{titulo} - Novo Comunicado', 
'<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">📢 Novo Comunicado</h1>
    </div>
    
    <div style="background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none;">
        <p style="font-size: 16px; line-height: 1.6; color: #333; margin-bottom: 10px;">
            Olá <strong>{nome_completo}</strong>,
        </p>
        
        <p style="font-size: 16px; line-height: 1.6; color: #333; margin-bottom: 25px;">
            Foi publicado um novo comunicado importante para você:
        </p>
        
        <div style="background-color: #f8f9fa; padding: 20px; border-left: 4px solid #667eea; border-radius: 4px; margin: 25px 0;">
            <h2 style="margin-top: 0; color: #667eea; font-size: 22px;">{titulo}</h2>
            
            {imagem_html}
            
            <div style="font-size: 15px; line-height: 1.8; color: #555; margin-top: 15px;">
                {conteudo_preview}
            </div>
        </div>
        
        <p style="font-size: 14px; color: #666; margin: 20px 0;">
            <strong>Publicado por:</strong> {criado_por_nome}<br>
            <strong>Data:</strong> {data_publicacao}
        </p>
        
        <div style="text-align: center; margin: 35px 0;">
            <a href="{sistema_url}" 
               style="display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                      color: white; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; 
                      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                Ver Comunicado Completo
            </a>
        </div>
        
        <p style="font-size: 13px; line-height: 1.6; color: #999; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            Este é um comunicado importante da sua empresa. Acesse o sistema para ler na íntegra e marcar como lido.
        </p>
    </div>
    
    <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px;">
        <p style="font-size: 13px; color: #999; margin: 0;">
            <strong>{empresa_nome}</strong><br>
            Este é um email automático, por favor não responda.
        </p>
    </div>
</div>',
'Olá {nome_completo},

Foi publicado um novo comunicado importante para você:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{titulo}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{conteudo_texto}

Publicado por: {criado_por_nome}
Data: {data_publicacao}

Acesse o sistema para ler o comunicado completo:
{sistema_url}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{empresa_nome}
Este é um email automático, por favor não responda.',
1,
'["nome_completo", "titulo", "conteudo_preview", "conteudo_texto", "imagem_html", "criado_por_nome", "data_publicacao", "sistema_url", "empresa_nome"]',
'Enviado automaticamente quando um novo comunicado é publicado para todos os colaboradores.')

ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    ativo = VALUES(ativo),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao);
