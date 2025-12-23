<?php
/**
 * Script de diagnóstico para onboarding
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = getDB();

echo "=== DIAGNÓSTICO DE ONBOARDING ===\n\n";

// Verifica estrutura da tabela
echo "1. ESTRUTURA DA TABELA ONBOARDING:\n";
$stmt = $pdo->query("DESCRIBE onboarding");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   - {$col['Field']}: {$col['Type']} ({$col['Null']})\n";
}

// Verifica se tem coluna entrevista_id
$has_entrevista_id = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'entrevista_id') {
        $has_entrevista_id = true;
        break;
    }
}

echo "\n2. COLUNA entrevista_id: " . ($has_entrevista_id ? "EXISTE ✓" : "NÃO EXISTE ✗ - EXECUTE A MIGRAÇÃO!") . "\n";

// Lista todos os onboardings
echo "\n3. REGISTROS NA TABELA ONBOARDING:\n";
$stmt = $pdo->query("SELECT * FROM onboarding ORDER BY id");
$onboardings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($onboardings)) {
    echo "   Nenhum registro encontrado!\n";
} else {
    foreach ($onboardings as $o) {
        echo "   ID: {$o['id']}, candidatura_id: " . ($o['candidatura_id'] ?? 'NULL');
        if ($has_entrevista_id) {
            echo ", entrevista_id: " . ($o['entrevista_id'] ?? 'NULL');
        }
        echo ", status: {$o['status']}, coluna: " . ($o['coluna_kanban'] ?? 'NULL') . "\n";
    }
}

// Verifica onboarding específico (id=2)
echo "\n4. ONBOARDING ID=2:\n";
$stmt = $pdo->prepare("SELECT * FROM onboarding WHERE id = 2");
$stmt->execute();
$ob2 = $stmt->fetch(PDO::FETCH_ASSOC);
if ($ob2) {
    print_r($ob2);
} else {
    echo "   Não existe registro com id=2\n";
}

// Verifica entrevistas na coluna contratado
echo "\n5. ENTREVISTAS NA COLUNA CONTRATADO:\n";
$stmt = $pdo->query("SELECT id, candidato_nome_manual, coluna_kanban, status FROM entrevistas WHERE coluna_kanban = 'contratado' AND candidatura_id IS NULL");
$entrevistas = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($entrevistas)) {
    echo "   Nenhuma entrevista manual na coluna contratado\n";
} else {
    foreach ($entrevistas as $e) {
        echo "   ID: {$e['id']}, Nome: {$e['candidato_nome_manual']}, Coluna: {$e['coluna_kanban']}, Status: {$e['status']}\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

