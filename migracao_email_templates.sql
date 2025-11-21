-- Script de Migração: Cria tabela de templates de email
-- Execute este script para criar a tabela de templates

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único do template (ex: novo_colaborador)',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do template',
    assunto VARCHAR(255) NOT NULL COMMENT 'Assunto do email (pode conter variáveis)',
    corpo_html LONGTEXT NOT NULL COMMENT 'Corpo do email em HTML (pode conter variáveis)',
    corpo_texto TEXT NULL COMMENT 'Versão texto do email (opcional)',
    ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se o template está ativo',
    variaveis_disponiveis TEXT NULL COMMENT 'JSON com lista de variáveis disponíveis',
    descricao TEXT NULL COMMENT 'Descrição do template e quando é usado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere templates padrão
INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, variaveis_disponiveis, descricao) VALUES
('novo_colaborador', 'Novo Colaborador', 'Bem-vindo ao {empresa_nome}!', 
'<h2>Olá {nome_completo}!</h2>
<p>Bem-vindo ao <strong>{empresa_nome}</strong>!</p>
<p>Estamos felizes em tê-lo(a) em nossa equipe.</p>
<p><strong>Dados do seu cadastro:</strong></p>
<ul>
    <li><strong>Cargo:</strong> {cargo_nome}</li>
    <li><strong>Setor:</strong> {setor_nome}</li>
    <li><strong>Data de Início:</strong> {data_inicio}</li>
    <li><strong>Tipo de Contrato:</strong> {tipo_contrato}</li>
</ul>
<p>Se você tiver alguma dúvida, não hesite em entrar em contato conosco.</p>
<p>Bem-vindo(a)!</p>',
'Olá {nome_completo}!\n\nBem-vindo ao {empresa_nome}!\n\nEstamos felizes em tê-lo(a) em nossa equipe.\n\nDados do seu cadastro:\n- Cargo: {cargo_nome}\n- Setor: {setor_nome}\n- Data de Início: {data_inicio}\n- Tipo de Contrato: {tipo_contrato}\n\nBem-vindo(a)!',
1,
'["nome_completo", "empresa_nome", "cargo_nome", "setor_nome", "data_inicio", "tipo_contrato", "cpf", "email_pessoal", "telefone"]',
'Enviado automaticamente quando um novo colaborador é cadastrado no sistema.'),

('nova_promocao', 'Nova Promoção', 'Parabéns! Você recebeu uma promoção!',
'<h2>Parabéns, {nome_completo}!</h2>
<p>Temos o prazer de informar que você recebeu uma promoção!</p>
<p><strong>Detalhes da Promoção:</strong></p>
<ul>
    <li><strong>Data:</strong> {data_promocao}</li>
    <li><strong>Salário Anterior:</strong> R$ {salario_anterior}</li>
    <li><strong>Novo Salário:</strong> R$ {salario_novo}</li>
    <li><strong>Motivo:</strong> {motivo}</li>
</ul>
{observacoes}
<p>Parabéns pelo seu desempenho e dedicação!</p>',
'Parabéns, {nome_completo}!\n\nTemos o prazer de informar que você recebeu uma promoção!\n\nDetalhes:\n- Data: {data_promocao}\n- Salário Anterior: R$ {salario_anterior}\n- Novo Salário: R$ {salario_novo}\n- Motivo: {motivo}\n\nParabéns!',
1,
'["nome_completo", "data_promocao", "salario_anterior", "salario_novo", "motivo", "observacoes", "empresa_nome"]',
'Enviado automaticamente quando uma promoção é registrada para um colaborador.'),

('fechamento_pagamento', 'Fechamento de Pagamento', 'Seu pagamento de {mes_referencia} está disponível',
'<h2>Olá {nome_completo}!</h2>
<p>Informamos que o fechamento do pagamento referente ao mês de <strong>{mes_referencia}</strong> está disponível.</p>
<p><strong>Resumo do Pagamento:</strong></p>
<ul>
    <li><strong>Salário Base:</strong> R$ {salario_base}</li>
    <li><strong>Horas Extras:</strong> {horas_extras} horas - R$ {valor_horas_extras}</li>
    <li><strong>Descontos:</strong> R$ {descontos}</li>
    <li><strong>Adicionais:</strong> R$ {adicionais}</li>
    <li><strong>Valor Total:</strong> R$ {valor_total}</li>
</ul>
<p>Data de Fechamento: {data_fechamento}</p>
{observacoes}
<p>Em caso de dúvidas, entre em contato com o RH.</p>',
'Olá {nome_completo}!\n\nInformamos que o fechamento do pagamento referente ao mês de {mes_referencia} está disponível.\n\nResumo:\n- Salário Base: R$ {salario_base}\n- Horas Extras: {horas_extras} horas - R$ {valor_horas_extras}\n- Descontos: R$ {descontos}\n- Adicionais: R$ {adicionais}\n- Valor Total: R$ {valor_total}\n\nData de Fechamento: {data_fechamento}',
1,
'["nome_completo", "mes_referencia", "salario_base", "horas_extras", "valor_horas_extras", "descontos", "adicionais", "valor_total", "data_fechamento", "observacoes", "empresa_nome"]',
'Enviado automaticamente para cada colaborador quando um fechamento de pagamento é realizado.'),

('ocorrencia', 'Ocorrência Registrada', 'Ocorrência registrada - {tipo_ocorrencia}',
'<h2>Olá {nome_completo}!</h2>
<p>Informamos que foi registrada uma ocorrência em seu nome.</p>
<p><strong>Detalhes da Ocorrência:</strong></p>
<ul>
    <li><strong>Tipo:</strong> {tipo_ocorrencia}</li>
    <li><strong>Data:</strong> {data_ocorrencia}</li>
    {hora_ocorrencia}
    {tempo_atraso}
    <li><strong>Descrição:</strong> {descricao}</li>
    <li><strong>Registrado por:</strong> {usuario_registro}</li>
</ul>
<p>Data/Hora do Registro: {data_registro}</p>
<p>Em caso de dúvidas ou para mais informações, entre em contato com o RH.</p>',
'Olá {nome_completo}!\n\nInformamos que foi registrada uma ocorrência em seu nome.\n\nDetalhes:\n- Tipo: {tipo_ocorrencia}\n- Data: {data_ocorrencia}\n- Descrição: {descricao}\n- Registrado por: {usuario_registro}\n\nData/Hora do Registro: {data_registro}',
1,
'["nome_completo", "tipo_ocorrencia", "data_ocorrencia", "hora_ocorrencia", "tempo_atraso", "descricao", "usuario_registro", "data_registro", "empresa_nome", "setor_nome", "cargo_nome"]',
'Enviado automaticamente quando uma ocorrência é registrada para um colaborador.')
ON DUPLICATE KEY UPDATE codigo = codigo;

