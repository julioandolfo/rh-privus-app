<?php
/**
 * Script para criar/atualizar template de email de horas extras
 */

require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

$template = [
    'codigo' => 'horas_extras',
    'nome' => 'Horas Extras Registradas',
    'assunto' => 'Horas extras registradas - {data_trabalho}',
    'corpo_html' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
        <div style="background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #333333; margin-top: 0; margin-bottom: 20px;">Olá {nome_completo}!</h2>
            
            <p style="color: #666666; line-height: 1.6; margin-bottom: 20px;">
                Informamos que foram registradas horas extras em seu nome.
            </p>
            
            <div style="background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #0d6efd; margin-top: 0; margin-bottom: 15px; font-size: 18px;">Detalhes das Horas Extras</h3>
                <ul style="color: #333333; line-height: 1.8; margin: 0; padding-left: 20px;">
                    <li><strong>Data do Trabalho:</strong> {data_trabalho}</li>
                    <li><strong>Quantidade de Horas:</strong> {quantidade_horas}</li>
                    {tipo_pagamento_html}
                    {valor_hora_html}
                    {percentual_adicional_html}
                    {valor_total_html}
                    {saldo_banco_html}
                    {observacoes_html}
                </ul>
            </div>
            
            <p style="color: #666666; line-height: 1.6; margin-bottom: 10px;">
                <strong>Registrado por:</strong> {usuario_registro}
            </p>
            <p style="color: #666666; line-height: 1.6; margin-bottom: 20px;">
                <strong>Data do Registro:</strong> {data_registro}
            </p>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <p style="color: #999999; font-size: 12px; margin: 0; text-align: center;">
                    Este é um email automático do sistema RH Privus. Por favor, não responda este email.
                </p>
                <p style="color: #999999; font-size: 12px; margin: 5px 0 0 0; text-align: center;">
                    ' . date('Y') . ' RH Privus - Todos os direitos reservados
                </p>
            </div>
        </div>
    </div>',
    'corpo_texto' => 'Olá {nome_completo}!

Informamos que foram registradas horas extras em seu nome.

Detalhes:
- Data do Trabalho: {data_trabalho}
- Quantidade de Horas: {quantidade_horas}
{tipo_pagamento_texto}
{valor_hora_texto}
{percentual_adicional_texto}
{valor_total_texto}
{saldo_banco_texto}
{observacoes_texto}

Registrado por: {usuario_registro}
Data do Registro: {data_registro}

Este é um email automático do sistema RH Privus.
' . date('Y') . ' RH Privus - Todos os direitos reservados',
    'descricao' => 'Enviado quando horas extras são registradas para um colaborador.',
    'ativo' => 1,
    'variaveis_disponiveis' => json_encode([
        'nome_completo',
        'data_trabalho',
        'quantidade_horas',
        'tipo_pagamento',
        'valor_hora',
        'percentual_adicional',
        'valor_total',
        'saldo_banco',
        'observacoes',
        'usuario_registro',
        'data_registro',
        'empresa_nome',
        'setor_nome',
        'cargo_nome',
        'ano_atual'
    ])
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, descricao, ativo, variaveis_disponiveis)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nome = VALUES(nome),
            assunto = VALUES(assunto),
            corpo_html = VALUES(corpo_html),
            corpo_texto = VALUES(corpo_texto),
            descricao = VALUES(descricao),
            variaveis_disponiveis = VALUES(variaveis_disponiveis)
    ");
    
    $stmt->execute([
        $template['codigo'],
        $template['nome'],
        $template['assunto'],
        $template['corpo_html'],
        $template['corpo_texto'],
        $template['descricao'],
        $template['ativo'],
        $template['variaveis_disponiveis']
    ]);
    
    echo "Template de email 'horas_extras' criado/atualizado com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro ao criar template: " . $e->getMessage() . "\n";
    exit(1);
}

