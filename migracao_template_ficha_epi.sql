-- Migração: Template Ficha de Entrega de EPI
-- Variáveis utilizadas: empresa.razao_social, empresa.cnpj, colaborador.nome_completo,
-- colaborador.cnpj, colaborador.cargo_nome, data_formatada

INSERT INTO contratos_templates (nome, descricao, conteudo_html, variaveis_disponiveis, ativo, criado_por_usuario_id)
VALUES (
    'Ficha de Entrega de EPI',
    'Ficha para registro de entrega de Equipamentos de Proteção Individual (EPIs) ao prestador de serviço PJ.',
    '<div style="font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px;">

    <h1 style="text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 30px; text-transform: uppercase;">
        FICHA DE ENTREGA DE EPI
    </h1>

    <p style="margin-bottom: 3px;">
        <strong>Empresa:</strong> {{empresa.razao_social}}
    </p>
    <p style="margin-bottom: 20px;">
        <strong>CNPJ:</strong> {{empresa.cnpj}}
    </p>

    <table style="width: 100%; margin-bottom: 5px; border: none;">
        <tr>
            <td style="width: 65%; padding: 3px 0;">
                <strong>Nome do Prestador de Serviço:</strong> {{colaborador.nome_completo}}
            </td>
            <td style="width: 35%; padding: 3px 0;">
                <strong>CNPJ:</strong> {{colaborador.cnpj}}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding: 3px 0;">
                <strong>Função:</strong> {{colaborador.cargo_nome}}
            </td>
        </tr>
    </table>

    <p style="text-align: justify; margin: 20px 0;">
        Declaro que recebi gratuitamente os Equipamentos de Proteção Individual (EPIs) abaixo relacionados, em perfeito estado de conservação, e fui orientado quanto ao uso correto, guarda e conservação.
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f5f5f5; width: 12%;">Data</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f5f5f5; width: 28%;">EPI</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f5f5f5; width: 12%;">CA</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f5f5f5; width: 15%;">Quantidade</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f5f5f5; width: 33%;">Assinatura</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">Luvas nitrílicas</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">Óculos de proteção incolor</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">Protetor auricular</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">Máscara PFF2 ou PFF3 (com válvula)</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border: 1px solid #000; padding: 8px; height: 35px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
                <td style="border: 1px solid #000; padding: 8px;">&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <p style="text-align: justify; margin-bottom: 30px;">
        Declaro estar ciente de que o uso dos EPIs é obrigatório durante a execução das atividades.
    </p>

    <div style="margin-top: 40px;">
        <p style="margin-bottom: 5px;">
            Assinatura do Prestador de Serviço: ________________________________________
        </p>
        <p style="margin-bottom: 25px;">
            CNPJ: {{colaborador.cnpj}}
        </p>

        <p style="margin-bottom: 5px;">
            Responsável pela entrega: ________________________________________
        </p>
        <p style="margin-bottom: 5px;">
            Cargo: ________________________________________
        </p>
    </div>

</div>',
    '["empresa.razao_social", "empresa.cnpj", "colaborador.nome_completo", "colaborador.cnpj", "colaborador.cargo_nome"]',
    1,
    1
);
