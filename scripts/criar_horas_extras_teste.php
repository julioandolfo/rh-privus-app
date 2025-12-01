<?php
/**
 * Script para criar horas extras de teste
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== CRIAR HORAS EXTRAS DE TESTE ===\n\n";

$pdo = getDB();

// Busca um colaborador ativo com salário
$stmt = $pdo->query("
    SELECT id, nome_completo, salario, empresa_id
    FROM colaboradores 
    WHERE status = 'ativo' 
    AND salario IS NOT NULL 
    AND salario > 0
    LIMIT 1
");
$colaborador = $stmt->fetch();

if (!$colaborador) {
    echo "❌ Nenhum colaborador ativo com salário encontrado!\n";
    exit(1);
}

echo "Colaborador selecionado: {$colaborador['nome_completo']} (ID: {$colaborador['id']})\n";
echo "Salário: R$ " . number_format($colaborador['salario'], 2, ',', '.') . "\n\n";

// Busca percentual da empresa
$stmt = $pdo->prepare("SELECT percentual_hora_extra FROM empresas WHERE id = ?");
$stmt->execute([$colaborador['empresa_id']]);
$empresa = $stmt->fetch();
$percentual = $empresa['percentual_hora_extra'] ?? 50.00;

echo "Percentual de hora extra: {$percentual}%\n\n";

// Calcula valores
$valor_hora = $colaborador['salario'] / 220;
echo "Valor hora base: R$ " . number_format($valor_hora, 2, ',', '.') . "\n\n";

// Cria 5 horas extras de teste
$horas_teste = [
    ['data' => '2025-12-01', 'horas' => 2.0, 'obs' => 'Teste 1 - 2 horas'],
    ['data' => '2025-12-05', 'horas' => 3.5, 'obs' => 'Teste 2 - 3.5 horas'],
    ['data' => '2025-12-10', 'horas' => 1.5, 'obs' => 'Teste 3 - 1.5 horas'],
    ['data' => '2025-12-15', 'horas' => 4.0, 'obs' => 'Teste 4 - 4 horas'],
    ['data' => '2025-12-20', 'horas' => 2.5, 'obs' => 'Teste 5 - 2.5 horas'],
];

echo "Criando 5 horas extras de teste (tipo: dinheiro)...\n";
echo str_repeat("-", 80) . "\n";

$pdo->beginTransaction();

try {
    foreach ($horas_teste as $he) {
        $valor_total = $valor_hora * $he['horas'] * (1 + ($percentual / 100));
        
        $stmt = $pdo->prepare("
            INSERT INTO horas_extras (
                colaborador_id, data_trabalho, quantidade_horas,
                valor_hora, percentual_adicional, valor_total,
                observacoes, usuario_id, tipo_pagamento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'dinheiro')
        ");
        
        $stmt->execute([
            $colaborador['id'],
            $he['data'],
            $he['horas'],
            $valor_hora,
            $percentual,
            $valor_total,
            $he['obs']
        ]);
        
        $id_criado = $pdo->lastInsertId();
        
        printf("✅ ID %d - %s - %.1fh - R$ %.2f - %s\n",
            $id_criado,
            date('d/m/Y', strtotime($he['data'])),
            $he['horas'],
            $valor_total,
            $he['obs']
        );
    }
    
    $pdo->commit();
    
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "✅ 5 horas extras criadas com sucesso!\n\n";
    
    // Mostra resumo
    $stmt = $pdo->query("
        SELECT COUNT(*) as qtd, SUM(quantidade_horas) as total_horas, SUM(valor_total) as total_valor
        FROM horas_extras
        WHERE fechamento_pagamento_id IS NULL
        AND (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL)
    ");
    $resumo = $stmt->fetch();
    
    echo "RESUMO DE HORAS EXTRAS DISPONÍVEIS PARA FECHAMENTO:\n";
    echo "Registros: {$resumo['qtd']}\n";
    echo "Total de horas: " . number_format($resumo['total_horas'], 2, ',', '.') . "h\n";
    echo "Valor total: R$ " . number_format($resumo['total_valor'], 2, ',', '.') . "\n\n";
    
    echo "Agora você pode:\n";
    echo "1. Ir em Financeiro > Fechamento de Pagamentos\n";
    echo "2. Criar um novo fechamento para DEZEMBRO/2025\n";
    echo "3. Selecionar o colaborador: {$colaborador['nome_completo']}\n";
    echo "4. As horas extras devem aparecer no fechamento!\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Erro ao criar horas extras: " . $e->getMessage() . "\n";
    exit(1);
}

