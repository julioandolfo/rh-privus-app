-- ============================================
-- MIGRAÇÃO: FAQs sobre Sistema de Flags
-- Adiciona perguntas e respostas sobre flags ao FAQ do Manual de Conduta
-- ============================================

-- Nota: Este script assume que existe pelo menos um usuário ADMIN com ID = 1
-- Se necessário, ajuste o criado_por para o ID correto do usuário ADMIN

-- Categoria: Flags
-- Ordem: Começando em 100 para não conflitar com FAQs existentes

INSERT INTO faq_manual_conduta (pergunta, resposta, categoria, ordem, ativo, criado_por) VALUES
(
    'O que são flags?',
    'Flags são um mecanismo de controle para estimular a disciplina, a responsabilidade e o alinhamento cultural dentro da empresa. Cada falta não justificada ou má conduta gera 1 flag, que tem validade de 30 dias corridos.',
    'Flags',
    100,
    1,
    1
),
(
    'Quando uma flag é aplicada?',
    'Uma flag é aplicada automaticamente quando você recebe uma ocorrência dos seguintes tipos:<br><br>• <strong>Faltas não justificadas:</strong> Ausência sem aviso prévio ou sem justificativa aceita pelo RH em até 48h.<br>• <strong>Faltas por compromissos pessoais:</strong> Devem ser previamente solicitadas e autorizadas pela liderança ou RH. Caso não haja solicitação ou autorização formal, a ausência será considerada falta não justificada e resultará em flag.<br>• <strong>Má conduta:</strong> Atitudes inadequadas, desrespeitosas ou prejudiciais ao ambiente de trabalho, avaliadas pela liderança, diretoria ou RH.',
    'Flags',
    101,
    1,
    1
),
(
    'Como funciona a validade de 30 dias das flags?',
    'Cada flag tem validade de 30 dias corridos a partir da data em que foi recebida. Se você não receber novas flags nesse período, a flag expira automaticamente. Você pode acompanhar suas flags e suas datas de validade na página "Minhas Flags" do sistema.',
    'Flags',
    102,
    1,
    1
),
(
    'O que acontece se eu receber uma nova flag enquanto outra ainda está ativa?',
    'Se você receber uma nova flag enquanto outra ainda estiver ativa, ambas passam a contar juntas. O sistema renova automaticamente a validade de todas as flags ativas para contar juntas, todas com a mesma data de validade (30 dias a partir da nova flag).',
    'Flags',
    103,
    1,
    1
),
(
    'Como posso verificar minhas flags?',
    'Você pode verificar suas flags acessando o menu "Minhas Flags" no sistema. Lá você verá:<br><br>• Quantas flags ativas você possui<br>• Data em que cada flag foi recebida<br>• Data de validade de cada flag<br>• Tipo de flag (Falta Não Justificada, Má Conduta, etc.)<br>• Link para a ocorrência relacionada<br><br>Também é possível ver um indicador visual na sua página de perfil mostrando quantas flags ativas você possui.',
    'Flags',
    104,
    1,
    1
),
(
    'O que acontece se eu atingir 3 flags ativas ao mesmo tempo?',
    'Se você atingir 3 flags ativas ao mesmo tempo, o sistema emitirá um alerta visual (badge vermelho) e registrará um alerta para o RH. É importante saber que o sistema <strong>não desliga automaticamente</strong> colaboradores com 3 flags. O desligamento é uma decisão que deve ser tomada manualmente pelo RH/ADMIN após análise do caso.',
    'Flags',
    105,
    1,
    1
),
(
    'Como as flags expiram?',
    'As flags expiram automaticamente após 30 dias corridos a partir da data em que foram recebidas. O sistema verifica e expira flags vencidas automaticamente. Quando uma flag expira, ela muda de status para "expirada" e não conta mais para o total de flags ativas. Você pode ver flags expiradas na página "Minhas Flags" para consulta histórica.',
    'Flags',
    106,
    1,
    1
),
(
    'Quais são os tipos de flags?',
    'Existem três tipos de flags no sistema:<br><br>• <strong>Falta Não Justificada:</strong> Aplicada quando há falta sem aviso prévio ou sem justificativa aceita pelo RH em até 48h.<br>• <strong>Falta por Compromisso Pessoal:</strong> Aplicada quando há falta por compromisso pessoal sem solicitação ou autorização formal prévia.<br>• <strong>Má Conduta:</strong> Aplicada em casos de atitudes inadequadas, desrespeitosas ou prejudiciais ao ambiente de trabalho.',
    'Flags',
    107,
    1,
    1
),
(
    'Exemplo prático: Como funciona o sistema de flags?',
    'Vamos usar um exemplo prático:<br><br><strong>01/05:</strong> Você recebe a 1ª flag (ativa até 31/05).<br><strong>20/05:</strong> Você recebe a 2ª flag → agora a 1ª e 2ª ficam ativas até 19/06 (renovadas para contar juntas).<br><strong>15/06:</strong> Você recebe a 3ª flag → todas as três ficam ativas até 15/07 (renovadas novamente). Neste momento, o sistema emite alerta de 3 flags ativas.<br><strong>16/07:</strong> Se você não receber novas flags, todas expiram automaticamente e você volta a ter 0 flags ativas.',
    'Flags',
    108,
    1,
    1
),
(
    'Posso contestar uma flag?',
    'Sim, você pode contestar uma flag através da ocorrência relacionada. Acesse a ocorrência que gerou a flag e entre em contato com o RH ou sua liderança para esclarecimentos. O sistema mantém histórico completo de todas as flags e ocorrências para análise.',
    'Flags',
    109,
    1,
    1
),
(
    'As flags afetam meu salário ou benefícios?',
    'As flags em si não afetam diretamente o salário ou benefícios. No entanto, as ocorrências que geram flags podem ter impactos financeiros (como descontos por faltas ou atrasos) conforme as políticas da empresa. As flags são um mecanismo de controle disciplinar separado dos impactos financeiros das ocorrências.',
    'Flags',
    110,
    1,
    1
),
(
    'Como posso evitar receber flags?',
    'Para evitar receber flags, é importante:<br><br>• <strong>Comunicar faltas com antecedência:</strong> Sempre avise sua liderança ou RH antes de faltar.<br>• <strong>Solicitar autorização:</strong> Para compromissos pessoais, solicite autorização formal antes da ausência.<br>• <strong>Justificar ausências:</strong> Se faltar, justifique junto ao RH em até 48h.<br>• <strong>Manter boa conduta:</strong> Respeite colegas, clientes e regras internas da empresa.<br>• <strong>Evitar negligência:</strong> Seja responsável e evite ações que possam causar prejuízo ou risco à empresa.',
    'Flags',
    111,
    1,
    1
);

-- Atualiza a ordem para garantir que as FAQs de Flags apareçam juntas
UPDATE faq_manual_conduta 
SET ordem = ordem + 200 
WHERE categoria != 'Flags' AND ordem < 200;

