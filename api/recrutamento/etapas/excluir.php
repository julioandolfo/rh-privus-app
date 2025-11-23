<?php
/**
 * API: Excluir Etapa do Processo
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
    
    $etapa_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (empty($etapa_id)) {
        throw new Exception('Etapa não informada');
    }
    
    // Verifica se é etapa padrão (não pode excluir etapas de vagas específicas)
    $stmt = $pdo->prepare("SELECT id FROM processo_seletivo_etapas WHERE id = ? AND vaga_id IS NULL");
    $stmt->execute([$etapa_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Etapa não encontrada ou não é uma etapa padrão');
    }
    
    // Verifica se está sendo usada em alguma vaga
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vagas_etapas WHERE etapa_id = ?");
    $stmt->execute([$etapa_id]);
    $uso = $stmt->fetch();
    
    if ($uso['total'] > 0) {
        throw new Exception('Esta etapa está sendo usada em ' . $uso['total'] . ' vaga(s). Remova das vagas antes de excluir.');
    }
    
    // Exclui a etapa
    $stmt = $pdo->prepare("DELETE FROM processo_seletivo_etapas WHERE id = ? AND vaga_id IS NULL");
    $stmt->execute([$etapa_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Etapa excluída com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

