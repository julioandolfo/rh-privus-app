<?php
/**
 * Script PHP para limpar etapas duplicadas do processo seletivo
 * 
 * IMPORTANTE: Faça backup do banco de dados antes de executar!
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Apenas ADMIN pode executar
if (!has_role(['ADMIN'])) {
    die('Acesso negado. Apenas ADMIN pode executar este script.');
}

$pdo = getDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>Limpeza de Etapas Duplicadas</h2>\n";
echo "<pre>\n";

try {
    // ============================================
    // 1. Verificar duplicatas antes
    // ============================================
    echo "1. Verificando duplicatas...\n";
    $stmt = $pdo->query("
        SELECT 
            nome,
            codigo,
            vaga_id,
            COUNT(*) as total,
            GROUP_CONCAT(id ORDER BY id) as ids,
            MIN(id) as id_manter
        FROM processo_seletivo_etapas
        WHERE vaga_id IS NULL
        GROUP BY nome, codigo, vaga_id
        HAVING COUNT(*) > 1
    ");
    $duplicatas = $stmt->fetchAll();
    
    if (empty($duplicatas)) {
        echo "✓ Nenhuma duplicata encontrada!\n";
        exit;
    }
    
    echo "Encontradas " . count($duplicatas) . " grupos de duplicatas:\n";
    foreach ($duplicatas as $dup) {
        echo "  - {$dup['nome']} ({$dup['codigo']}): IDs {$dup['ids']} -> Manter ID {$dup['id_manter']}\n";
    }
    
    // ============================================
    // 2. Iniciar transação
    // ============================================
    echo "\n2. Iniciando transação...\n";
    $pdo->beginTransaction();
    
    // ============================================
    // 3. Atualizar candidaturas_etapas
    // ============================================
    echo "3. Atualizando candidaturas_etapas...\n";
    $stmt = $pdo->prepare("
        UPDATE candidaturas_etapas ce
        INNER JOIN processo_seletivo_etapas e1 ON ce.etapa_id = e1.id
        INNER JOIN processo_seletivo_etapas e2 
            ON e1.nome = e2.nome 
            AND e1.codigo = e2.codigo 
            AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
            AND e2.id < e1.id
        SET ce.etapa_id = e2.id
        WHERE ce.etapa_id = e1.id
        AND NOT EXISTS (
            SELECT 1 
            FROM candidaturas_etapas ce2 
            WHERE ce2.candidatura_id = ce.candidatura_id 
            AND ce2.etapa_id = e2.id
        )
    ");
    $stmt->execute();
    $atualizados = $stmt->rowCount();
    echo "  ✓ {$atualizados} registros atualizados\n";
    
    // Remover duplicatas de candidaturas_etapas
    $stmt = $pdo->prepare("
        DELETE ce1 FROM candidaturas_etapas ce1
        INNER JOIN candidaturas_etapas ce2 
            ON ce1.candidatura_id = ce2.candidatura_id 
            AND ce1.etapa_id != ce2.etapa_id
        INNER JOIN processo_seletivo_etapas e1 ON ce1.etapa_id = e1.id
        INNER JOIN processo_seletivo_etapas e2 ON ce2.etapa_id = e2.id
        WHERE e1.nome = e2.nome 
        AND e1.codigo = e2.codigo
        AND e1.id > e2.id
    ");
    $stmt->execute();
    $removidos = $stmt->rowCount();
    if ($removidos > 0) {
        echo "  ✓ {$removidos} registros duplicados removidos de candidaturas_etapas\n";
    }
    
    // ============================================
    // 4. Atualizar entrevistas
    // ============================================
    echo "4. Atualizando entrevistas...\n";
    $stmt = $pdo->prepare("
        UPDATE entrevistas e
        INNER JOIN processo_seletivo_etapas e1 ON e.etapa_id = e1.id
        INNER JOIN processo_seletivo_etapas e2 
            ON e1.nome = e2.nome 
            AND e1.codigo = e2.codigo 
            AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
            AND e2.id < e1.id
        SET e.etapa_id = e2.id
        WHERE e.etapa_id = e1.id
    ");
    $stmt->execute();
    $atualizados = $stmt->rowCount();
    echo "  ✓ {$atualizados} registros atualizados\n";
    
    // ============================================
    // 5. Atualizar formularios_cultura
    // ============================================
    echo "5. Atualizando formularios_cultura...\n";
    $stmt = $pdo->prepare("
        UPDATE formularios_cultura fc
        INNER JOIN processo_seletivo_etapas e1 ON fc.etapa_id = e1.id
        INNER JOIN processo_seletivo_etapas e2 
            ON e1.nome = e2.nome 
            AND e1.codigo = e2.codigo 
            AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
            AND e2.id < e1.id
        SET fc.etapa_id = e2.id
        WHERE fc.etapa_id = e1.id
    ");
    $stmt->execute();
    $atualizados = $stmt->rowCount();
    echo "  ✓ {$atualizados} registros atualizados\n";
    
    // ============================================
    // 6. Excluir etapas duplicadas
    // ============================================
    echo "6. Excluindo etapas duplicadas...\n";
    $stmt = $pdo->prepare("
        DELETE e1 FROM processo_seletivo_etapas e1
        INNER JOIN processo_seletivo_etapas e2 
            ON e1.nome = e2.nome 
            AND e1.codigo = e2.codigo 
            AND (e1.vaga_id = e2.vaga_id OR (e1.vaga_id IS NULL AND e2.vaga_id IS NULL))
        WHERE e1.id > e2.id
        AND e1.vaga_id IS NULL
    ");
    $stmt->execute();
    $excluidas = $stmt->rowCount();
    echo "  ✓ {$excluidas} etapas duplicadas excluídas\n";
    
    // ============================================
    // 7. Verificar resultado
    // ============================================
    echo "\n7. Verificando resultado...\n";
    $stmt = $pdo->query("
        SELECT 
            nome,
            codigo,
            vaga_id,
            COUNT(*) as total
        FROM processo_seletivo_etapas
        WHERE vaga_id IS NULL
        GROUP BY nome, codigo, vaga_id
        HAVING COUNT(*) > 1
    ");
    $restantes = $stmt->fetchAll();
    
    if (empty($restantes)) {
        echo "✓ Limpeza concluída com sucesso! Nenhuma duplicata restante.\n";
        $pdo->commit();
        echo "\n✓ Transação confirmada!\n";
    } else {
        echo "⚠ Ainda existem duplicatas:\n";
        foreach ($restantes as $dup) {
            echo "  - {$dup['nome']} ({$dup['codigo']}): {$dup['total']} ocorrências\n";
        }
        $pdo->rollBack();
        echo "\n✗ Transação revertida devido a erros.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Transação revertida.\n";
}

echo "</pre>\n";

