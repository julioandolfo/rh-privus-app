<?php
/**
 * API: Busca templates de descrição para um tipo de ocorrência
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ocorrencias_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$tipo_id = $_GET['tipo_id'] ?? null;

try {
    $templates = get_templates_descricao($tipo_id);
    
    echo json_encode(['success' => true, 'templates' => $templates]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar templates: ' . $e->getMessage()]);
}

