<?php
/**
 * API: Busca campos dinâmicos de um tipo de ocorrência
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$tipo_id = $_GET['tipo_id'] ?? null;

if (!$tipo_id) {
    echo json_encode(['success' => false, 'message' => 'ID do tipo não informado']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM tipos_ocorrencias_campos
        WHERE tipo_ocorrencia_id = ?
        ORDER BY ordem ASC
    ");
    $stmt->execute([$tipo_id]);
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'campos' => $campos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar campos: ' . $e->getMessage()]);
}

