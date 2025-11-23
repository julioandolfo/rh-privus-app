<?php
/**
 * Script PHP para matar processos bloqueados no MySQL
 * 
 * IMPORTANTE: Use com cuidado! Isso pode interromper operações importantes.
 * Apenas ADMIN pode executar este script.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Apenas ADMIN pode executar
if (!has_role(['ADMIN'])) {
    die('Acesso negado. Apenas ADMIN pode executar este script.');
}

$pdo = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>Diagnóstico e Limpeza de Processos Bloqueados</h2>\n";
echo "<pre>\n";

try {
    // ============================================
    // 1. Ver processos bloqueados
    // ============================================
    echo "1. Verificando processos bloqueados...\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            ID,
            USER,
            HOST,
            DB,
            COMMAND,
            TIME as tempo_segundos,
            STATE,
            LEFT(INFO, 200) as query_preview
        FROM information_schema.PROCESSLIST
        WHERE COMMAND != 'Sleep'
        AND (STATE LIKE '%lock%' OR TIME > 5)
        ORDER BY TIME DESC
    ");
    $processos = $stmt->fetchAll();
    
    if (empty($processos)) {
        echo "✓ Nenhum processo bloqueado encontrado!\n";
        exit;
    }
    
    echo "Encontrados " . count($processos) . " processos:\n\n";
    foreach ($processos as $proc) {
        echo sprintf(
            "ID: %-6s | User: %-15s | Tempo: %-5s seg | Estado: %-20s\n",
            $proc['ID'],
            $proc['USER'],
            $proc['tempo_segundos'],
            $proc['STATE'] ?? 'N/A'
        );
        if ($proc['query_preview']) {
            echo "  Query: " . substr($proc['query_preview'], 0, 100) . "...\n";
        }
        echo "\n";
    }
    
    // ============================================
    // 2. Ver transações ativas
    // ============================================
    echo "\n2. Verificando transações ativas...\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            trx_id,
            trx_state,
            trx_mysql_thread_id,
            trx_tables_locked,
            trx_rows_locked,
            TIMESTAMPDIFF(SECOND, trx_started, NOW()) as tempo_segundos,
            LEFT(trx_query, 200) as query_preview
        FROM information_schema.innodb_trx
        ORDER BY trx_started ASC
    ");
    $transacoes = $stmt->fetchAll();
    
    if (!empty($transacoes)) {
        echo "Encontradas " . count($transacoes) . " transações ativas:\n\n";
        foreach ($transacoes as $trx) {
            echo sprintf(
                "Thread ID: %-6s | Estado: %-10s | Tempo: %-5s seg | Tabelas: %-3s | Linhas: %-5s\n",
                $trx['trx_mysql_thread_id'],
                $trx['trx_state'],
                $trx['tempo_segundos'],
                $trx['trx_tables_locked'],
                $trx['trx_rows_locked']
            );
            if ($trx['query_preview']) {
                echo "  Query: " . substr($trx['query_preview'], 0, 100) . "...\n";
            }
            echo "\n";
        }
    } else {
        echo "✓ Nenhuma transação ativa encontrada!\n";
    }
    
    // ============================================
    // 3. Ver locks esperando
    // ============================================
    echo "\n3. Verificando locks esperando...\n";
    echo str_repeat("=", 80) . "\n";
    
    try {
        $stmt = $pdo->query("
            SELECT 
                r.trx_id waiting_trx_id,
                r.trx_mysql_thread_id waiting_thread,
                LEFT(r.trx_query, 200) waiting_query,
                b.trx_id blocking_trx_id,
                b.trx_mysql_thread_id blocking_thread,
                LEFT(b.trx_query, 200) blocking_query
            FROM information_schema.innodb_lock_waits w
            INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
            INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id
        ");
        $locks = $stmt->fetchAll();
        
        if (!empty($locks)) {
            echo "Encontrados " . count($locks) . " locks esperando:\n\n";
            foreach ($locks as $lock) {
                echo "Thread esperando: " . $lock['waiting_thread'] . "\n";
                echo "Thread bloqueando: " . $lock['blocking_thread'] . "\n";
                echo "Query bloqueando: " . substr($lock['blocking_query'] ?? 'N/A', 0, 100) . "...\n\n";
            }
        } else {
            echo "✓ Nenhum lock esperando encontrado!\n";
        }
    } catch (Exception $e) {
        echo "⚠ Não foi possível verificar locks (pode não estar disponível nesta versão do MySQL)\n";
    }
    
    // ============================================
    // 4. Matar processos com mais de 30 segundos
    // ============================================
    echo "\n4. Processos com mais de 30 segundos (candidatos para matar):\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            ID,
            USER,
            TIME as tempo_segundos,
            STATE,
            LEFT(INFO, 200) as query_preview
        FROM information_schema.PROCESSLIST
        WHERE COMMAND != 'Sleep'
        AND TIME > 30
        AND ID != CONNECTION_ID()
        ORDER BY TIME DESC
    ");
    $processos_antigos = $stmt->fetchAll();
    
    if (!empty($processos_antigos)) {
        echo "Encontrados " . count($processos_antigos) . " processos antigos:\n\n";
        
        $ids_para_matar = [];
        foreach ($processos_antigos as $proc) {
            echo sprintf(
                "ID: %-6s | User: %-15s | Tempo: %-5s seg\n",
                $proc['ID'],
                $proc['USER'],
                $proc['tempo_segundos']
            );
            if ($proc['query_preview']) {
                echo "  Query: " . substr($proc['query_preview'], 0, 100) . "...\n";
            }
            $ids_para_matar[] = $proc['ID'];
            echo "\n";
        }
        
        // Pergunta se quer matar
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "ATENÇÃO: Matar processos pode interromper operações importantes!\n";
        echo "IDs que serão mortos: " . implode(', ', $ids_para_matar) . "\n";
        echo "\n";
        echo "Para matar estes processos, descomente as linhas abaixo no código PHP:\n";
        echo "// foreach (\$ids_para_matar as \$id) {\n";
        echo "//     try {\n";
        echo "//         \$pdo->exec(\"KILL \" . \$id);\n";
        echo "//         echo \"✓ Processo \$id morto com sucesso\\n\";\n";
        echo "//     } catch (Exception \$e) {\n";
        echo "//         echo \"✗ Erro ao matar processo \$id: \" . \$e->getMessage() . \"\\n\";\n";
        echo "//     }\n";
        echo "// }\n";
        
        // Descomente as linhas abaixo para matar automaticamente:
        /*
        foreach ($ids_para_matar as $id) {
            try {
                $pdo->exec("KILL " . $id);
                echo "✓ Processo $id morto com sucesso\n";
            } catch (Exception $e) {
                echo "✗ Erro ao matar processo $id: " . $e->getMessage() . "\n";
            }
        }
        */
    } else {
        echo "✓ Nenhum processo antigo encontrado!\n";
    }
    
    // ============================================
    // 5. Ver configurações de timeout
    // ============================================
    echo "\n5. Configurações de timeout:\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'innodb_lock_wait_timeout'");
    $timeout = $stmt->fetch();
    echo "innodb_lock_wait_timeout: " . ($timeout['Value'] ?? 'N/A') . " segundos\n";
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'lock_wait_timeout'");
    $timeout2 = $stmt->fetch();
    echo "lock_wait_timeout: " . ($timeout2['Value'] ?? 'N/A') . " segundos\n";
    
    echo "\n✓ Diagnóstico concluído!\n";
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";

