<?php
/**
 * API para buscar dados completos de colaborador desligado
 * Retorna: nome, salário, data admissão, data desligamento, tipo contrato, saldo banco de horas
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/banco_horas_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$pdo = getDB();
$colaborador_id = (int)($_GET['id'] ?? 0);

if (!$colaborador_id) {
    echo json_encode(['error' => 'ID do colaborador é obrigatório']);
    exit;
}

// Busca dados do colaborador
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.nome_completo,
        c.salario,
        c.data_inicio,
        c.status,
        c.tipo_contrato,
        c.empresa_id
    FROM colaboradores c
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    echo json_encode(['error' => 'Colaborador não encontrado']);
    exit;
}

// Verifica permissão de acesso
if (!can_access_empresa($colaborador['empresa_id'])) {
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

// Busca data de desligamento da tabela demissoes
$stmt = $pdo->prepare("
    SELECT data_demissao 
    FROM demissoes 
    WHERE colaborador_id = ? 
    ORDER BY data_demissao DESC 
    LIMIT 1
");
$stmt->execute([$colaborador_id]);
$demissao = $stmt->fetch(PDO::FETCH_ASSOC);

$data_desligamento = $demissao['data_demissao'] ?? null;

// Busca saldo do banco de horas
$saldo_banco = get_saldo_banco_horas($colaborador_id);
$saldo_horas_total = 0;
if ($saldo_banco) {
    $saldo_horas_total = ($saldo_banco['saldo_horas'] ?? 0) + (($saldo_banco['saldo_minutos'] ?? 0) / 60);
}

// Calcula férias proporcionais (somente CLT)
$ferias_proporcionais_dias = 0;
$valor_ferias_13 = 0;
$meses_13_proporcional = 0;
$valor_13_proporcional = 0;

if ($colaborador['tipo_contrato'] === 'CLT' && $colaborador['data_inicio'] && $data_desligamento) {
    $data_admissao = new DateTime($colaborador['data_inicio']);
    $data_saida = new DateTime($data_desligamento);
    
    // Calcula meses desde a última férias vencida (ou admissão)
    // Simplificação: considera período aquisitivo desde a admissão
    $interval = $data_admissao->diff($data_saida);
    $meses_trabalhados = ($interval->y * 12) + $interval->m;
    
    // Dias de férias proporcionais (2.5 dias por mês trabalhado, máx 30)
    $ferias_proporcionais_dias = min(30, round($meses_trabalhados * 2.5));
    
    // 13º proporcional (meses no ano corrente)
    $ano_desligamento = (int)$data_saida->format('Y');
    $inicio_ano = new DateTime($ano_desligamento . '-01-01');
    
    if ($data_admissao > $inicio_ano) {
        $inicio_contagem = $data_admissao;
    } else {
        $inicio_contagem = $inicio_ano;
    }
    
    $interval_13 = $inicio_contagem->diff($data_saida);
    $meses_13_proporcional = $interval_13->m + 1; // +1 para contar o mês atual se trabalhou mais de 15 dias
    
    // Ajusta se o dia do mês é menor que 15 (não conta como mês completo)
    if ((int)$data_saida->format('d') < 15) {
        $meses_13_proporcional = max(0, $meses_13_proporcional - 1);
    }
    
    $meses_13_proporcional = min(12, $meses_13_proporcional);
    
    // Calcula valores se tem salário
    $salario = (float)($colaborador['salario'] ?? 0);
    if ($salario > 0) {
        // Valor férias + 1/3
        $valor_dia_ferias = $salario / 30;
        $valor_ferias = $valor_dia_ferias * $ferias_proporcionais_dias;
        $valor_ferias_13 = $valor_ferias + ($valor_ferias / 3); // +1/3 constitucional
        
        // Valor 13º proporcional
        $valor_13_proporcional = ($salario / 12) * $meses_13_proporcional;
    }
}

// Calcula saldo de salário (dias não pagos)
// Considera a data de admissão se for no mesmo mês do desligamento
$saldo_salario_dias = 0;
$valor_saldo_salario = 0;

if ($data_desligamento && $colaborador['data_inicio']) {
    $data_saida = new DateTime($data_desligamento);
    $data_admissao = new DateTime($colaborador['data_inicio']);
    
    $dia_desligamento = (int)$data_saida->format('d');
    $dia_admissao = (int)$data_admissao->format('d');
    
    // Verifica se admissão e desligamento são no mesmo mês/ano
    $mesmo_mes = ($data_admissao->format('Y-m') === $data_saida->format('Y-m'));
    
    if ($mesmo_mes) {
        // Se admissão e desligamento no mesmo mês, conta os dias entre eles
        // +1 porque conta o dia de admissão e o dia de desligamento
        $saldo_salario_dias = $dia_desligamento - $dia_admissao + 1;
    } else {
        // Se meses diferentes, conta do início do mês até o desligamento
        $saldo_salario_dias = $dia_desligamento;
    }
    
    // Garante que não seja negativo
    $saldo_salario_dias = max(0, $saldo_salario_dias);
    
    $salario = (float)($colaborador['salario'] ?? 0);
    if ($salario > 0) {
        $valor_saldo_salario = ($salario / 30) * $saldo_salario_dias;
    }
}

// Calcula valor do banco de horas em R$
$valor_hora = 0;
$valor_banco_horas = 0;
$salario = (float)($colaborador['salario'] ?? 0);
if ($salario > 0 && $saldo_horas_total != 0) {
    // Valor da hora = salário / 220 (jornada padrão CLT)
    $valor_hora = $salario / 220;
    $valor_banco_horas = $valor_hora * $saldo_horas_total;
}

echo json_encode([
    'success' => true,
    'colaborador' => [
        'id' => $colaborador['id'],
        'nome' => $colaborador['nome_completo'],
        'salario' => (float)($colaborador['salario'] ?? 0),
        'data_admissao' => $colaborador['data_inicio'],
        'data_desligamento' => $data_desligamento,
        'tipo_contrato' => $colaborador['tipo_contrato'] ?? 'CLT',
        'saldo_banco_horas' => round($saldo_horas_total, 2),
        'valor_banco_horas' => round($valor_banco_horas, 2),
        'valor_hora' => round($valor_hora, 2),
        
        // Cálculos CLT
        'ferias_dias' => $ferias_proporcionais_dias,
        'valor_ferias' => round($valor_ferias_13, 2),
        'meses_13' => $meses_13_proporcional,
        'valor_13' => round($valor_13_proporcional, 2),
        
        // Saldo de salário
        'saldo_salario_dias' => $saldo_salario_dias,
        'valor_saldo_salario' => round($valor_saldo_salario, 2),
    ]
]);
