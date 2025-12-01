<?php
/**
 * Script para verificar qual banco está conectado e aplicar migração se necessário
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== VERIFICAÇÃO DE BANCO DE DADOS E MIGRAÇÃO ===\n\n";

try {
    $pdo = getDB();
    
    // 1. Mostra informações do banco conectado
    echo "1. BANCO DE DADOS CONECTADO:\n";
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db_info = $stmt->fetch();
    echo "   Nome do banco: {$db_info['db_name']}\n";
    
    $stmt = $pdo->query("SELECT @@hostname as host");
    $host_info = $stmt->fetch();
    echo "   Host: {$host_info['host']}\n\n";
    
    // 2. Verifica se a migração existe
    echo "2. VERIFICANDO MIGRAÇÃO:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM horas_extras LIKE 'fechamento_pagamento_id'");
    $campo_existe = $stmt->fetch();
    
    if ($campo_existe) {
        echo "   ✅ Campo fechamento_pagamento_id: EXISTE\n";
        echo "   ✅ Migração JÁ APLICADA neste banco\n\n";
    } else {
        echo "   ❌ Campo fechamento_pagamento_id: NÃO EXISTE\n";
        echo "   ⚠️  MIGRAÇÃO NECESSÁRIA!\n\n";
        
        echo "Deseja aplicar a migração AGORA? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $resposta = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($resposta) === 's' || strtolower($resposta) === 'sim') {
            echo "\n3. APLICANDO MIGRAÇÃO...\n";
            
            $pdo->beginTransaction();
            
            try {
                // Adiciona o campo
                $pdo->exec("
                    ALTER TABLE horas_extras
                    ADD COLUMN fechamento_pagamento_id INT NULL 
                    COMMENT 'ID do fechamento que incluiu esta hora extra' 
                    AFTER tipo_pagamento
                ");
                echo "   ✅ Campo criado\n";
                
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
                
                echo "\n   " . str_repeat("=", 70) . "\n";
                echo "   ✅ MIGRAÇÃO APLICADA COM SUCESSO!\n";
                echo "   " . str_repeat("=", 70) . "\n\n";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            echo "\n   ⚠️  Migração cancelada pelo usuário.\n\n";
            exit(0);
        }
    }
    
    // 3. Estatísticas de horas extras
    echo "3. ESTATÍSTICAS DE HORAS EXTRAS:\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL THEN 1 ELSE 0 END) as tipo_dinheiro,
            SUM(CASE WHEN tipo_pagamento = 'banco_horas' THEN 1 ELSE 0 END) as tipo_banco
        FROM horas_extras
    ");
    $total = $stmt->fetch();
    
    echo "   Total de horas extras: {$total['total']}\n";
    echo "   - Tipo dinheiro: {$total['tipo_dinheiro']}\n";
    echo "   - Tipo banco_horas: {$total['tipo_banco']}\n\n";
    
    if ($total['tipo_dinheiro'] > 0) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as qtd,
                SUM(quantidade_horas) as total_horas,
                SUM(valor_total) as total_valor
            FROM horas_extras
            WHERE (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL)
            AND fechamento_pagamento_id IS NULL
        ");
        $disponiveis = $stmt->fetch();
        
        echo "   HORAS EXTRAS DISPONÍVEIS PARA FECHAMENTO:\n";
        echo "   - Registros: {$disponiveis['qtd']}\n";
        echo "   - Total horas: " . number_format($disponiveis['total_horas'], 2, ',', '.') . "h\n";
        echo "   - Valor total: R$ " . number_format($disponiveis['total_valor'], 2, ',', '.') . "\n\n";
        
        if ($disponiveis['qtd'] > 0) {
            echo "   ✅ Estas horas extras APARECERÃO nos fechamentos!\n\n";
            
            // Lista as 10 primeiras
            echo "4. PRIMEIRAS 10 HORAS EXTRAS DISPONÍVEIS:\n";
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
                ORDER BY he.data_trabalho DESC
                LIMIT 10
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
            echo "\n";
        } else {
            echo "   ⚠️  Todas as horas extras tipo 'dinheiro' já foram pagas!\n\n";
        }
    }
    
    echo "=" . str_repeat("=", 70) . "\n";
    echo "✅ VERIFICAÇÃO CONCLUÍDA!\n";
    echo "=" . str_repeat("=", 70) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n\n";
    exit(1);
}

