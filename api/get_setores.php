<?php
/**
 * API - Busca Setores por Empresa
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'setores' => []]);
    exit;
}

require_once __DIR__ . '/../includes/permissions.php';

$usuario = $_SESSION['usuario'];
$empresa_id = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;

try {
    $pdo = getDB();
    $where = ["1=1"];
    $params = [];
    
    // Verifica se existe coluna status na tabela setores
    $stmt_check = $pdo->query("SHOW COLUMNS FROM setores LIKE 'status'");
    $has_status_column = $stmt_check->fetch() !== false;
    
    if ($has_status_column) {
        $where[] = "(s.status = 'ativo' OR s.status IS NULL)";
    }
    
    if ($empresa_id) {
        $where[] = "s.empresa_id = ?";
        $params[] = $empresa_id;
    } else {
        // Aplica filtros de permissão
        if ($usuario['role'] === 'ADMIN') {
            // ADMIN vê todos
        } elseif ($usuario['role'] === 'RH') {
            if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $where[] = "s.empresa_id IN ($placeholders)";
                $params = array_merge($params, $usuario['empresas_ids']);
            } else {
                $where[] = "s.empresa_id = ?";
                $params[] = $usuario['empresa_id'] ?? 0;
            }
        } else {
            $where[] = "s.empresa_id = ?";
            $params[] = $usuario['empresa_id'] ?? 0;
        }
    }
    
    $where_sql = 'WHERE ' . implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.nome_setor, e.nome_fantasia as empresa_nome
        FROM setores s
        LEFT JOIN empresas e ON s.empresa_id = e.id
        $where_sql
        ORDER BY e.nome_fantasia, s.nome_setor
    ");
    $stmt->execute($params);
    $setores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retorna tanto 'data' quanto 'setores' para compatibilidade
    echo json_encode(['success' => true, 'data' => $setores, 'setores' => $setores]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'data' => [], 'setores' => [], 'error' => $e->getMessage()]);
}

