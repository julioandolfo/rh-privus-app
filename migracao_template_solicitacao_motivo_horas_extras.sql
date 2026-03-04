-- ============================================
-- MIGRAÇÃO: Template de Email para Solicitação de Motivo - Horas Extras
-- ============================================

INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, descricao, ativo, variaveis_disponiveis)
VALUES (
    'solicitacao_motivo_horas_extras',
    'Solicitação de Motivo - Horas Extras',
    '📝 Motivo Necessário - Horas Extras do dia {data_trabalho}',
    '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
        <div style="background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #333333; margin-top: 0; margin-bottom: 20px;">Olá {nome_completo}!</h2>
            
            <p style="color: #666666; line-height: 1.6; margin-bottom: 20px;">
                O RH da <strong>{empresa_nome}</strong> solicitou mais informações sobre suas horas extras.
            </p>
            
            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #856404; margin-top: 0; margin-bottom: 15px; font-size: 18px;">
                    ⚠️ Detalhes da Solicitação
                </h3>
                <ul style="color: #333333; line-height: 1.8; margin: 0; padding-left: 20px;">
                    <li><strong>Data do Trabalho:</strong> {data_trabalho}</li>
                    <li><strong>Quantidade de Horas:</strong> {quantidade_horas}</li>
                    <li><strong>Motivo Atual:</strong> {motivo_atual}</li>
                </ul>
            </div>
            
            <div style="background-color: #f8f9fa; border: 1px dashed #ffc107; padding: 15px; margin-bottom: 20px;">
                <h4 style="color: #856404; margin-top: 0; margin-bottom: 10px;">O que o RH precisa saber:</h4>
                <p style="color: #333333; line-height: 1.6; margin: 0;">{observacao_rh}</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{url_adicionar_motivo}" 
                   style="background-color: #ffc107; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                    📝 Adicionar Motivo
                </a>
            </div>
            
            <p style="color: #666666; line-height: 1.6; margin-bottom: 20px; font-size: 12px;">
                Ou copie e cole este link no seu navegador:<br>
                <span style="color: #0d6efd;">{url_adicionar_motivo}</span>
            </p>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="color: #999999; font-size: 12px; margin: 0; text-align: center;">
                    Este é um email automático do sistema RH Privus. Por favor, não responda este email.
                </p>
                <p style="color: #999999; font-size: 12px; margin: 5px 0 0 0; text-align: center;">
                    {ano_atual} RH Privus - Todos os direitos reservados
                </p>
            </div>
        </div>
    </div>',
    'Olá {nome_completo}!

O RH da {empresa_nome} solicitou mais informações sobre suas horas extras.

Detalhes da Solicitação:
- Data do Trabalho: {data_trabalho}
- Quantidade de Horas: {quantidade_horas}
- Motivo Atual: {motivo_atual}

O que o RH precisa saber:
{observacao_rh}

Para adicionar o motivo, acesse:
{url_adicionar_motivo}

Este é um email automático do sistema RH Privus.
{ano_atual} RH Privus - Todos os direitos reservados',
    'Enviado quando o RH solicita mais informações sobre o motivo de uma solicitação de horas extras.',
    1,
    '["nome_completo", "data_trabalho", "quantidade_horas", "motivo_atual", "observacao_rh", "empresa_nome", "url_adicionar_motivo", "ano_atual"]'
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    corpo_texto = VALUES(corpo_texto),
    descricao = VALUES(descricao),
    variaveis_disponiveis = VALUES(variaveis_disponiveis);

