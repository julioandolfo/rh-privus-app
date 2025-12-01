<?php
/**
 * Debug completo de horas extras
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== DEBUG COMPLETO DE HORAS EXTRAS ===\n\n";

$pdo = getDB();

// Lista TODAS as horas extras (independente do tipo)
echo "TODAS as horas extras cadastradas:\n";
echo str_repeat("=", 120) . "\n";

$stmt = $pdo->query("
    SELECT he.id, c.nome_completo, he.data_trabalho, he.quantidade_horas, 
           he.valor_total, he.tipo_pagamento, he.fechamento_pagamento_id,
           he.created_at, e.nome_fantasia as empresa
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    LEFT JOIN empresas e ON c.empresa_id = e.id
    ORDER BY he.data_trabalho DESC, he.created_at DESC
");
$todas = $stmt->fetchAll();

if (empty($todas)) {
    echo "❌ Nenhuma hora extra encontrada no banco!\n\n";
    echo "Possíveis causas:\n";
    echo "1. As horas extras não foram salvas corretamente\n";
    echo "2. Você está olhando o banco errado\n";
    echo "3. As horas foram deletadas\n\n";
    exit(1);
}

printf("%-4s | %-30s | %-12s | %-8s | %-12s | %-15s | %-15s\n", 
    "ID", "Colaborador", "Data", "Horas", "Valor", "Tipo Pag.", "Fechamento");
echo str_repeat("-", 120) . "\n";

foreach ($todas as $he) {
    $tipo_pag = $he['tipo_pagamento'] ?? 'NULL (dinheiro)';
    $fechamento = $he['fechamento_pagamento_id'] ? "Fechamento #{$he['fechamento_pagamento_id']}" : "Não pago";
    
    printf("%-4d | %-30s | %-12s | %6.2fh | R$ %8.2f | %-15s | %-15s\n",
        $he['id'],
        substr($he['nome_completo'], 0, 30),
        date('d/m/Y', strtotime($he['data_trabalho'])),
        $he['quantidade_horas'],
        $he['valor_total'],
        $tipo_pag,
        $fechamento
    );
}

echo "\n" . str_repeat("=", 120) . "\n\n";

// Resumo por tipo
echo "RESUMO POR TIPO DE PAGAMENTO:\n";
$stmt = $pdo->query("
    SELECT 
        COALESCE(tipo_pagamento, 'NULL (dinheiro)') as tipo,
        COUNT(*) as qtd,
        SUM(quantidade_horas) as total_horas,
        SUM(valor_total) as total_valor,
        SUM(CASE WHEN fechamento_pagamento_id IS NULL THEN 1 ELSE 0 END) as nao_pagos,
        SUM(CASE WHEN fechamento_pagamento_id IS NOT NULL THEN 1 ELSE 0 END) as pagos
    FROM horas_extras
    GROUP BY tipo_pagamento
");
$resumo = $stmt->fetchAll();

printf("%-20s | %-8s | %-12s | %-15s | %-10s | %-10s\n", 
    "Tipo", "Qtd", "Total Horas", "Valor Total", "Não Pagos", "Pagos");
echo str_repeat("-", 90) . "\n";

foreach ($resumo as $r) {
    printf("%-20s | %8d | %10.2fh | R$ %11.2f | %10d | %10d\n",
        $r['tipo'],
        $r['qtd'],
        $r['total_horas'],
        $r['total_valor'],
        $r['nao_pagos'],
        $r['pagos']
    );
}

echo "\n" . str_repeat("=", 120) . "\n\n";

// Verifica o que DEVERIA aparecer no fechamento
echo "HORAS QUE DEVERIAM APARECER NO FECHAMENTO (tipo=dinheiro, não pagas):\n";
$stmt = $pdo->query("
    SELECT he.id, c.nome_completo, he.data_trabalho, he.quantidade_horas, 
           he.valor_total, he.tipo_pagamento
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    WHERE he.fechamento_pagamento_id IS NULL
    AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
    ORDER BY he.data_trabalho DESC
");
$para_fechar = $stmt->fetchAll();

if (empty($para_fechar)) {
    echo "❌ Nenhuma hora extra disponível para fechamento!\n";
    echo "\nIsso significa que:\n";
    echo "- Todas as horas tipo 'dinheiro' já foram pagas, OU\n";
    echo "- Todas as horas cadastradas são tipo 'banco_horas'\n\n";
} else {
    printf("%-4s | %-30s | %-12s | %-8s | %-12s\n", 
        "ID", "Colaborador", "Data", "Horas", "Valor");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($para_fechar as $he) {
        printf("%-4d | %-30s | %-12s | %6.2fh | R$ %8.2f\n",
            $he['id'],
            substr($he['nome_completo'], 0, 30),
            date('d/m/Y', strtotime($he['data_trabalho'])),
            $he['quantidade_horas'],
            $he['valor_total']
        );
    }
}

echo "\n" . str_repeat("=", 120) . "\n";

