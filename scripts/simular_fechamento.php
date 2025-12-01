<?php
/**
 * Simula a criação de um fechamento para ver quais horas extras serão incluídas
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== SIMULAÇÃO DE FECHAMENTO DE PAGAMENTO ===\n\n";

$pdo = getDB();

// Solicita dados do fechamento
echo "Informe os dados para simular o fechamento:\n";
echo "Mês de referência (YYYY-MM, ex: 2025-11): ";
$handle = fopen("php://stdin", "r");
$mes_referencia = trim(fgets($handle));

// Busca empresas
$stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
$empresas = $stmt->fetchAll();

echo "\nEmpresas disponíveis:\n";
foreach ($empresas as $emp) {
    echo "  [{$emp['id']}] {$emp['nome_fantasia']}\n";
}
echo "ID da Empresa: ";
$empresa_id = (int)trim(fgets($handle));

fclose($handle);

// Calcula período
$ano_mes = explode('-', $mes_referencia);
$data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio));

echo "\n" . str_repeat("=", 100) . "\n";
echo "SIMULAÇÃO PARA:\n";
echo "  Empresa ID: {$empresa_id}\n";
echo "  Mês: {$mes_referencia}\n";
echo "  Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim)) . "\n";
echo str_repeat("=", 100) . "\n\n";

// Busca colaboradores ativos da empresa
echo "1. COLABORADORES ATIVOS DA EMPRESA:\n";
$stmt = $pdo->prepare("
    SELECT id, nome_completo, salario, setor_id
    FROM colaboradores
    WHERE empresa_id = ?
    AND status = 'ativo'
    ORDER BY nome_completo
");
$stmt->execute([$empresa_id]);
$colaboradores = $stmt->fetchAll();

echo "   Total de colaboradores: " . count($colaboradores) . "\n\n";

if (empty($colaboradores)) {
    echo "   ❌ Nenhum colaborador ativo encontrado para esta empresa!\n";
    exit(1);
}

// Para cada colaborador, verifica horas extras
echo "2. HORAS EXTRAS POR COLABORADOR NO PERÍODO:\n";
echo str_repeat("-", 120) . "\n";
printf("%-4s | %-30s | %-10s | %-8s | %-12s | Status\n", 
    "ID", "Colaborador", "Salário", "Horas", "Valor");
echo str_repeat("-", 120) . "\n";

$total_geral_horas = 0;
$total_geral_valor = 0;
$colaboradores_com_horas = [];

foreach ($colaboradores as $colab) {
    // Busca horas extras do período (mesma query do sistema)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN quantidade_horas ELSE 0 END), 0) as total_horas_dinheiro,
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as total_valor_dinheiro
        FROM horas_extras
        WHERE colaborador_id = ? 
        AND data_trabalho >= ? 
        AND data_trabalho <= ?
        AND fechamento_pagamento_id IS NULL
    ");
    $stmt->execute([$colab['id'], $data_inicio, $data_fim]);
    $he_data = $stmt->fetch();
    
    $horas_extras = (float)($he_data['total_horas_dinheiro'] ?? 0);
    $valor_horas_extras = (float)($he_data['total_valor_dinheiro'] ?? 0);
    
    $salario_formatado = $colab['salario'] ? 'R$ ' . number_format($colab['salario'], 2, ',', '.') : 'Sem salário';
    
    if ($horas_extras > 0) {
        printf("%-4d | %-30s | %-10s | %6.2fh | R$ %8.2f | ✅ TEM HORAS\n",
            $colab['id'],
            substr($colab['nome_completo'], 0, 30),
            $salario_formatado,
            $horas_extras,
            $valor_horas_extras
        );
        
        $total_geral_horas += $horas_extras;
        $total_geral_valor += $valor_horas_extras;
        $colaboradores_com_horas[] = $colab['id'];
    }
}

if (empty($colaboradores_com_horas)) {
    echo "\n❌ NENHUM colaborador tem horas extras neste período!\n\n";
    echo "Possíveis causas:\n";
    echo "1. As horas extras são de outro mês (verifique o mês de referência)\n";
    echo "2. As horas extras já foram incluídas em outro fechamento\n";
    echo "3. As horas extras são de colaboradores de outra empresa\n\n";
} else {
    echo str_repeat("-", 120) . "\n";
    printf("TOTAL: %d colaboradores | %.2fh | R$ %.2f\n\n",
        count($colaboradores_com_horas),
        $total_geral_horas,
        $total_geral_valor
    );
    
    echo "✅ Estes colaboradores DEVEM ser selecionados ao criar o fechamento!\n\n";
}

// Mostra detalhes das horas extras que seriam incluídas
if (!empty($colaboradores_com_horas)) {
    echo "3. DETALHES DAS HORAS EXTRAS QUE SERÃO INCLUÍDAS:\n";
    echo str_repeat("-", 120) . "\n";
    
    $placeholders = implode(',', array_fill(0, count($colaboradores_com_horas), '?'));
    $params = array_merge($colaboradores_com_horas, [$data_inicio, $data_fim]);
    
    $stmt = $pdo->prepare("
        SELECT he.id, c.nome_completo, he.data_trabalho, he.quantidade_horas, 
               he.valor_total, he.observacoes
        FROM horas_extras he
        INNER JOIN colaboradores c ON he.colaborador_id = c.id
        WHERE he.colaborador_id IN ($placeholders)
        AND he.data_trabalho >= ?
        AND he.data_trabalho <= ?
        AND he.fechamento_pagamento_id IS NULL
        AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
        ORDER BY c.nome_completo, he.data_trabalho
    ");
    $stmt->execute($params);
    $horas_detalhadas = $stmt->fetchAll();
    
    printf("%-4s | %-30s | %-12s | %-8s | %-12s | Obs\n", 
        "ID", "Colaborador", "Data", "Horas", "Valor");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($horas_detalhadas as $he) {
        printf("%-4d | %-30s | %-12s | %6.2fh | R$ %8.2f | %s\n",
            $he['id'],
            substr($he['nome_completo'], 0, 30),
            date('d/m/Y', strtotime($he['data_trabalho'])),
            $he['quantidade_horas'],
            $he['valor_total'],
            substr($he['observacoes'] ?? '', 0, 30)
        );
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "INSTRUÇÕES PARA CRIAR O FECHAMENTO:\n";
    echo str_repeat("=", 100) . "\n";
    echo "1. Vá em: Financeiro → Fechamento de Pagamentos\n";
    echo "2. Clique em: Novo Fechamento\n";
    echo "3. Selecione:\n";
    echo "   - Mês de Referência: {$mes_referencia}\n";
    echo "   - Empresa: (ID {$empresa_id})\n";
    echo "   - Colaboradores: SELECIONE OS " . count($colaboradores_com_horas) . " COLABORADORES LISTADOS ACIMA\n";
    echo "4. Criar Fechamento\n\n";
    echo "✅ As horas extras aparecerão no fechamento!\n\n";
}

echo str_repeat("=", 100) . "\n";

