<?php
/**
 * Script para debugar bônus individuais não aparecendo
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

echo "=== DEBUG BÔNUS INDIVIDUAIS ===\n\n";

// Busca fechamentos extras do tipo individual
$stmt = $pdo->query("
    SELECT fp.id, fp.mes_referencia, fp.tipo_fechamento, fp.subtipo_fechamento, fp.status,
           e.nome_fantasia as empresa_nome
    FROM fechamentos_pagamento fp
    LEFT JOIN empresas e ON fp.empresa_id = e.id
    WHERE fp.tipo_fechamento = 'extra' 
    AND fp.subtipo_fechamento = 'individual'
    ORDER BY fp.id DESC
    LIMIT 10
");

$fechamentos = $stmt->fetchAll();

echo "FECHAMENTOS EXTRA INDIVIDUAIS ENCONTRADOS: " . count($fechamentos) . "\n\n";

foreach ($fechamentos as $fechamento) {
    echo "--- Fechamento ID: {$fechamento['id']} ---\n";
    echo "Mês: {$fechamento['mes_referencia']}\n";
    echo "Status: {$fechamento['status']}\n";
    echo "Empresa: {$fechamento['empresa_nome']}\n";
    
    // Busca itens do fechamento
    $stmt_itens = $pdo->prepare("
        SELECT i.*, c.nome_completo as colaborador_nome
        FROM fechamentos_pagamento_itens i
        INNER JOIN colaboradores c ON i.colaborador_id = c.id
        WHERE i.fechamento_id = ?
    ");
    $stmt_itens->execute([$fechamento['id']]);
    $itens = $stmt_itens->fetchAll();
    
    echo "Itens: " . count($itens) . "\n";
    
    foreach ($itens as $item) {
        echo "  - Colaborador: {$item['colaborador_nome']} (ID: {$item['colaborador_id']})\n";
        echo "    Valor Total: R$ " . number_format($item['valor_total'], 2, ',', '.') . "\n";
        echo "    Valor Manual: R$ " . number_format($item['valor_manual'] ?? 0, 2, ',', '.') . "\n";
        
        // Busca bônus salvos para este item
        $stmt_bonus = $pdo->prepare("
            SELECT fb.*, tb.nome as tipo_bonus_nome, tb.tipo_valor
            FROM fechamentos_pagamento_bonus fb
            LEFT JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
            WHERE fb.fechamento_pagamento_id = ? AND fb.colaborador_id = ?
        ");
        $stmt_bonus->execute([$fechamento['id'], $item['colaborador_id']]);
        $bonus = $stmt_bonus->fetchAll();
        
        echo "    Bônus encontrados: " . count($bonus) . "\n";
        foreach ($bonus as $b) {
            echo "      - Tipo: {$b['tipo_bonus_nome']} (ID: {$b['tipo_bonus_id']})\n";
            echo "        Valor: R$ " . number_format($b['valor'], 2, ',', '.') . "\n";
            echo "        Valor Original: R$ " . number_format($b['valor_original'] ?? $b['valor'], 2, ',', '.') . "\n";
        }
        
        if (empty($bonus)) {
            echo "      ⚠️ NENHUM BÔNUS ENCONTRADO NA TABELA fechamentos_pagamento_bonus!\n";
        }
    }
    
    echo "\n";
}

echo "\n=== VERIFICAÇÃO DE TIPOS DE BÔNUS ===\n";
$stmt_tipos = $pdo->query("SELECT id, nome, tipo_valor, valor_fixo FROM tipos_bonus WHERE status = 'ativo' ORDER BY nome");
$tipos = $stmt_tipos->fetchAll();
echo "Tipos de bônus ativos: " . count($tipos) . "\n";
foreach ($tipos as $tipo) {
    echo "  - {$tipo['nome']} (ID: {$tipo['id']}, Tipo: {$tipo['tipo_valor']}";
    if ($tipo['tipo_valor'] === 'fixo') {
        echo ", Valor Fixo: R$ " . number_format($tipo['valor_fixo'], 2, ',', '.');
    }
    echo ")\n";
}

