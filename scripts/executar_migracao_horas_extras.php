<?php
/**
 * Script para executar a migração de controle de horas extras pagas
 * Verifica se o campo existe e aplica a migração automaticamente
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== MIGRAÇÃO: CONTROLE DE HORAS EXTRAS PAGAS ===\n\n";

try {
    $pdo = getDB();
    
    // 1. Verifica se o campo já existe
    echo "1. Verificando se a migração já foi aplicada...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM horas_extras LIKE 'fechamento_pagamento_id'");
    $campo_existe = $stmt->fetch();
    
    if ($campo_existe) {
        echo "   ✅ Migração já foi aplicada anteriormente!\n";
        echo "   Campo fechamento_pagamento_id já existe.\n\n";
        
        // Mostra estatísticas
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN fechamento_pagamento_id IS NULL THEN 1 ELSE 0 END) as nao_pagos,
                SUM(CASE WHEN fechamento_pagamento_id IS NOT NULL THEN 1 ELSE 0 END) as pagos
            FROM horas_extras
            WHERE tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL
        ");
        $stats = $stmt->fetch();
        
        echo "   Estatísticas de horas extras (tipo dinheiro):\n";
        echo "   - Total: {$stats['total']}\n";
        echo "   - Não pagas: {$stats['nao_pagos']}\n";
        echo "   - Já pagas: {$stats['pagos']}\n\n";
        
        exit(0);
    }
    
    echo "   ⚠️  Campo NÃO existe. Aplicando migração...\n\n";
    
    // 2. Mostra horas extras antes da migração
    echo "2. Horas extras cadastradas ANTES da migração:\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, 
               SUM(quantidade_horas) as total_horas,
               SUM(valor_total) as total_valor
        FROM horas_extras
        WHERE tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL
    ");
    $antes = $stmt->fetch();
    echo "   - Registros tipo 'dinheiro': {$antes['total']}\n";
    echo "   - Total de horas: " . number_format($antes['total_horas'], 2, ',', '.') . "h\n";
    echo "   - Valor total: R$ " . number_format($antes['total_valor'], 2, ',', '.') . "\n\n";
    
    // 3. Aplica a migração
    echo "3. Aplicando migração...\n";
    
    $pdo->beginTransaction();
    
    // Adiciona o campo
    $pdo->exec("
        ALTER TABLE horas_extras
        ADD COLUMN fechamento_pagamento_id INT NULL 
        COMMENT 'ID do fechamento que incluiu esta hora extra' 
        AFTER tipo_pagamento
    ");
    echo "   ✅ Campo fechamento_pagamento_id criado\n";
    
    // Adiciona índice
    $pdo->exec("
        ALTER TABLE horas_extras
        ADD INDEX idx_fechamento_pagamento (fechamento_pagamento_id)
    ");
    echo "   ✅ Índice criado\n";
    
    // Adiciona chave estrangeira
    $pdo->exec("
        ALTER TABLE horas_extras
        ADD FOREIGN KEY (fechamento_pagamento_id) 
        REFERENCES fechamentos_pagamento(id) ON DELETE SET NULL
    ");
    echo "   ✅ Chave estrangeira adicionada\n";
    
    $pdo->commit();
    
    echo "\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "✅ MIGRAÇÃO APLICADA COM SUCESSO!\n";
    echo "=" . str_repeat("=", 70) . "\n\n";
    
    // 4. Mostra horas extras após a migração
    echo "4. Horas extras APÓS a migração:\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, 
               SUM(quantidade_horas) as total_horas,
               SUM(valor_total) as total_valor
        FROM horas_extras
        WHERE (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL)
        AND fechamento_pagamento_id IS NULL
    ");
    $depois = $stmt->fetch();
    echo "   - Registros DISPONÍVEIS para fechamento: {$depois['total']}\n";
    echo "   - Total de horas: " . number_format($depois['total_horas'], 2, ',', '.') . "h\n";
    echo "   - Valor total: R$ " . number_format($depois['total_valor'], 2, ',', '.') . "\n\n";
    
    echo "✅ Agora você pode criar fechamentos de pagamento!\n";
    echo "   As horas extras acima aparecerão nos fechamentos.\n\n";
    
    // 5. Lista as horas extras disponíveis
    echo "5. Horas extras DISPONÍVEIS para fechamento:\n";
    echo "   " . str_repeat("-", 100) . "\n";
    printf("   %-4s | %-30s | %-12s | %-8s | %-12s\n", 
        "ID", "Colaborador", "Data", "Horas", "Valor");
    echo "   " . str_repeat("-", 100) . "\n";
    
    $stmt = $pdo->query("
        SELECT he.id, c.nome_completo, he.data_trabalho, 
               he.quantidade_horas, he.valor_total
        FROM horas_extras he
        INNER JOIN colaboradores c ON he.colaborador_id = c.id
        WHERE (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
        AND he.fechamento_pagamento_id IS NULL
        ORDER BY he.data_trabalho DESC, he.created_at DESC
        LIMIT 20
    ");
    
    $horas = $stmt->fetchAll();
    foreach ($horas as $he) {
        printf("   %-4d | %-30s | %-12s | %6.2fh | R$ %8.2f\n",
            $he['id'],
            substr($he['nome_completo'], 0, 30),
            date('d/m/Y', strtotime($he['data_trabalho'])),
            $he['quantidade_horas'],
            $he['valor_total']
        );
    }
    
    if (count($horas) >= 20) {
        echo "   ... e mais\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "\n❌ ERRO ao aplicar migração:\n";
    echo "   " . $e->getMessage() . "\n\n";
    
    echo "Tente executar manualmente:\n";
    echo "   mysql -u root -p seu_banco < migracao_controle_horas_extras_pagas.sql\n\n";
    
    exit(1);
}

