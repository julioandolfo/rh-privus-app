-- Template de Email para Solicitação de Feedback
-- Execute este script para adicionar o template de email de solicitação de feedback

INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
('solicitacao_feedback', 'Solicitação de Feedback', '{solicitante_nome} está solicitando um feedback seu', 
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
        .solicitacao-box {
            background-color: #f9f9f9;
            border-left: 4px solid #009ef7;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box {
            background-color: #fff8e1;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
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
            <h1>📋 Nova Solicitação de Feedback</h1>
        </div>
        <div class="content">
            <p>Olá, <strong>{nome_completo}</strong>!</p>
            <p><strong>{solicitante_nome}</strong> está solicitando que você envie um feedback sobre ele(a).</p>
            
            <div class="solicitacao-box">
                <p style="margin-top: 0;"><strong>Mensagem do solicitante:</strong></p>
                <div style="color: #555;">{mensagem}</div>
            </div>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>⏰ Prazo sugerido:</strong> {prazo}</p>
            </div>
            
            <div style="background-color: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #1976d2;">Como funciona?</h3>
                <p style="margin-bottom: 0;">Você pode aceitar ou recusar esta solicitação. Se aceitar, você será direcionado para enviar o feedback. Se recusar, você pode opcionalmente deixar uma mensagem explicando o motivo.</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{link_solicitacao}" class="button">Ver Solicitação</a>
            </div>
            
            <p style="color: #666; font-size: 14px; margin-top: 30px;">
                <em>Este é um email automático do sistema RH Privus. Você pode aceitar ou recusar esta solicitação através do sistema.</em>
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

{solicitante_nome} está solicitando que você envie um feedback sobre ele(a).

Mensagem do solicitante:
{mensagem}

Prazo sugerido: {prazo}

Acesse o sistema para ver a solicitação e responder: {link_solicitacao}

Este é um email automático do sistema RH Privus.
© 2024 RH Privus - Todos os direitos reservados',
1,
'["nome_completo", "solicitante_nome", "mensagem", "prazo", "link_solicitacao"]',
'Enviado automaticamente quando um colaborador solicita feedback de outro colaborador.')
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao),
    ativo = VALUES(ativo);
