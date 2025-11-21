<?php
/**
 * API - Busca Colaboradores que podem ser Líderes
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

$empresa_id = $_GET['empresa_id'] ?? null;
$setor_id = $_GET['setor_id'] ?? null;
$nivel_hierarquico_id = $_GET['nivel_hierarquico_id'] ?? null;
$excluir_id = $_GET['excluir_id'] ?? null; // Para não mostrar o próprio colaborador ao editar

$usuario = $_SESSION['usuario'];

if (!$empresa_id) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $where = ["c.empresa_id = ?", "c.status = 'ativo'"];
    $params = [$empresa_id];
    
    if ($setor_id) {
        $where[] = "c.setor_id = ?";
        $params[] = $setor_id;
    }
    
    // Se tiver nível hierárquico, busca apenas líderes de nível superior
    if ($nivel_hierarquico_id) {
        $where[] = "nh.nivel < (SELECT nivel FROM niveis_hierarquicos WHERE id = ?)";
        $params[] = $nivel_hierarquico_id;
    }
    
    if ($excluir_id) {
        $where[] = "c.id != ?";
        $params[] = $excluir_id;
    }
    
    // Se for RH, limita à empresa do usuário
    if ($usuario['role'] === 'RH') {
        $where[] = "c.empresa_id = ?";
        $params[] = $usuario['empresa_id'];
    }
    
    $where_sql = 'WHERE ' . implode(' AND ', $where);
    
    $sql = "
        SELECT c.id, c.nome_completo, nh.nome as nivel_nome, nh.nivel as nivel_numero
        FROM colaboradores c
        LEFT JOIN niveis_hierarquicos nh ON c.nivel_hierarquico_id = nh.id
        $where_sql
        ORDER BY nh.nivel ASC, c.nome_completo ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lideres = $stmt->fetchAll();
    
    echo json_encode($lideres);
} catch (PDOException $e) {
    echo json_encode([]);
}

