-- Template de Email para Feedback Recebido
-- Execute este script para adicionar o template de email de feedback

INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
('feedback_recebido', 'Feedback Recebido', 'Novo Feedback Recebido - {remetente_nome}', 
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0; 
            padding: 0; 
            background-color: #f4f4f4;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff;
        }
        .header { 
            background-color: #009ef7; 
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content { 
            padding: 30px 20px; 
        }
        .feedback-box {
            background-color: #f9f9f9;
            border-left: 4px solid #009ef7;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .avaliacoes-box {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .avaliacoes-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .avaliacoes-box li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .avaliacoes-box li:last-child {
            border-bottom: none;
        }
        .estrelas {
            color: #ffc700;
            font-size: 18px;
        }
        .button { 
            display: inline-block; 
            background-color: #009ef7; 
            color: white; 
            padding: 14px 30px; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 20px 0; 
            font-weight: bold;
        }
        .button:hover {
            background-color: #0088d1;
        }
        .info-badge {
            display: inline-block;
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            margin: 5px 5px 5px 0;
        }
        .footer { 
            text-align: center; 
            padding: 20px; 
            color: #666; 
            font-size: 12px; 
            background-color: #f9f9f9;
            border-top: 1px solid #e0e0e0;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Novo Feedback Recebido</h1>
        </div>
        <div class="content">
            <p>Olá, <strong>{nome_completo}</strong>!</p>
            <p>Você recebeu um novo feedback de <strong>{remetente_nome}</strong>.</p>
            
            {avaliacoes}
            
            <div class="feedback-box">
                <p style="margin-top: 0;"><strong>Conteúdo do Feedback:</strong></p>
                <div style="white-space: pre-wrap; color: #555;">{conteudo}</div>
            </div>
            
            <div style="margin: 20px 0;">
                {anonimo}{presencial}
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{link_feedback}" class="button">Ver Feedback Completo</a>
            </div>
            
            <p style="color: #666; font-size: 14px; margin-top: 30px;">
                <em>Este é um email automático do sistema RH Privus. Se você não esperava receber este feedback, entre em contato com o RH.</em>
            </p>
        </div>
        <div class="footer">
            <p><strong>RH Privus</strong></p>
            <p>Este é um email automático, por favor não responda.</p>
            <p>&copy; 2024 RH Privus - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>',
'Olá {nome_completo}!

Você recebeu um novo feedback de {remetente_nome}.

Conteúdo do Feedback:
{conteudo}

Acesse o sistema para ver o feedback completo: {link_feedback}

Este é um email automático do sistema RH Privus.
© 2024 RH Privus - Todos os direitos reservados',
1,
'["nome_completo", "remetente_nome", "conteudo", "avaliacoes", "link_feedback", "anonimo", "presencial"]',
'Enviado automaticamente quando um colaborador recebe um feedback de outro colaborador.')
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao),
    ativo = VALUES(ativo);

