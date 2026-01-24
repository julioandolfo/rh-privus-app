-- Migração: Template de Contrato de Prestação de Serviços PJ
-- Execute este script para adicionar o template padrão

-- Primeiro, verifica se precisa adicionar colunas de endereço na tabela empresas
SET @dbname = DATABASE();
SET @tablename = 'empresas';

-- Adiciona endereco se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'endereco') > 0,
    'SELECT 1',
    'ALTER TABLE empresas ADD COLUMN endereco VARCHAR(500) NULL AFTER email'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona bairro se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'bairro') > 0,
    'SELECT 1',
    'ALTER TABLE empresas ADD COLUMN bairro VARCHAR(100) NULL AFTER endereco'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona cep se não existir
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'cep') > 0,
    'SELECT 1',
    'ALTER TABLE empresas ADD COLUMN cep VARCHAR(10) NULL AFTER bairro'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insere o template de Contrato de Prestação de Serviços
INSERT INTO contratos_templates (nome, descricao, conteudo_html, variaveis_disponiveis, ativo, criado_por_usuario_id) 
VALUES (
    'Contrato de Prestação de Serviços PJ',
    'Contrato padrão para prestadores de serviço pessoa jurídica, incluindo anexos de descritivo de serviços e confidencialidade.',
    '<div style="font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.5; max-width: 800px; margin: 0 auto; padding: 20px;">
    
    <h1 style="text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 30px; text-transform: uppercase;">
        CONTRATO DE PRESTAÇÃO DE SERVIÇOS
    </h1>
    
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>{{empresa.razao_social}}</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob nº <strong>{{empresa.cnpj}}</strong>, estabelecida na {{empresa.endereco}}, {{empresa.cidade}}/{{empresa.estado}}, CEP {{empresa.cep}}, neste ato, representada na forma de seu contrato social ("<strong>CONTRATANTE</strong>"); e
    </p>
    
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>{{colaborador.nome_completo}}</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob nº <strong>{{colaborador.cnpj}}</strong>, estabelecida {{colaborador.endereco_completo}}, neste ato, representada na forma de seu contrato social, ("<strong>CONTRATADA</strong>"); estabelecem entre si os seguintes entendimentos:
    </p>
    
    <p style="text-align: justify; margin-bottom: 20px;">
        As partes acima identificadas têm, entre si, justo e acertado o presente CONTRATO DE PRESTAÇÃO DE SERVIÇOS, que se regerá pelas cláusulas seguintes e pelas condições descritas no presente.
    </p>
    
    <!-- CLÁUSULA 1 - OBJETO -->
    <p style="text-align: justify; margin-bottom: 10px;">
        <strong>1. Objeto:</strong> O presente contrato possui como objeto a prestação dos serviços discriminados e detalhados no ANEXO I, os quais serão executados pela CONTRATADA em favor da CONTRATANTE.
    </p>
    
    <!-- CLÁUSULA 2 - OBRIGAÇÕES DA CONTRATADA -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>2. Obrigações da contratada:</strong> Além das obrigações previstas em Lei e/ou nas demais Cláusulas deste Contrato, constituem obrigações específicas da CONTRATADA:
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.1. Planejar, conduzir e executar os serviços, com integral observância das disposições deste Contrato, obedecendo rigorosamente aos prazos contratuais, às normas vigentes e os requerimentos gerais que forem formulados, por escrito, pela CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.2. Executar os serviços, objetos deste Contrato, com a melhor técnica aplicável a trabalhos desta natureza, com zelo, diligência e rigorosa observância das prescrições legais e às especificações fornecidas pela CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.3. Respeitar o código de conduta da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.4. Admitir e dirigir, sob sua inteira responsabilidade, pessoal especializado e capacitado, correndo por sua conta exclusiva quaisquer demandas trabalhistas, previdenciárias, sobre acidentes do trabalho ou de qualquer outra natureza atinentes ao pessoal utilizado na prestação dos serviços, sob sua responsabilidade, mantendo a CONTRATANTE isenta de qualquer responsabilidade.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.5. É de responsabilidade da CONTRATADA o ressarcimento de qualquer valor que a CONTRATANTE tenha que arcar em eventual demanda judicial ou administrativa, devidamente corrigido desde a data do efetivo desembolso, que for envolvida em decorrência da presente prestação de serviços. Caso não haja pagamento espontâneo, a CONTRATANTE se resguarda ao direito à retenção de pagamentos e ação de regresso.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.6. A CONTRATADA deverá informar à CONTRATANTE toda e qualquer mudança em seu quadro de colaboradores, bem como comprovar a aptidão deste para a execução do serviço.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.7. Responsabilizar-se por todos e quaisquer danos que vier a sofrer por si, seus empregados ou prepostos, em seus bens materiais, corporais, psíquicos e/ou morais, bem como àqueles sofridos pela CONTRATANTE, seja nos seus equipamentos e/ou equipamentos de clientes da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.8. Desde já, a CONTRATADA aceita sem restrições que sejam descontados dos pagamentos devidos pela CONTRATANTE à CONTRATADA, os valores relativos aos danos causados e devidamente comprovados pela CONTRATADA à CONTRATANTE, sem prejuízos das demais penalidades cabíveis.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.9. A CONTRATADA deverá encaminhar à CONTRATANTE até o dia 20 de cada mês os documentos que se referem ao cumprimento dos encargos trabalhistas e contribuições previdenciárias do mês anterior.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.10. Demais documentos que a CONTRATANTE solicitar esporadicamente deverão ser entregues pela CONTRATADA em até 5 (cinco) dias úteis após a solicitação.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        2.10.1. A não apresentação de qualquer documento ou a apresentação de documentos equivocados, vencidos ou com qualquer outro vício, faculta à CONTRATANTE a retenção dos valores devidos até a sua correta apresentação.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        2.10.2. A não atenção aos prazos descritos permitirá à CONTRATANTE a rescisão do presente contrato.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        2.11. Responsabilizar-se por todos os tributos que incidam ou venham a incidir sobre o presente Contrato, inclusive, no tocante ao recolhimento previdenciário (INSS), bem como todas as despesas com alimentação, hospedagem e quaisquer outras que se fizerem necessárias para a execução dos serviços.
    </p>
    
    <!-- CLÁUSULA 3 - OBRIGAÇÕES DA CONTRATANTE -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>3. Obrigações da contratante:</strong> Além das obrigações previstas em Lei e/ou nas demais Cláusulas deste Contrato, constituem obrigações específicas da CONTRATANTE:
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.1. Pagar o preço na forma e condições pactuadas na Cláusula Sexta do presente contrato.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.2. Fornecer todas as informações necessárias ao bom desempenho da execução dos serviços pela CONTRATADA.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.3. Comunicar previamente à CONTRATADA qualquer modificação e/ou criação de novos procedimentos a serem adotados, bem como todo e qualquer evento que possa ou venha a causar qualquer modificação nos prazos e entregáveis ora definidos.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.4. Fiscalizar e auditar as atividades da CONTRATADA, a fim de diagnosticar e comprovar a eficiência dos serviços prestados, com o objetivo de assegurar que o objeto pelas quais a presente contratação se deu, foi ou será alcançada a contento.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.5. O direito de fiscalização e auditoria a que se refere o item acima não exime a CONTRATADA da responsabilidade legal pela execução dos serviços contratados, mantendo-se obrigada por todo e qualquer dano que eventualmente venha causar, seja por negligência, imperícia ou imprudência durante a vigência do presente instrumento, e após esse, pelo prazo que a lei determina.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.6. Relatar à CONTRATADA por escrito, toda e qualquer irregularidade ou comentários nos serviços prestados.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 15px;">
        3.6.1. Caso a irregularidade eventualmente constatada seja de exclusiva responsabilidade da CONTRATADA, essa se compromete, desde já, na sua adequação e/ou reexecução sem qualquer ônus para a CONTRATANTE, as quais, inclusive, poderão reter os respectivos pagamentos, caso a CONTRATADA se mantenha inadimplente até o mês seguinte ao da notificação de irregularidade, desde que o prazo dado para sua execução não seja superior à esse.
    </p>
    
    <!-- CLÁUSULA 4 - PRAZO E RENOVAÇÃO -->
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>4. Prazo e renovação:</strong> O presente contrato é celebrado pelo prazo de 12 (doze) meses, contados a partir da data de sua assinatura, podendo ser renovado automaticamente por períodos iguais e sucessivos.
    </p>
    
    <!-- CLÁUSULA 5 - EXECUÇÃO -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>5. Execução:</strong> A CONTRATADA está autorizada a prestar serviços através de terceiro, devidamente habilitado e com expertise compatível a plena e eficaz execução do serviço, às suas expensas e sob sua total responsabilidade, ficando o mesmo responsável por qualquer dano causado.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        5.1. O terceiro indicado passará por avaliação da CONTRATANTE antes de iniciar a prestação dos serviços, com o intento de verificar se há expertise necessária para a execução da atividade.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        5.2. O terceiro indicado pela CONTRATADA deverá seguir as mesmas regras constantes deste instrumento, no que se refere aos procedimentos operacionais da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        5.3. É de responsabilidade da CONTRATADA que o terceiro esteja habilitado perante os órgãos necessários para sua atuação regular.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        5.4. A CONTRATADA prestará atendimento ao CONTRATANTE em horário comercial, preferencialmente, das 08:00 às 18:00 horas de segunda à quinta-feira e às sextas-feiras das 08:00 às 17:00, contudo, poderá ser requerido pela CONTRATANTE, em se fazendo necessário, dada a especialidade do serviço prestado, que a CONTRATADA venha prestar serviços em horários diversos dos estipulados, sem ônus para a CONTRATANTE.
    </p>
    
    <!-- CLÁUSULA 6 - PREÇO E FORMA DE PAGAMENTO -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>6. Preço e forma de pagamento:</strong> Como preço pela prestação de serviços objeto deste contrato, a CONTRATANTE pagará individualmente à CONTRATADA os valores estipulados no ANEXO I, junto ao descritivo analítico de suas atividades, os quais seguirão as seguintes condições:
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        6.1. Para que os pagamentos sejam liberados, a CONTRATADA deve encaminhar até o dia 5° dia útil de cada mês, a respectiva nota fiscal, com vencimento para todo dia 5° do mês;
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        6.2. Caso o dia do mês em questão caia num domingo ou feriado, o pagamento será efetuado no dia útil imediatamente consecutivo;
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        6.3. Nenhum outro pagamento será devido pela prestação de serviços, além dos itens que serviram de base para a fixação do valor ajustado e aceito por ambas as partes;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 15px;">
        6.3.1. No entanto, eventuais serviços poderão ser negociados pelas partes para que sejam prestados pela CONTRATADA em locais diversos de sua sede, ou da sede da CONTRATANTE e, caso assim o sejam, as partes negociarão, caso a caso, os valores pelo transporte, estadia e alimentação que eventualmente poderão ser suportados pela CONTRATANTE;
    </p>
    
    <!-- CLÁUSULA 7 - DESPESAS -->
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>7. Despesas:</strong> Qualquer despesa relativa à prestação do serviço, como deslocamento, computadores e similares, mas não se limitando à estas, e especialmente as decorrentes de contratação de mão-de-obra, inclusive contribuições previdenciárias e encargos trabalhistas, são de exclusiva responsabilidade da CONTRATADA. Nenhum outro pagamento será devido pela prestação de serviços, além dos valores ora estipulados e aceitos em comum acordo.
    </p>
    
    <!-- CLÁUSULA 8 - TRIBUTOS -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>8. Tributos:</strong> Os tributos que forem devidos em decorrência direta ou indireta do presente contrato e/ou de sua execução, constituem ônus de responsabilidade exclusiva da CONTRATADA, ficando expressamente vedado o seu repasse para a CONTRATANTE, inclusive o Imposto Sobre Serviços de Qualquer Natureza que deverá estar considerado e acordado na época da realização dos serviços.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        8.1. Eventuais modificações, para mais ou para menos, na alíquota de qualquer tributo ou encargo incidente ou que venha a incidir sobre os serviços ora contratados, bem como a criação, modificação, eliminação ou substituição de tributos e/ou encargos, fatores estes que, de qualquer forma, influam ou venham a comprovadamente influir nos preços dos serviços contratados, serão objeto de novos ajustes entre as partes.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        8.2. Se, por qualquer previsão legal, uma das partes for responsável pelo pagamento de qualquer tributo que tenha a outra parte como contribuinte, o valor deverá ser restituído pela parte devedora à parte credora no prazo de até cinco dias úteis.
    </p>
    
    <!-- CLÁUSULA 9 - CLÁUSULA PENAL -->
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>9. Cláusula penal:</strong> A parte que infringir qualquer das cláusulas ora pactuadas, além de provocar a rescisão do presente contrato, pagará à outra multa equivalente a 01 (um) mês do valor correspondente a remuneração paga ao CONTRATADO, sem prejuízo de perdas e danos verificados.
    </p>
    
    <!-- CLÁUSULA 10 - RESCISÃO -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>10. Rescisão:</strong> O presente contrato pode ser rescindido a qualquer tempo, sem pagamento de multa por qualquer uma das partes, desde que haja comunicação escrita com antecedência mínima 30 dias. O não cumprimento desta cláusula implica em pagamento de multa prevista na Cláusula Nona.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        10.1. A CONTRATANTE poderá rescindir o presente contrato, independentemente de qualquer notificação judicial ou extrajudicial, nas seguintes hipóteses:
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        10.1.1. Inadimplência reiterada de qualquer cláusula ou condição do presente Contrato;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        10.1.2. Paralisação dos serviços sem justa causa e/ou prévia comunicação à CONTRATANTE;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        10.1.3. Cometimento reiterado de falhas na execução dos serviços;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        10.1.4. Quando de serviços mal executados e, neste caso, a CONTRATADA terá que arcar com eventuais ônus que a CONTRATANTE venha a ter em consequência dos serviços mal executados;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        10.1.5. Por imperícia, negligência ou imprudência na execução do serviço, pelo CONTRATADO ou por pessoa sob suas ordens e direção;
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 15px;">
        10.1.6. Atraso injustificado na conclusão dos serviços;
    </p>
    
    <!-- CLÁUSULA 11 - CONFIDENCIALIDADE -->
    <p style="text-align: justify; margin-bottom: 15px;">
        <strong>11. Confidencialidade e não concorrência:</strong> As regras de confidencialidade e não concorrência estão discriminadas e detalhadas no ANEXO II que é parte integrante desse instrumento.
    </p>
    
    <!-- CLÁUSULA 12 - NATUREZA DO CONTRATO -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>12. Natureza do contrato:</strong> Na conformidade do artigo 5º, da Lei nº 11.442/07, o presente contrato tem natureza comercial e não haverá vínculo empregatício, nem responsabilidade solidária ou subsidiária com o CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        12.1. Caso a CONTRATANTE seja acionada por qualquer pessoa e por qualquer razão vier a responder por atos praticados pela CONTRATADA no desenvolver do presente contrato, desde logo fica pactuado que todas e quaisquer despesas suportadas por esta serão imediatamente ressarcidas pela CONTRATADA.
    </p>
    
    <!-- CLÁUSULA 13 - DISPOSIÇÕES GERAIS -->
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>13. Disposições gerais:</strong> Qualquer omissão ou tolerância por qualquer das Partes com relação às disposições do presente Contrato ou na exigência do cumprimento de quaisquer de suas cláusulas, não afetará de qualquer forma a validade do instrumento e não será considerada como precedente, alteração ou novação de suas cláusulas, nem renúncia do direito de tal Parte previsto neste Contrato de exigir o cumprimento de qualquer de suas disposições.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.1. Caso qualquer termo ou disposição deste Contrato seja considerado ilegal ou inexequível por força de qualquer lei ou política pública, todos os demais termos e disposições deste instrumento permanecerão em pleno vigor.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        13.1.1. Quando da determinação de que qualquer termo ou outra disposição é inválido, ilegal ou incapaz de ser executado, as Partes irão negociar de boa-fé com vistas a fazer valer o intento original deste contrato na máxima medida possível;
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.2. Fica facultado a CONTRATANTE o direito de auditar, a qualquer tempo, toda a documentação da CONTRATADA relativa aos recolhimentos retro mencionados.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.3. A CONTRATADA poderá utilizar-se das instalações da CONTRATANTE para execução dos serviços.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.4. Toda e qualquer tolerância quanto ao descumprimento ou cumprimento irregular das obrigações aqui previstas, por qualquer das Partes, não constituirá novação ou alteração das disposições ora pactuadas, mas tão somente liberalidade.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.5. É vedado a qualquer das Partes ceder ou transferir os direitos e obrigações oriundas do presente contrato, sem o prévio e expresso consentimento da outra Parte.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.6. Este contrato obriga as partes, seus sucessores e cessionários a qualquer título.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.7. As partes elegem o Foro da Comarca de {{empresa.cidade}}/{{empresa.estado}}, para dirimir todas as questões decorrentes do presente Contrato, com renúncia a qualquer outro, por mais privilegiado que seja.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        13.8. Este contrato transmite a vontade final das partes e regulamenta toda e qualquer negociação entre elas e eventuais modificações/reajustes terão que ser negociados e formalizados através de termo aditivo a ser assinado entre as partes.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 20px;">
        13.9. Toda e qualquer alteração ao presente instrumento somente será admitida mediante mútuo acordo entre as partes o qual deverá ser feito por meio de aditivo por escrito e anexo ao presente contrato.
    </p>
    
    <p style="text-align: justify; margin-bottom: 30px;">
        E, por estarem assim justas e pactuadas, as Partes assinam o presente instrumento em 02 (duas) vias de igual teor e forma, na presença das testemunhas abaixo, para que produza seus jurídicos e legais efeitos.
    </p>
    
    <p style="text-align: center; margin-bottom: 50px;">
        {{empresa.cidade}}/{{empresa.estado}}, {{data_formatada}}.
    </p>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 50px;">
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{empresa.razao_social}}</strong>
            </div>
        </div>
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{colaborador.nome_completo}}</strong>
            </div>
        </div>
    </div>
    
    <p style="margin-bottom: 30px;"><strong>TESTEMUNHAS:</strong></p>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 50px;">
        <div style="width: 45%;">
            <p>1. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
        <div style="width: 45%;">
            <p>2. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- ANEXO I -->
    <!-- ============================================ -->
    <div style="page-break-before: always;"></div>
    
    <h1 style="text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 10px; text-transform: uppercase;">
        CONTRATO DE PRESTAÇÃO DE SERVIÇOS
    </h1>
    
    <h2 style="text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 30px;">
        ANEXO I
    </h2>
    
    <h3 style="text-align: center; font-size: 11pt; margin-bottom: 20px;">
        DESCRITIVO ANALÍTICO DOS SERVIÇOS QUE SERÃO PRESTADOS PELA CONTRATADA
    </h3>
    
    <p style="text-align: justify; margin-bottom: 10px;">
        <strong>1.</strong> Em decorrência do disposto na Cláusula "1. OBJETO", serão detalhados abaixo os serviços que a CONTRATADA se responsabiliza pela execução, nos termos do contrato acima ratificado:
    </p>
    
    <div style="margin-left: 20px; margin-bottom: 20px;">
        {{contrato.descricao_funcao}}
    </div>
    
    <p style="text-align: justify; margin-bottom: 10px;">
        Em observância ao disposto na Cláusula "6. PREÇO E FORMA DE PAGAMENTO", serão detalhados abaixo os valores a serem pagos pela CONTRATANTE à CONTRATADA:
    </p>
    
    <p style="text-align: justify; margin-bottom: 30px; margin-left: 20px;">
        <strong>2.</strong> A CONTRATANTE pagará o montante de <strong>{{colaborador.salario}}</strong> ({{colaborador.salario_extenso}}), mensais, iguais e consecutivos.
    </p>
    
    <p style="text-align: center; margin-bottom: 50px;">
        {{empresa.cidade}}/{{empresa.estado}}, {{data_formatada}}.
    </p>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 50px;">
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{empresa.razao_social}}</strong>
            </div>
        </div>
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{colaborador.nome_completo}}</strong>
            </div>
        </div>
    </div>
    
    <p style="margin-bottom: 30px;"><strong>TESTEMUNHAS:</strong></p>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 50px;">
        <div style="width: 45%;">
            <p>1. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
        <div style="width: 45%;">
            <p>2. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- ANEXO II -->
    <!-- ============================================ -->
    <div style="page-break-before: always;"></div>
    
    <h1 style="text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 10px; text-transform: uppercase;">
        CONTRATO DE PRESTAÇÃO DE SERVIÇOS
    </h1>
    
    <h2 style="text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 30px;">
        ANEXO II
    </h2>
    
    <h3 style="text-align: center; font-size: 11pt; margin-bottom: 20px;">
        REGRAS DE CONFIDENCIALIDADE E NÃO CONCORRÊNCIA
    </h3>
    
    <p style="text-align: justify; margin-bottom: 15px;">
        As partes resolvem firmar as regras de confidencialidade e não concorrência, mediante as seguintes condições:
    </p>
    
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>1. COMPROMISSO:</strong>
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        1.1. Toda e qualquer informação técnica, administrativa ou comercial, transmitida verbalmente ou por escrito, que a CONTRATADA venha a ter acesso durante a prestação de serviços, ou que tenha sido fornecida pela CONTRATANTE à CONTRATADA para esse fim, será considerada como estritamente confidencial pela CONTRATADA, que se obriga a não revelar a terceiros e deverá ser utilizada única e exclusivamente para os serviços contratados.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 5px;">
        1.1.1. Para os fins deste instrumento, as informações mencionadas incluem, mas não se limitam, a todas as descobertas, ideias, conceitos, know-how, técnicas, código fonte, guides de marca e desenvolvimento de produtos, design, especificações, desenhos, diagramas, modelos, amostras, balancetes, dados, programas de computador, informações constantes de bancos de dados, informações técnicas, financeiras, e comerciais.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 15px;">
        1.1.2. Também será considerada "informação confidencial", toda e qualquer informação desenvolvida pela CONTRATADA que contenha, em parte ou na íntegra, a informação revelada pela CONTRATANTE.
    </p>
    
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>2. RESULTADOS:</strong>
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.1. A CONTRATADA reconhece que todo trabalho realizado para a CONTRATANTE está sujeita à direção e controle da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        2.2. Toda Informação proprietária desenvolvida, criada, inventada, concebida ou descoberta pela CONTRATADA que está sujeito a direito autoral são explicitamente consideradas pela CONTRATADA e CONTRATANTE como "Trabalhos por Contratação" e são de propriedade da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        2.3. Para os efeitos dessa cláusula, "Informação Proprietária" deve incluir, mas não limitar a qualquer informação, observação, dado, material escrito, registro, documento, rascunho, fotografia, layout, programa de computador, software, multimídia, firmware, invenção, descoberta, melhoria, desenvolvimento, ferramental, máquina, aparato, aparelho, design, trabalho de autoria, logo, sistema, ideia promocional, lista de consumidores, necessidades de consumidores, prática, informação de preço, processo, teste, conceito, fórmula, método, informação de mercado, técnica, segredo de mercado, produto e/ou pesquisa relacionada à atual ou antecipada pesquisa de desenvolvimento, produtos, organização, marketing, propaganda, negócios ou finanças da CONTRATANTE, seus afiliados ou entidades relacionadas.
    </p>
    
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>3. OBRIGAÇÕES:</strong>
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.1. A CONTRATADA se obriga em não utilizar das informações para gerar, prospectar, elaborar ou desenvolver projetos, produtos e/ou serviços que sejam similares ou que façam concorrência no mercado direto ou tangente com aqueles de propriedade da CONTRATANTE e dos produtos ou serviços, respectivamente, desenvolvidos ou prestados pelos seus clientes, ou ainda permitir ou facilitar que terceiros venham a elaborar tais ações, nisto incluindo, mas não se limitando, aos produtos e serviços hoje conhecidos e/ou citados.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.2. É vedada a cópia ou qualquer outra forma de reprodução destas informações, exceto para o cumprimento de obrigações estabelecidas nos termos deste instrumento e de acordo com a legislação aplicável relativamente a direitos autorais e propriedade intelectual.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.3. É vedado à CONTRATADA revelar qualquer Informação Confidencial para terceiros que não sejam colaboradores da CONTRATANTE.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        3.4. A CONTRATADA não deve remover o material confidencial, proprietário ou documentos sem autorização escrita. Imediatamente quando solicitado pela CONTRATANTE, a CONTRATADA deve devolver à CONTRATANTE todo material confidencial ou propriedade proprietária ou documentos.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 15px;">
        3.5. Quando do término ou rescisão do presente contrato, a CONTRATADA obriga-se a devolver imediatamente à CONTRATANTE, todo e qualquer documento, material e equipamento entregue a ela para execução dos serviços, sob pena de indenização por perdas e danos.
    </p>
    
    <p style="text-align: justify; margin-bottom: 5px;">
        <strong>4. RESPONSABILIDADE PÓS-CONTRATO:</strong>
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        4.1. Após o término do relacionamento das partes, a CONTRATADA não deve, direta ou indiretamente, revelar a qualquer pessoa, firma ou corporação os nomes ou endereços de qualquer dos consumidores/clientes da CONTRATANTE ou qualquer outra informação pertencente à eles, ou pedir, solicitar, tomar ou tentar tomar, solicitar, ou levar qualquer consumidor/cliente da CONTRATANTE, seja pra si mesmo ou para qualquer outra pessoa, firma ou corporação.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        4.2. As obrigações previstas neste ANEXO II sobreviverão ao término ou rescisão deste contrato.
    </p>
    <p style="text-align: justify; margin-left: 20px; margin-bottom: 5px;">
        4.3. As partes se obrigam a comunicar e fazer com que os empregados, prepostos, ou terceiros sob sua responsabilidade, cumpram os termos desta Cláusula, impedindo o uso indevido de informações umas das outras.
    </p>
    <p style="text-align: justify; margin-left: 40px; margin-bottom: 30px;">
        4.3.1. As obrigações do CONTRATADA sobre os segredos de mercado e informação confidencial devem continuar em efeito além do período de relacionamento como definido acima e dita obrigação deve ser ligada ao cônjuge/esposa dos sócios do CONTRATADA, seus afiliados, atribuídos, herdeiros, parceiros, administradores, ou outros representantes legais.
    </p>
    
    <p style="text-align: center; margin-bottom: 50px;">
        {{empresa.cidade}}/{{empresa.estado}}, {{data_formatada}}.
    </p>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 50px;">
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{empresa.razao_social}}</strong>
            </div>
        </div>
        <div style="width: 45%; text-align: center;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong>{{colaborador.nome_completo}}</strong>
            </div>
        </div>
    </div>
    
    <p style="margin-bottom: 30px;"><strong>TESTEMUNHAS:</strong></p>
    
    <div style="display: flex; justify-content: space-between;">
        <div style="width: 45%;">
            <p>1. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
        <div style="width: 45%;">
            <p>2. ______________________________________</p>
            <p>Nome: _________________________________</p>
            <p>CPF: __________________________________</p>
        </div>
    </div>
    
</div>',
    '["empresa.razao_social", "empresa.cnpj", "empresa.endereco", "empresa.cidade", "empresa.estado", "empresa.cep", "colaborador.nome_completo", "colaborador.cnpj", "colaborador.endereco_completo", "colaborador.salario", "colaborador.salario_extenso", "contrato.descricao_funcao", "data_formatada"]',
    1,
    1
) ON DUPLICATE KEY UPDATE 
    descricao = VALUES(descricao),
    conteudo_html = VALUES(conteudo_html),
    variaveis_disponiveis = VALUES(variaveis_disponiveis);
