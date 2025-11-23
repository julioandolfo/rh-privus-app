<?php
/**
 * API: Excluir Componente da Landing Page
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $usuario = $_SESSION['usuario'];
    
    $componente_id = (int)($_GET['id'] ?? 0);
    
    if (empty($componente_id)) {
        throw new Exception('Componente não informado');
    }
    
    // Busca componente e verifica permissão
    $stmt = $pdo->prepare("
        SELECT lp.vaga_id, v.empresa_id
        FROM vagas_landing_page_componentes c
        INNER JOIN vagas_landing_pages lp ON c.landing_page_id = lp.id
        INNER JOIN vagas v ON lp.vaga_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$componente_id]);
    $componente = $stmt->fetch();
    
    if (!$componente) {
        throw new Exception('Componente não encontrado');
    }
    
    if (!can_access_empresa($componente['empresa_id'])) {
        throw new Exception('Sem permissão');
    }
    
    // Exclui componente
    $stmt = $pdo->prepare("DELETE FROM vagas_landing_page_componentes WHERE id = ?");
    $stmt->execute([$componente_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Componente excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

