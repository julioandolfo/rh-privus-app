<?php
/**
 * API para deletar bônus de colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

check_permission(['ADMIN', 'RH']);

$id = (int)($_POST['id'] ?? 0);

if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'ID não informado']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("DELETE FROM colaboradores_bonus WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Bônus removido com sucesso!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao remover: ' . $e->getMessage()]);
}

