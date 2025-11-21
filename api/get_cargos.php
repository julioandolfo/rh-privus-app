<?php
/**
 * API - Busca Cargos por Empresa
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

if (!$empresa_id) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_cargo");
    $stmt->execute([$empresa_id]);
    $cargos = $stmt->fetchAll();
    echo json_encode($cargos);
} catch (PDOException $e) {
    echo json_encode([]);
}

