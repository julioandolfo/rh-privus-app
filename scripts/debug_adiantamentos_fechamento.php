<?php
/**
 * Script de Debug - Adiantamentos em Fechamentos
 * Verifica se os adiantamentos estão sendo salvos e buscados corretamente
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();

echo "=== DEBUG: ADIANTAMENTOS EM FECHAMENTOS ===\n\n";

// Pede ID do fechamento
$fechamento_id = $argv[1] ?? null;

if (!$fechamento_id) {
    echo "Uso: php scripts/debug_adiantamentos_fechamento.php [ID_DO_FECHAMENTO]\n";
    echo "\nOu informe o ID do fechamento: ";
    $fechamento_id = trim(fgets(STDIN));
}

if (empty($fechamento_id)) {
    die("ID do fechamento é obrigatório!\n");
}

$fechamento_id = (int)$fechamento_id;

// Busca dados do fechamento
$stmt = $pdo->prepare("
    SELECT f.*, e.nome_fantasia as empresa_nome
    FROM fechamentos_pagamento f
    LEFT JOIN empresas e ON f.empresa_id = e.id
    WHERE f.id = ?
");
$stmt->execute([$fechamento_id]);
$fechamento = $stmt->fetch();

if (!$fechamento) {
    die("Fechamento não encontrado!\n");
}

echo "FECHAMENTO:\n";
echo "ID: {$fechamento['id']}\n";
echo "Tipo: {$fechamento['tipo_fechamento']}\n";
echo "Mês Referência: {$fechamento['mes_referencia']}\n";
echo "Empresa: {$fechamento['empresa_nome']}\n";
echo "\n";

// Busca itens do fechamento
$stmt = $pdo->prepare("
    SELECT i.*, c.nome_completo as colaborador_nome, c.id as colaborador_id
    FROM fechamentos_pagamento_itens i
    INNER JOIN colaboradores c ON i.colaborador_id = c.id
    WHERE i.fechamento_id = ?
    ORDER BY c.nome_completo
");
$stmt->execute([$fechamento_id]);
$itens = $stmt->fetchAll();

echo "ITENS DO FECHAMENTO:\n";
echo str_repeat("=", 100) . "\n";
printf("%-30s | %-15s | %-15s | %-15s\n", "Colaborador", "Descontos (BD)", "Adiantamentos", "Total Exibido");
echo str_repeat("-", 100) . "\n";

foreach ($itens as $item) {
    $colab_id = $item['colaborador_id'];
    $descontos_bd = (float)($item['descontos'] ?? 0);
    
    // Busca adiantamentos descontados neste fechamento (método 1)
    $stmt = $pdo->prepare("
        SELECT SUM(valor_descontar) as total_adiantamentos
        FROM fechamentos_pagamento_adiantamentos
        WHERE fechamento_desconto_id = ?
        AND colaborador_id = ?
        AND descontado = 1
    ");
    $stmt->execute([$fechamento_id, $colab_id]);
    $ad1 = $stmt->fetch();
    $adiantamentos_metodo1 = (float)($ad1['total_adiantamentos'] ?? 0);
    
    // Busca adiantamentos descontados por mês (método 2)
    $stmt = $pdo->prepare("
        SELECT SUM(valor_descontar) as total_adiantamentos
        FROM fechamentos_pagamento_adiantamentos
        WHERE colaborador_id = ?
        AND mes_desconto = ?
        AND descontado = 1
    ");
    $stmt->execute([$colab_id, $fechamento['mes_referencia']]);
    $ad2 = $stmt->fetch();
    $adiantamentos_metodo2 = (float)($ad2['total_adiantamentos'] ?? 0);
    
    // Usa o maior valor encontrado
    $adiantamentos_encontrados = max($adiantamentos_metodo1, $adiantamentos_metodo2);
    
    $total_exibido = $descontos_bd;
    if ($adiantamentos_encontrados > 0 && $descontos_bd < $adiantamentos_encontrados) {
        $total_exibido = $descontos_bd + $adiantamentos_encontrados;
    }
    
    printf(
        "%-30s | R$ %12.2f | R$ %12.2f | R$ %12.2f\n",
        substr($item['colaborador_nome'], 0, 30),
        $descontos_bd,
        $adiantamentos_encontrados,
        $total_exibido
    );
    
    // Se encontrou adiantamentos, mostra detalhes
    if ($adiantamentos_encontrados > 0) {
        $stmt = $pdo->prepare("
            SELECT fa.*, f.mes_referencia as fechamento_mes_referencia, f.data_fechamento
            FROM fechamentos_pagamento_adiantamentos fa
            INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
            WHERE fa.colaborador_id = ?
            AND (
                (fa.fechamento_desconto_id = ? AND fa.descontado = 1)
                OR (fa.mes_desconto = ? AND fa.descontado = 1)
            )
            ORDER BY f.data_fechamento DESC
        ");
        $stmt->execute([$colab_id, $fechamento_id, $fechamento['mes_referencia']]);
        $adiantamentos_detalhes = $stmt->fetchAll();
        
        if (!empty($adiantamentos_detalhes)) {
            echo "  └─ Adiantamentos encontrados:\n";
            foreach ($adiantamentos_detalhes as $ad) {
                echo "     - ID: {$ad['id']} | Valor: R$ " . number_format($ad['valor_descontar'], 2, ',', '.') . 
                     " | Mês Desconto: {$ad['mes_desconto']} | Descontado: " . ($ad['descontado'] ? 'SIM' : 'NÃO') .
                     " | Fechamento Desconto ID: " . ($ad['fechamento_desconto_id'] ?? 'NULL') . "\n";
            }
        }
    }
}

echo "\n";
echo str_repeat("=", 100) . "\n";
echo "\n";

// Busca TODOS os adiantamentos pendentes dos colaboradores deste fechamento
echo "ADIANTAMENTOS PENDENTES DOS COLABORADORES:\n";
echo str_repeat("=", 100) . "\n";

$colaboradores_ids = array_column($itens, 'colaborador_id');
if (!empty($colaboradores_ids)) {
    $placeholders = implode(',', array_fill(0, count($colaboradores_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT fa.*, c.nome_completo as colaborador_nome, f.mes_referencia as fechamento_mes_referencia
        FROM fechamentos_pagamento_adiantamentos fa
        INNER JOIN colaboradores c ON fa.colaborador_id = c.id
        INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
        WHERE fa.colaborador_id IN ($placeholders)
        AND fa.descontado = 0
        ORDER BY c.nome_completo, fa.mes_desconto
    ");
    $stmt->execute($colaboradores_ids);
    $adiantamentos_pendentes = $stmt->fetchAll();
    
    if (empty($adiantamentos_pendentes)) {
        echo "Nenhum adiantamento pendente encontrado.\n";
    } else {
        printf("%-30s | %-12s | %-15s | %-15s\n", "Colaborador", "Mês Desconto", "Valor", "Status");
        echo str_repeat("-", 100) . "\n";
        foreach ($adiantamentos_pendentes as $ad) {
            printf(
                "%-30s | %-12s | R$ %12.2f | %s\n",
                substr($ad['colaborador_nome'], 0, 30),
                $ad['mes_desconto'],
                $ad['valor_descontar'],
                $ad['mes_desconto'] === $fechamento['mes_referencia'] ? 'DEVERIA SER DESCONTADO' : 'Aguardando'
            );
        }
    }
}

echo "\n";
echo "=== FIM DO DEBUG ===\n";

