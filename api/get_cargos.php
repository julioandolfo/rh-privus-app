<?php
/**
 * API - Busca Cargos por Empresa ou Setor
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'cargos' => []]);
    exit;
}

$empresa_id = $_GET['empresa_id'] ?? null;
$setor_id = $_GET['setor_id'] ?? null;

if (!$empresa_id && !$setor_id) {
    echo json_encode(['success' => false, 'cargos' => []]);
    exit;
}

try {
    $pdo = getDB();
    
    // Se tem setor_id, busca empresa do setor primeiro
    if ($setor_id) {
        $stmt_setor = $pdo->prepare("SELECT empresa_id FROM setores WHERE id = ?");
        $stmt_setor->execute([$setor_id]);
        $setor = $stmt_setor->fetch();
        if ($setor) {
            $empresa_id = $setor['empresa_id'];
        } else {
            echo json_encode(['success' => false, 'cargos' => []]);
            exit;
        }
    }
    
    // Busca cargos da empresa
    if ($empresa_id) {
        // Verifica se existe coluna status na tabela cargos
        $stmt_check = $pdo->query("SHOW COLUMNS FROM cargos LIKE 'status'");
        $has_status_column = $stmt_check->fetch() !== false;
        
        if ($has_status_column) {
            $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE empresa_id = ? AND (status = 'ativo' OR status IS NULL) ORDER BY nome_cargo");
        } else {
            $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE empresa_id = ? ORDER BY nome_cargo");
        }
        $stmt->execute([$empresa_id]);
        $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'cargos' => $cargos]);
    } else {
        echo json_encode(['success' => false, 'cargos' => []]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'cargos' => [], 'error' => $e->getMessage()]);
}

