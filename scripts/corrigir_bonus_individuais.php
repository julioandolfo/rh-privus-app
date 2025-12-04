<?php
/**
 * Script para corrigir fechamentos individuais que não têm bônus salvos
 * Este script busca fechamentos extras individuais que têm valor_manual mas não têm bônus na tabela fechamentos_pagamento_bonus
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

echo "=== CORREÇÃO DE BÔNUS INDIVIDUAIS ===\n\n";

// Busca fechamentos extras individuais que têm itens mas não têm bônus salvos
$stmt = $pdo->query("
    SELECT DISTINCT fp.id as fechamento_id, fp.mes_referencia, fp.tipo_fechamento, fp.subtipo_fechamento, fp.status,
           e.nome_fantasia as empresa_nome,
           i.colaborador_id, i.valor_total, i.valor_manual, i.motivo,
           c.nome_completo as colaborador_nome
    FROM fechamentos_pagamento fp
    INNER JOIN fechamentos_pagamento_itens i ON fp.id = i.fechamento_id
    LEFT JOIN empresas e ON fp.empresa_id = e.id
    LEFT JOIN colaboradores c ON i.colaborador_id = c.id
    LEFT JOIN fechamentos_pagamento_bonus fb ON fp.id = fb.fechamento_pagamento_id AND i.colaborador_id = fb.colaborador_id
    WHERE fp.tipo_fechamento = 'extra' 
    AND fp.subtipo_fechamento = 'individual'
    AND fb.id IS NULL
    AND i.valor_total > 0
    ORDER BY fp.id DESC
");

$fechamentos_sem_bonus = $stmt->fetchAll();

echo "FECHAMENTOS ENCONTRADOS SEM BÔNUS: " . count($fechamentos_sem_bonus) . "\n\n";

if (empty($fechamentos_sem_bonus)) {
    echo "✅ Nenhum fechamento precisa de correção!\n";
    exit;
}

echo "ATENÇÃO: Este script irá criar registros de bônus para fechamentos individuais que não têm bônus salvos.\n";
echo "Como não há tipo_bonus_id original, será criado um registro genérico.\n\n";
echo "Deseja continuar? (s/n): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 's' && trim($line) !== 'S') {
    echo "Operação cancelada.\n";
    exit;
}

$corrigidos = 0;
$erros = 0;

foreach ($fechamentos_sem_bonus as $fechamento) {
    echo "\n--- Fechamento ID: {$fechamento['fechamento_id']} ---\n";
    echo "Colaborador: {$fechamento['colaborador_nome']} (ID: {$fechamento['colaborador_id']})\n";
    echo "Valor Total: R$ " . number_format($fechamento['valor_total'], 2, ',', '.') . "\n";
    echo "Valor Manual: R$ " . number_format($fechamento['valor_manual'] ?? 0, 2, ',', '.') . "\n";
    
    // Para fechamentos individuais sem tipo de bônus, vamos criar um registro usando o tipo "Bônus" genérico
    // ou criar um registro sem tipo_bonus_id (NULL) se não houver tipo genérico disponível
    
    $valor_final = (float)$fechamento['valor_total'];
    $valor_original = (float)($fechamento['valor_manual'] ?? $fechamento['valor_total']);
    $motivo = $fechamento['motivo'] ?? 'Bônus individual - Corrigido automaticamente';
    
    // Busca tipo de bônus genérico "Bônus"
    $stmt_tipo = $pdo->prepare("SELECT id FROM tipos_bonus WHERE nome LIKE '%Bônus%' AND status = 'ativo' LIMIT 1");
    $stmt_tipo->execute();
    $tipo_bonus_gen = $stmt_tipo->fetch();
    
    $tipo_bonus_id = $tipo_bonus_gen ? $tipo_bonus_gen['id'] : null;
    
    try {
        if ($tipo_bonus_id) {
            // Insere o bônus com tipo de bônus
            $stmt_insert = $pdo->prepare("
                INSERT INTO fechamentos_pagamento_bonus 
                (fechamento_pagamento_id, colaborador_id, tipo_bonus_id, valor, valor_original, observacoes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([
                $fechamento['fechamento_id'],
                $fechamento['colaborador_id'],
                $tipo_bonus_id,
                $valor_final,
                $valor_original,
                $motivo . ' | [CORRIGIDO AUTOMATICAMENTE]'
            ]);
        } else {
            // Se não houver tipo genérico, não podemos inserir sem tipo_bonus_id
            // porque a tabela pode ter constraint NOT NULL
            // Neste caso, vamos apenas informar que precisa de um tipo de bônus
            echo "⚠️ Não foi possível encontrar um tipo de bônus genérico. O sistema agora exibirá o valor automaticamente mesmo sem registro na tabela.\n";
            echo "   (O valor aparecerá na coluna Bônus como 'Valor Livre')\n";
            $corrigidos++; // Conta como corrigido porque o sistema agora exibe automaticamente
            continue;
        }
        
        echo "✅ Bônus criado com sucesso!\n";
        $corrigidos++;
    } catch (Exception $e) {
        echo "❌ Erro ao criar bônus: " . $e->getMessage() . "\n";
        $erros++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Corrigidos: $corrigidos\n";
echo "Erros: $erros\n";
echo "Total processados: " . count($fechamentos_sem_bonus) . "\n";

