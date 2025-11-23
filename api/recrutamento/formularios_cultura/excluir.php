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
    
    // Verifica se está sendo usado em respostas de candidaturas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM formularios_cultura_respostas WHERE formulario_id = ?");
    $stmt->execute([$formulario_id]);
    $uso_respostas = $stmt->fetch();
    
    if ($uso_respostas['total'] > 0) {
        throw new Exception('Este formulário possui ' . $uso_respostas['total'] . ' resposta(s) de candidaturas. Não é possível excluir formulários com respostas.');
    }
    
    // Verifica se está vinculado a alguma etapa (opcional - apenas informativo)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM formularios_cultura WHERE id = ? AND etapa_id IS NOT NULL");
    $stmt->execute([$formulario_id]);
    $vinculado = $stmt->fetch();
    
    if ($vinculado['total'] > 0) {
        // Apenas aviso, não impede exclusão
        // O formulário está vinculado a uma etapa, mas isso não impede a exclusão
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

