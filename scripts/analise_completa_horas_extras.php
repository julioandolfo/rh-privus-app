<?php
/**
 * Análise completa: onde estão as horas extras disponíveis
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== ANÁLISE COMPLETA DE HORAS EXTRAS DISPONÍVEIS ===\n\n";

$pdo = getDB();

// 1. Lista TODAS as horas extras não pagas
echo "1. TODAS AS HORAS EXTRAS NÃO PAGAS (tipo dinheiro):\n";
echo str_repeat("=", 130) . "\n";

$stmt = $pdo->query("
    SELECT 
        he.id,
        he.colaborador_id,
        c.nome_completo,
        c.empresa_id,
        e.nome_fantasia as empresa_nome,
        he.data_trabalho,
        he.quantidade_horas,
        he.valor_total,
        he.observacoes,
        he.created_at
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    WHERE he.fechamento_pagamento_id IS NULL
    AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
    ORDER BY e.nome_fantasia, c.nome_completo, he.data_trabalho
");

$horas = $stmt->fetchAll();

if (empty($horas)) {
    echo "❌ Nenhuma hora extra disponível!\n\n";
    exit(0);
}

printf("%-3s | %-4s | %-25s | %-20s | %-12s | %-7s | %-10s\n",
    "ID", "Col", "Colaborador", "Empresa", "Data", "Horas", "Valor");
echo str_repeat("-", 130) . "\n";

$por_empresa = [];
$por_colaborador = [];
$por_mes = [];

foreach ($horas as $he) {
    printf("%-3d | %-4d | %-25s | %-20s | %-12s | %5.2fh | R$ %7.2f\n",
        $he['id'],
        $he['colaborador_id'],
        substr($he['nome_completo'], 0, 25),
        substr($he['empresa_nome'] ?? 'SEM EMPRESA', 0, 20),
        date('d/m/Y', strtotime($he['data_trabalho'])),
        $he['quantidade_horas'],
        $he['valor_total']
    );
    
    // Agrupa por empresa
    $emp_id = $he['empresa_id'] ?? 0;
    $emp_nome = $he['empresa_nome'] ?? 'SEM EMPRESA';
    if (!isset($por_empresa[$emp_id])) {
        $por_empresa[$emp_id] = [
            'nome' => $emp_nome,
            'qtd' => 0,
            'horas' => 0,
            'valor' => 0,
            'colaboradores' => []
        ];
    }
    $por_empresa[$emp_id]['qtd']++;
    $por_empresa[$emp_id]['horas'] += $he['quantidade_horas'];
    $por_empresa[$emp_id]['valor'] += $he['valor_total'];
    $por_empresa[$emp_id]['colaboradores'][$he['colaborador_id']] = $he['nome_completo'];
    
    // Agrupa por mês
    $mes = substr($he['data_trabalho'], 0, 7); // YYYY-MM
    if (!isset($por_mes[$mes])) {
        $por_mes[$mes] = ['qtd' => 0, 'horas' => 0, 'valor' => 0];
    }
    $por_mes[$mes]['qtd']++;
    $por_mes[$mes]['horas'] += $he['quantidade_horas'];
    $por_mes[$mes]['valor'] += $he['valor_total'];
}

echo "\n";

// 2. Resumo por empresa
echo "2. RESUMO POR EMPRESA:\n";
echo str_repeat("=", 100) . "\n";
printf("%-5s | %-30s | %-10s | %-12s | %-15s | Colaboradores\n",
    "ID", "Empresa", "Registros", "Horas", "Valor");
echo str_repeat("-", 100) . "\n";

foreach ($por_empresa as $emp_id => $dados) {
    printf("%-5s | %-30s | %10d | %10.2fh | R$ %11.2f | %d colaboradores\n",
        $emp_id ?: 'N/A',
        substr($dados['nome'], 0, 30),
        $dados['qtd'],
        $dados['horas'],
        $dados['valor'],
        count($dados['colaboradores'])
    );
    
    // Lista colaboradores desta empresa
    echo "      Colaboradores: ";
    $nomes = array_values($dados['colaboradores']);
    echo implode(', ', array_map(function($n) { return substr($n, 0, 20); }, $nomes));
    echo "\n";
}

echo "\n";

// 3. Resumo por mês
echo "3. RESUMO POR MÊS:\n";
echo str_repeat("=", 80) . "\n";
printf("%-10s | %-10s | %-12s | %-15s\n",
    "Mês", "Registros", "Horas", "Valor");
echo str_repeat("-", 80) . "\n";

ksort($por_mes);
foreach ($por_mes as $mes => $dados) {
    printf("%-10s | %10d | %10.2fh | R$ %11.2f\n",
        $mes,
        $dados['qtd'],
        $dados['horas'],
        $dados['valor']
    );
}

echo "\n" . str_repeat("=", 130) . "\n";

// 4. Instruções específicas
echo "\n📋 INSTRUÇÕES PARA CRIAR FECHAMENTOS:\n\n";

foreach ($por_empresa as $emp_id => $dados) {
    if ($emp_id == 0) continue; // Pula se não tem empresa
    
    // Descobre qual mês tem mais horas para esta empresa
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(he.data_trabalho, '%Y-%m') as mes,
            COUNT(*) as qtd,
            SUM(he.quantidade_horas) as total_horas
        FROM horas_extras he
        INNER JOIN colaboradores c ON he.colaborador_id = c.id
        WHERE c.empresa_id = ?
        AND he.fechamento_pagamento_id IS NULL
        AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
        GROUP BY DATE_FORMAT(he.data_trabalho, '%Y-%m')
        ORDER BY qtd DESC
        LIMIT 1
    ");
    $stmt->execute([$emp_id]);
    $mes_principal = $stmt->fetch();
    
    if (!$mes_principal) continue;
    
    echo "▶ EMPRESA: {$dados['nome']} (ID: {$emp_id})\n";
    echo "  Mês com mais horas: {$mes_principal['mes']} ({$mes_principal['qtd']} registros, {$mes_principal['total_horas']}h)\n";
    echo "  \n";
    echo "  Passos:\n";
    echo "  1. Vá em: Financeiro → Fechamento de Pagamentos → Novo Fechamento\n";
    echo "  2. Selecione:\n";
    echo "     - Empresa: {$dados['nome']}\n";
    echo "     - Mês: {$mes_principal['mes']}\n";
    echo "     - Colaboradores: Marque " . count($dados['colaboradores']) . " colaborador(es):\n";
    
    foreach ($dados['colaboradores'] as $colab_id => $colab_nome) {
        echo "        [✓] {$colab_nome} (ID: {$colab_id})\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 130) . "\n";
echo "✅ Total: " . count($horas) . " horas extras disponíveis para fechamento\n";
echo str_repeat("=", 130) . "\n";

