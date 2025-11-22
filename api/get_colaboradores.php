<?php
/**
 * API para buscar colaboradores
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$empresa_id = $_GET['empresa_id'] ?? 0;
$lider_id = $_GET['lider_id'] ?? 0;
$status = $_GET['status'] ?? '';
$com_salario = $_GET['com_salario'] ?? '0';

// Se tem lider_id, busca liderados desse líder
if ($lider_id) {
    $where = ["lider_id = ?", "status = 'ativo'"];
    $params = [$lider_id];
    
    $sql = "SELECT id, nome_completo, foto FROM colaboradores WHERE " . implode(' AND ', $where) . " ORDER BY nome_completo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($colaboradores);
    exit;
}

if (empty($empresa_id)) {
    echo json_encode([]);
    exit;
}

// Verifica permissão usando função de autenticação
if (!can_access_empresa($empresa_id)) {
    echo json_encode([]);
    exit;
}

$where = ["empresa_id = ?"];
$params = [$empresa_id];

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($com_salario === '1') {
    $where[] = "salario IS NOT NULL AND salario > 0";
}

$sql = "SELECT id, nome_completo, salario, empresa_id FROM colaboradores WHERE " . implode(' AND ', $where) . " ORDER BY nome_completo";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($colaboradores);

