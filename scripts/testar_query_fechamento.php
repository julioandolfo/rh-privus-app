<?php
/**
 * Testa exatamente a mesma query que o fechamento usa
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== TESTE DA QUERY DE FECHAMENTO ===\n\n";

$pdo = getDB();

// Simula dados de um fechamento
$mes_referencia = '2025-11';
$ano_mes = explode('-', $mes_referencia);
$data_inicio = $ano_mes[0] . '-' . $ano_mes[1] . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio));

echo "Período: {$data_inicio} a {$data_fim}\n\n";

// Lista colaboradores para teste
$stmt = $pdo->query("
    SELECT DISTINCT c.id, c.nome_completo, c.empresa_id, e.nome_fantasia
    FROM colaboradores c
    LEFT JOIN empresas e ON c.empresa_id = e.id
    WHERE c.status = 'ativo'
    AND EXISTS (
        SELECT 1 FROM horas_extras he
        WHERE he.colaborador_id = c.id
        AND he.data_trabalho >= '{$data_inicio}'
        AND he.data_trabalho <= '{$data_fim}'
        AND he.fechamento_pagamento_id IS NULL
    )
    ORDER BY e.nome_fantasia, c.nome_completo
");
$colaboradores = $stmt->fetchAll();

echo "Colaboradores com horas extras: " . count($colaboradores) . "\n\n";

foreach ($colaboradores as $colab) {
    echo str_repeat("=", 120) . "\n";
    echo "COLABORADOR: {$colab['nome_completo']} (ID: {$colab['id']}) - {$colab['nome_fantasia']}\n";
    echo str_repeat("=", 120) . "\n";
    
    // ESTA É A QUERY EXATA DO FECHAMENTO (linhas 300-313 de fechamento_pagamentos.php)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN quantidade_horas ELSE 0 END), 0) as total_horas_dinheiro,
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as total_valor_dinheiro,
            COALESCE(SUM(CASE WHEN tipo_pagamento = 'banco_horas' THEN quantidade_horas ELSE 0 END), 0) as total_horas_banco,
            COALESCE(SUM(quantidade_horas), 0) as total_horas,
            COALESCE(SUM(CASE WHEN (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL) THEN valor_total ELSE 0 END), 0) as total_valor
        FROM horas_extras
        WHERE colaborador_id = ? 
        AND data_trabalho >= ? 
        AND data_trabalho <= ?
        AND fechamento_pagamento_id IS NULL
    ");
    $stmt->execute([$colab['id'], $data_inicio, $data_fim]);
    $he_data = $stmt->fetch();
    
    echo "\nRESULTADO DA QUERY:\n";
    echo "  total_horas_dinheiro: " . $he_data['total_horas_dinheiro'] . "h\n";
    echo "  total_valor_dinheiro: R$ " . number_format($he_data['total_valor_dinheiro'], 2, ',', '.') . "\n";
    echo "  total_horas_banco: " . $he_data['total_horas_banco'] . "h\n";
    echo "  total_horas (soma): " . $he_data['total_horas'] . "h\n";
    echo "  total_valor (soma): R$ " . number_format($he_data['total_valor'], 2, ',', '.') . "\n";
    
    if ($he_data['total_horas_dinheiro'] > 0) {
        echo "\n  ✅ DEVERIA APARECER NO FECHAMENTO!\n";
        
        // Lista as horas extras
        $stmt2 = $pdo->prepare("
            SELECT id, data_trabalho, quantidade_horas, valor_total, tipo_pagamento, fechamento_pagamento_id
            FROM horas_extras
            WHERE colaborador_id = ?
            AND data_trabalho >= ?
            AND data_trabalho <= ?
            AND fechamento_pagamento_id IS NULL
            ORDER BY data_trabalho
        ");
        $stmt2->execute([$colab['id'], $data_inicio, $data_fim]);
        $horas = $stmt2->fetchAll();
        
        echo "\n  Horas extras encontradas:\n";
        printf("  %-4s | %-12s | %-8s | %-12s | %-15s | %-15s\n",
            "ID", "Data", "Horas", "Valor", "Tipo", "Fechamento");
        echo "  " . str_repeat("-", 90) . "\n";
        
        foreach ($horas as $he) {
            printf("  %-4d | %-12s | %6.2fh | R$ %8.2f | %-15s | %-15s\n",
                $he['id'],
                date('d/m/Y', strtotime($he['data_trabalho'])),
                $he['quantidade_horas'],
                $he['valor_total'],
                $he['tipo_pagamento'] ?? 'NULL',
                $he['fechamento_pagamento_id'] ? "Fech #{$he['fechamento_pagamento_id']}" : 'Não pago'
            );
        }
    } else {
        echo "\n  ❌ NÃO DEVERIA APARECER (sem horas tipo dinheiro)\n";
    }
    
    echo "\n";
}

echo "\n" . str_repeat("=", 120) . "\n";
echo "RESUMO:\n";
echo "  Colaboradores com horas extras disponíveis: " . count($colaboradores) . "\n";
echo "  Período: {$data_inicio} a {$data_fim}\n";
echo "\n";
echo "Se esses colaboradores não aparecem no fechamento,\n";
echo "verifique se você está selecionando a EMPRESA e MÊS corretos!\n";
echo str_repeat("=", 120) . "\n";

