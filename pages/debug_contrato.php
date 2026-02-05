<?php
/**
 * DEBUG - Verificar estrutura de contratos
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$contrato_id = intval($_GET['id'] ?? 0);

echo "<h1>Debug - Contrato #$contrato_id</h1>";

// Verifica se a tabela existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'contratos'");
    $exists = $stmt->fetch();
    echo "<h2>Tabela 'contratos' existe: " . ($exists ? "SIM" : "NÃO") . "</h2>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erro ao verificar tabela: " . $e->getMessage() . "</p>";
}

// Mostra estrutura da tabela
try {
    echo "<h2>Estrutura da tabela 'contratos':</h2>";
    $stmt = $pdo->query("DESCRIBE contratos");
    $columns = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erro ao verificar estrutura: " . $e->getMessage() . "</p>";
}

// Busca contrato específico
if ($contrato_id > 0) {
    try {
        echo "<h2>Dados do contrato #$contrato_id:</h2>";
        $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
        $stmt->execute([$contrato_id]);
        $contrato = $stmt->fetch();
        
        if ($contrato) {
            echo "<pre>";
            print_r($contrato);
            echo "</pre>";
            
            // Busca colaborador
            echo "<h2>Dados do colaborador #" . $contrato['colaborador_id'] . ":</h2>";
            $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
            $stmt->execute([$contrato['colaborador_id']]);
            $colaborador = $stmt->fetch();
            echo "<pre>";
            print_r($colaborador);
            echo "</pre>";
            
            // Testa a query completa
            echo "<h2>Query completa com JOIN:</h2>";
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       col.nome_completo as colaborador_nome,
                       col.cpf as colaborador_cpf,
                       col.email_pessoal as colaborador_email,
                       u.nome as criado_por_nome,
                       t.nome as template_nome
                FROM contratos c
                INNER JOIN colaboradores col ON c.colaborador_id = col.id
                LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
                LEFT JOIN contratos_templates t ON c.template_id = t.id
                WHERE c.id = ?
            ");
            $stmt->execute([$contrato_id]);
            $result = $stmt->fetch();
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "<p style='color:red'>Contrato não encontrado!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Erro: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

// Lista todos os contratos
try {
    echo "<h2>Todos os contratos no sistema:</h2>";
    $stmt = $pdo->query("SELECT id, titulo, colaborador_id, status, created_at FROM contratos ORDER BY id DESC LIMIT 10");
    $contratos = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Título</th><th>Colaborador ID</th><th>Status</th><th>Criado em</th></tr>";
    foreach ($contratos as $c) {
        echo "<tr>";
        echo "<td>{$c['id']}</td>";
        echo "<td>{$c['titulo']}</td>";
        echo "<td>{$c['colaborador_id']}</td>";
        echo "<td>{$c['status']}</td>";
        echo "<td>{$c['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erro ao listar contratos: " . $e->getMessage() . "</p>";
}
?>
