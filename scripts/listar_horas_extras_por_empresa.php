<?php
/**
 * Lista horas extras disponíveis por empresa e mês
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== HORAS EXTRAS DISPONÍVEIS POR EMPRESA ===\n\n";

$pdo = getDB();

// Define o mês (novembro/2025 - onde estão as horas extras)
$mes_referencia = '2025-11';
$ano_mes = explode('-', $mes_referencia);
$data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio));

echo "Período analisado: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim)) . "\n";
echo str_repeat("=", 120) . "\n\n";

// Busca todas as empresas
$stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
$empresas = $stmt->fetchAll();

foreach ($empresas as $empresa) {
    echo "EMPRESA: {$empresa['nome_fantasia']} (ID: {$empresa['id']})\n";
    echo str_repeat("-", 120) . "\n";
    
    // Busca colaboradores da empresa com horas extras no período
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            c.id,
            c.nome_completo,
            c.salario,
            (
                SELECT SUM(quantidade_horas)
                FROM horas_extras he
                WHERE he.colaborador_id = c.id
                AND he.data_trabalho >= ?
                AND he.data_trabalho <= ?
                AND he.fechamento_pagamento_id IS NULL
                AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
            ) as total_horas,
            (
                SELECT SUM(valor_total)
                FROM horas_extras he
                WHERE he.colaborador_id = c.id
                AND he.data_trabalho >= ?
                AND he.data_trabalho <= ?
                AND he.fechamento_pagamento_id IS NULL
                AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
            ) as total_valor
        FROM colaboradores c
        WHERE c.empresa_id = ?
        AND c.status = 'ativo'
        AND EXISTS (
            SELECT 1 FROM horas_extras he
            WHERE he.colaborador_id = c.id
            AND he.data_trabalho >= ?
            AND he.data_trabalho <= ?
            AND he.fechamento_pagamento_id IS NULL
            AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
        )
        ORDER BY c.nome_completo
    ");
    $stmt->execute([
        $data_inicio, $data_fim,
        $data_inicio, $data_fim,
        $empresa['id'],
        $data_inicio, $data_fim
    ]);
    $colaboradores = $stmt->fetchAll();
    
    if (empty($colaboradores)) {
        echo "   ⚠️  Nenhum colaborador com horas extras neste período\n\n";
        continue;
    }
    
    printf("%-4s | %-35s | %-12s | %-10s | %-12s\n", 
        "ID", "Colaborador", "Salário", "Horas", "Valor");
    echo str_repeat("-", 120) . "\n";
    
    $total_empresa_horas = 0;
    $total_empresa_valor = 0;
    
    foreach ($colaboradores as $colab) {
        $salario = $colab['salario'] ? 'R$ ' . number_format($colab['salario'], 2, ',', '.') : 'Sem salário';
        
        printf("%-4d | %-35s | %-12s | %8.2fh | R$ %9.2f\n",
            $colab['id'],
            substr($colab['nome_completo'], 0, 35),
            $salario,
            $colab['total_horas'],
            $colab['total_valor']
        );
        
        $total_empresa_horas += $colab['total_horas'];
        $total_empresa_valor += $colab['total_valor'];
    }
    
    echo str_repeat("-", 120) . "\n";
    printf("TOTAL: %d colaboradores | %.2fh | R$ %.2f\n\n",
        count($colaboradores),
        $total_empresa_horas,
        $total_empresa_valor
    );
    
    // Mostra instruções específicas para esta empresa
    echo "📋 PARA CRIAR FECHAMENTO:\n";
    echo "   1. Vá em: Financeiro → Fechamento de Pagamentos → Novo Fechamento\n";
    echo "   2. Selecione:\n";
    echo "      - Empresa: {$empresa['nome_fantasia']}\n";
    echo "      - Mês: 2025-11 (Novembro/2025)\n";
    echo "      - Colaboradores: MARQUE OS " . count($colaboradores) . " COLABORADORES ACIMA:\n";
    
    foreach ($colaboradores as $i => $colab) {
        echo "         [✓] {$colab['nome_completo']}\n";
    }
    
    echo "\n";
    echo str_repeat("=", 120) . "\n\n";
}

echo "RESUMO GERAL:\n";
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT he.colaborador_id) as qtd_colaboradores,
        SUM(he.quantidade_horas) as total_horas,
        SUM(he.valor_total) as total_valor
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    WHERE he.data_trabalho >= ?
    AND he.data_trabalho <= ?
    AND he.fechamento_pagamento_id IS NULL
    AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
    AND c.status = 'ativo'
");
$stmt->execute([$data_inicio, $data_fim]);
$resumo = $stmt->fetch();

echo "Total de colaboradores com horas extras: {$resumo['qtd_colaboradores']}\n";
echo "Total de horas: " . number_format($resumo['total_horas'], 2, ',', '.') . "h\n";
echo "Valor total: R$ " . number_format($resumo['total_valor'], 2, ',', '.') . "\n\n";

echo "✅ PRONTO! Siga as instruções acima para criar os fechamentos.\n";

