<?php
/**
 * Script para debugar adiantamentos descontados
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

// ID do fechamento que você está verificando
$fechamento_id = isset($argv[1]) ? (int)$argv[1] : 0;

if ($fechamento_id === 0) {
    echo "Uso: php debug_adiantamentos.php <fechamento_id>\n";
    exit;
}

echo "=== DEBUG ADIANTAMENTOS - Fechamento #$fechamento_id ===\n\n";

// Busca dados do fechamento
$stmt = $pdo->prepare("SELECT * FROM fechamentos_pagamento WHERE id = ?");
$stmt->execute([$fechamento_id]);
$fechamento = $stmt->fetch();

if (!$fechamento) {
    echo "Fechamento não encontrado!\n";
    exit;
}

echo "Tipo: {$fechamento['tipo_fechamento']}\n";
echo "Mês: {$fechamento['mes_referencia']}\n";
echo "Status: {$fechamento['status']}\n\n";

// Busca itens do fechamento
$stmt = $pdo->prepare("
    SELECT i.*, c.nome_completo
    FROM fechamentos_pagamento_itens i
    INNER JOIN colaboradores c ON i.colaborador_id = c.id
    WHERE i.fechamento_id = ?
");
$stmt->execute([$fechamento_id]);
$itens = $stmt->fetchAll();

echo "Colaboradores no fechamento: " . count($itens) . "\n\n";

foreach ($itens as $item) {
    echo "--- {$item['nome_completo']} (ID: {$item['colaborador_id']}) ---\n";
    echo "Descontos no item: R$ " . number_format($item['descontos'], 2, ',', '.') . "\n";
    
    // Busca adiantamentos descontados neste fechamento
    $stmt = $pdo->prepare("
        SELECT fa.*, f.mes_referencia as fechamento_mes_referencia
        FROM fechamentos_pagamento_adiantamentos fa
        INNER JOIN fechamentos_pagamento f ON fa.fechamento_pagamento_id = f.id
        WHERE fa.colaborador_id = ?
        AND fa.fechamento_desconto_id = ?
        AND fa.descontado = 1
    ");
    $stmt->execute([$item['colaborador_id'], $fechamento_id]);
    $adiantamentos_descontados = $stmt->fetchAll();
    
    echo "Adiantamentos descontados: " . count($adiantamentos_descontados) . "\n";
    
    if (!empty($adiantamentos_descontados)) {
        foreach ($adiantamentos_descontados as $ad) {
            echo "  - ID: {$ad['id']}\n";
            echo "    Valor adiantamento: R$ " . number_format($ad['valor_adiantamento'], 2, ',', '.') . "\n";
            echo "    Valor a descontar: R$ " . number_format($ad['valor_descontar'], 2, ',', '.') . "\n";
            echo "    Mês desconto: {$ad['mes_desconto']}\n";
            echo "    Fechamento origem: #{$ad['fechamento_pagamento_id']} ({$ad['fechamento_mes_referencia']})\n";
            echo "    Fechamento desconto: #{$ad['fechamento_desconto_id']}\n";
        }
    }
    
    // Busca TODOS os adiantamentos deste colaborador (para debug)
    $stmt = $pdo->prepare("
        SELECT * FROM fechamentos_pagamento_adiantamentos
        WHERE colaborador_id = ?
        ORDER BY mes_desconto ASC
    ");
    $stmt->execute([$item['colaborador_id']]);
    $todos_adiantamentos = $stmt->fetchAll();
    
    echo "\nTodos os adiantamentos deste colaborador: " . count($todos_adiantamentos) . "\n";
    foreach ($todos_adiantamentos as $ad) {
        echo "  - ID: {$ad['id']} | Valor: R$ " . number_format($ad['valor_descontar'], 2, ',', '.') . 
             " | Mês desconto: {$ad['mes_desconto']} | Descontado: " . ($ad['descontado'] ? 'SIM' : 'NÃO') .
             " | Fechamento desconto: " . ($ad['fechamento_desconto_id'] ?? 'NULL') . "\n";
    }
    
    echo "\n";
}

