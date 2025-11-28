-- Script de Migração: Template de Email para Envio de Dados de Acesso
-- Execute este script para criar o template de email para envio de dados de acesso ao colaborador

-- Insere template de dados de acesso (ou atualiza se já existir)
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) 
VALUES (
    'dados_acesso',
    'Dados de Acesso ao Sistema',
    'Dados de Acesso ao Sistema - RH Privus',
    '<!DOCTYPE html>
<html lang="pt-BR">
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
            margin: 20px auto; 
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #009ef7 0%, #0066cc 100%);
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .content { 
            padding: 30px; 
            background-color: #ffffff;
        }
        .dados-acesso-box {
            background-color: #f8f9fa;
            border-left: 4px solid #009ef7;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .dados-acesso-box h3 {
            margin-top: 0;
            color: #009ef7;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .dados-acesso-box p {
            margin-bottom: 12px;
            font-size: 14px;
        }
        .dados-acesso-box code {
            background-color: #e9ecef;
            padding: 6px 12px;
            border-radius: 4px;
            font-family: "Courier New", monospace;
            font-size: 14px;
            font-weight: bold;
            color: #212529;
            display: inline-block;
            margin-left: 5px;
        }
        .dados-acesso-box .senha-code {
            font-size: 16px;
            color: #009ef7;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button { 
            display: inline-block; 
            background: linear-gradient(135deg, #009ef7 0%, #0066cc 100%);
            color: white; 
            padding: 14px 35px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 158, 247, 0.3);
        }
        .info-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 0;
            color: #856404;
            font-size: 13px;
        }
        .info-box strong {
            color: #856404;
        }
        .footer { 
            text-align: center; 
            padding: 20px; 
            background-color: #f8f9fa;
            color: #6c757d; 
            font-size: 12px; 
            border-top: 1px solid #e9ecef;
        }
        .dados-pessoais {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .dados-pessoais h4 {
            margin-top: 0;
            color: #495057;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .dados-pessoais ul {
            margin: 0;
            padding-left: 20px;
        }
        .dados-pessoais li {
            margin-bottom: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RH Privus</h1>
        </div>
        <div class="content">
            <p>Olá, <strong>{nome_completo}</strong>!</p>
            <p>Seus dados de acesso ao sistema foram enviados conforme solicitado.</p>
            
            {dados_acesso_html}
            
            <div class="dados-pessoais">
                <h4>Informações do Colaborador</h4>
                <ul>
                    <li><strong>Empresa:</strong> {empresa_nome}</li>
                    <li><strong>Cargo:</strong> {cargo_nome}</li>
                    <li><strong>Setor:</strong> {setor_nome}</li>
                    <li><strong>Data de Início:</strong> {data_inicio}</li>
                    <li><strong>Tipo de Contrato:</strong> {tipo_contrato}</li>
                </ul>
            </div>
            
            <div class="button-container">
                <a href="{url_login}" class="button">Acessar Sistema</a>
            </div>
            
            <div class="info-box">
                <p><strong>⚠️ Importante:</strong> Guarde estas informações com segurança. Se você recebeu uma senha temporária, altere-a após o primeiro acesso. Se esqueceu sua senha, use a opção "Esqueci minha senha" na página de login.</p>
            </div>
            
            <p>Se você não solicitou este acesso, por favor ignore este email ou entre em contato com o departamento de RH.</p>
        </div>
        <div class="footer">
            <p>Este é um email automático, por favor não responda.</p>
            <p>&copy; {ano_atual} RH Privus - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>',
    'Olá {nome_completo}!

Seus dados de acesso ao sistema foram enviados conforme solicitado.

{dados_acesso_texto}

Informações do Colaborador:
- Empresa: {empresa_nome}
- Cargo: {cargo_nome}
- Setor: {setor_nome}
- Data de Início: {data_inicio}
- Tipo de Contrato: {tipo_contrato}

Link de Acesso: {url_login}

⚠️ Importante: Guarde estas informações com segurança. Se você recebeu uma senha temporária, altere-a após o primeiro acesso. Se esqueceu sua senha, use a opção "Esqueci minha senha" na página de login.

Se você não solicitou este acesso, por favor ignore este email ou entre em contato com o departamento de RH.

---
Este é um email automático, por favor não responda.
© {ano_atual} RH Privus - Todos os direitos reservados',
    1,
    '{"nome_completo": "Nome completo do colaborador", "empresa_nome": "Nome da empresa", "cargo_nome": "Nome do cargo", "setor_nome": "Nome do setor", "data_inicio": "Data de início formatada", "tipo_contrato": "Tipo de contrato (CLT, PJ, etc)", "usuario_login": "Login do usuário (CPF ou email)", "senha": "Senha temporária (se gerada)", "url_login": "URL de acesso ao sistema", "dados_acesso_html": "HTML com box de dados de acesso", "dados_acesso_texto": "Texto com dados de acesso"}',
    'Template usado para enviar dados de acesso ao sistema para colaboradores. É acionado quando um administrador solicita o envio de dados de acesso através da página de visualização do colaborador. Pode incluir senha temporária se o colaborador ainda não tiver senha cadastrada.'
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    variaveis_disponiveis = VALUES(variaveis_disponiveis),
    descricao = VALUES(descricao),
    updated_at = CURRENT_TIMESTAMP;

