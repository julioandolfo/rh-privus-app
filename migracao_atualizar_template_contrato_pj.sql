-- Migração: Atualiza template Contrato Prestação de Serviços PJ
-- Mudanças:
--  1. Cláusula 2.9: "dia 20" -> "dia 01 e 15" (envio de documentos)
--  2. ANEXO I: substitui salário mensal por valor por hora ({{colaborador.valor_hora}})
--  3. ANEXO I: adiciona texto sobre timesheet e pagamento por produtividade

UPDATE contratos_templates
SET conteudo_html = REPLACE(
        conteudo_html,
        '2.9. A CONTRATADA deverá encaminhar à CONTRATANTE até o dia 20 de cada mês os documentos que se referem ao cumprimento dos encargos trabalhistas e contribuições previdenciárias do mês anterior.',
        '2.9. A CONTRATADA deverá encaminhar à CONTRATANTE até o dia 01 e o dia 15 de cada mês os documentos que se referem ao cumprimento dos encargos trabalhistas e contribuições previdenciárias do período anterior.'
    )
WHERE nome = 'Contrato de Prestação de Serviços PJ';

-- Substitui o trecho do ANEXO I (cláusula de pagamento) pelo modelo por hora
UPDATE contratos_templates
SET conteudo_html = REPLACE(
        conteudo_html,
        '<p style="text-align: justify; margin-bottom: 30px; margin-left: 20px;">
        <strong>2.</strong> A CONTRATANTE pagará o montante de <strong>{{colaborador.salario}}</strong> ({{colaborador.salario_extenso}}), mensais, iguais e consecutivos.
    </p>',
        '<p style="text-align: justify; margin-bottom: 10px;">
        <strong>2.</strong> A CONTRATADA será remunerada baseada em regime de valor por hora técnica efetivamente trabalhada, conforme segue:
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.1. A CONTRATANTE pagará à CONTRATADA o valor bruto de <strong>{{colaborador.valor_hora}}</strong> ({{colaborador.valor_hora_extenso}}) por hora de serviço prestado.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.2. A CONTRATADA deverá apresentar, juntamente com a Nota Fiscal mensal, um relatório descritivo (timesheet) das horas dedicadas à execução das atividades previstas no objeto contratual.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 30px;">
        2.3. O pagamento será realizado de acordo com a produtividade e demanda, não havendo garantia de faturamento mínimo mensal, reforçando a natureza eventual e autônoma da prestação.
    </p>'
    )
WHERE nome = 'Contrato de Prestação de Serviços PJ';

-- Atualiza variáveis_disponiveis para incluir valor_hora
UPDATE contratos_templates
SET variaveis_disponiveis = '["empresa.razao_social", "empresa.cnpj", "empresa.endereco", "empresa.cidade", "empresa.estado", "empresa.cep", "colaborador.nome_completo", "colaborador.cnpj", "colaborador.endereco_completo", "colaborador.valor_hora", "colaborador.valor_hora_extenso", "contrato.descricao_funcao", "data_formatada"]'
WHERE nome = 'Contrato de Prestação de Serviços PJ';
