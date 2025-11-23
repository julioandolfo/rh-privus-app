<?php
/**
 * API: Excluir Formulário de Cultura
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    
    $formulario_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (empty($formulario_id)) {
        throw new Exception('Formulário não informado');
    }
    
    // Verifica se existe
    $stmt = $pdo->prepare("SELECT id FROM formularios_cultura WHERE id = ?");
    $stmt->execute([$formulario_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Formulário não encontrado');
    }
    
    // Verifica se está sendo usado em alguma etapa
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM processo_seletivo_etapas WHERE formulario_cultura_id = ?");
    $stmt->execute([$formulario_id]);
    $uso = $stmt->fetch();
    
    if ($uso['total'] > 0) {
        throw new Exception('Este formulário está sendo usado em ' . $uso['total'] . ' etapa(s). Remova das etapas antes de excluir.');
    }
    
    // Exclui campos primeiro (cascade)
    $stmt = $pdo->prepare("DELETE FROM formularios_cultura_campos WHERE formulario_id = ?");
    $stmt->execute([$formulario_id]);
    
    // Exclui o formulário
    $stmt = $pdo->prepare("DELETE FROM formularios_cultura WHERE id = ?");
    $stmt->execute([$formulario_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Formulário excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

