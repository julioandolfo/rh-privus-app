-- Script de Migração: Templates de Email para Recrutamento e Seleção
-- Execute este script para criar os templates padrão de recrutamento

-- Template: Confirmação de Candidatura
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao) VALUES
('confirmacao_candidatura', 'Confirmação de Candidatura', 'Recebemos sua candidatura para a vaga {vaga_titulo}',
'<h2>Olá {nome_completo}!</h2>
<p>Recebemos sua candidatura para a vaga <strong>{vaga_titulo}</strong>.</p>
<p>Estamos analisando seu perfil e em breve entraremos em contato.</p>
<p><strong>Informações da vaga:</strong></p>
<ul>
    <li><strong>Vaga:</strong> {vaga_titulo}</li>
    <li><strong>Empresa:</strong> {empresa_nome}</li>
    <li><strong>Data da candidatura:</strong> {data_candidatura}</li>
</ul>
<p>Você pode acompanhar o status da sua candidatura através do link abaixo:</p>
<p><a href="{link_acompanhamento}" style="background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Acompanhar Candidatura</a></p>
<p>Ou copie e cole este link no seu navegador:</p>
<p style="word-break: break-all; color: #666;">{link_acompanhamento}</p>
<p>Obrigado pelo seu interesse em fazer parte da nossa equipe!</p>
<p>Atenciosamente,<br>Equipe de Recrutamento</p>',
'Olá {nome_completo}!\n\nRecebemos sua candidatura para a vaga {vaga_titulo}.\n\nEstamos analisando seu perfil e em breve entraremos em contato.\n\nInformações da vaga:\n- Vaga: {vaga_titulo}\n- Empresa: {empresa_nome}\n- Data da candidatura: {data_candidatura}\n\nAcompanhe sua candidatura: {link_acompanhamento}\n\nObrigado pelo seu interesse!\n\nEquipe de Recrutamento',
1,
'Enviado automaticamente quando um candidato se candidata a uma vaga.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), assunto = VALUES(assunto), corpo_html = VALUES(corpo_html), corpo_texto = VALUES(corpo_texto), descricao = VALUES(descricao);

-- Template: Aprovação
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao) VALUES
('aprovacao', 'Aprovação na Seleção', 'Parabéns! Você foi aprovado(a) para a vaga {vaga_titulo}',
'<h2>Parabéns, {nome_completo}!</h2>
<p>Temos o prazer de informar que você foi <strong>aprovado(a)</strong> no processo seletivo para a vaga <strong>{vaga_titulo}</strong>!</p>
<p>Seu perfil se destacou e estamos muito felizes em tê-lo(a) em nossa equipe.</p>
<p><strong>Próximos passos:</strong></p>
<ul>
    <li>Nossa equipe entrará em contato em breve para discutir os detalhes da contratação</li>
    <li>Prepare os documentos necessários para o processo de admissão</li>
    <li>Fique atento(a) ao seu email e telefone para não perder nossa comunicação</li>
</ul>
<p><strong>Informações:</strong></p>
<ul>
    <li><strong>Vaga:</strong> {vaga_titulo}</li>
    <li><strong>Empresa:</strong> {empresa_nome}</li>
    <li><strong>Data da aprovação:</strong> {data_aprovacao}</li>
</ul>
<p>Mais uma vez, parabéns! Estamos ansiosos para trabalhar com você.</p>
<p>Atenciosamente,<br>Equipe de Recrutamento</p>',
'Parabéns, {nome_completo}!\n\nVocê foi APROVADO(A) no processo seletivo para a vaga {vaga_titulo}!\n\nPróximos passos:\n- Nossa equipe entrará em contato em breve\n- Prepare os documentos necessários\n- Fique atento(a) ao seu email e telefone\n\nInformações:\n- Vaga: {vaga_titulo}\n- Empresa: {empresa_nome}\n- Data da aprovação: {data_aprovacao}\n\nParabéns!\n\nEquipe de Recrutamento',
1,
'Enviado quando um candidato é aprovado no processo seletivo.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), assunto = VALUES(assunto), corpo_html = VALUES(corpo_html), corpo_texto = VALUES(corpo_texto), descricao = VALUES(descricao);

-- Template: Rejeição
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao) VALUES
('rejeicao', 'Rejeição na Seleção', 'Atualização sobre sua candidatura - {vaga_titulo}',
'<h2>Olá {nome_completo}!</h2>
<p>Obrigado pelo seu interesse em fazer parte da nossa equipe.</p>
<p>Após análise cuidadosa do seu perfil, infelizmente não poderemos seguir com sua candidatura para a vaga <strong>{vaga_titulo}</strong> no momento.</p>
<p>Queremos que você saiba que valorizamos muito o tempo que dedicou ao nosso processo seletivo.</p>
<p><strong>Informações:</strong></p>
<ul>
    <li><strong>Vaga:</strong> {vaga_titulo}</li>
    <li><strong>Empresa:</strong> {empresa_nome}</li>
    <li><strong>Data da avaliação:</strong> {data_rejeicao}</li>
</ul>
{motivo_rejeicao}
<p>Seu perfil ficará em nosso banco de dados e entraremos em contato caso surjam oportunidades que se adequem ao seu perfil.</p>
<p>Desejamos muito sucesso em sua carreira profissional!</p>
<p>Atenciosamente,<br>Equipe de Recrutamento</p>',
'Olá {nome_completo}!\n\nObrigado pelo seu interesse em fazer parte da nossa equipe.\n\nApós análise cuidadosa, não poderemos seguir com sua candidatura para a vaga {vaga_titulo} no momento.\n\nInformações:\n- Vaga: {vaga_titulo}\n- Empresa: {empresa_nome}\n- Data da avaliação: {data_rejeicao}\n\n{motivo_rejeicao}\n\nSeu perfil ficará em nosso banco de dados para futuras oportunidades.\n\nDesejamos muito sucesso!\n\nEquipe de Recrutamento',
1,
'Enviado quando um candidato é rejeitado no processo seletivo.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), assunto = VALUES(assunto), corpo_html = VALUES(corpo_html), corpo_texto = VALUES(corpo_texto), descricao = VALUES(descricao);

-- Template: Nova Candidatura para Recrutador
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao) VALUES
('nova_candidatura_recrutador', 'Nova Candidatura Recebida', 'Nova candidatura recebida - {vaga_titulo}',
'<h2>Nova Candidatura Recebida</h2>
<p>Uma nova candidatura foi recebida para a vaga <strong>{vaga_titulo}</strong>.</p>
<p><strong>Dados do candidato:</strong></p>
<ul>
    <li><strong>Nome:</strong> {nome_completo}</li>
    <li><strong>Email:</strong> {email}</li>
    <li><strong>Telefone:</strong> {telefone}</li>
    <li><strong>Data da candidatura:</strong> {data_candidatura}</li>
</ul>
<p><strong>Informações da vaga:</strong></p>
<ul>
    <li><strong>Vaga:</strong> {vaga_titulo}</li>
    <li><strong>Empresa:</strong> {empresa_nome}</li>
</ul>
<p><a href="{link_candidatura}" style="background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Ver Candidatura</a></p>
<p>Atenciosamente,<br>Sistema RH Privus</p>',
'Nova Candidatura Recebida\n\nUma nova candidatura foi recebida para a vaga {vaga_titulo}.\n\nDados do candidato:\n- Nome: {nome_completo}\n- Email: {email}\n- Telefone: {telefone}\n- Data: {data_candidatura}\n\nVaga: {vaga_titulo}\nEmpresa: {empresa_nome}\n\nVer candidatura: {link_candidatura}\n\nSistema RH Privus',
1,
'Enviado para recrutadores quando uma nova candidatura é recebida.')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), assunto = VALUES(assunto), corpo_html = VALUES(corpo_html), corpo_texto = VALUES(corpo_texto), descricao = VALUES(descricao);

