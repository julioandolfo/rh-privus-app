<?php
/**
 * API para Obter Detalhes do PDI (objetivos e ações)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $pdi_id = (int)($_GET['id'] ?? 0);
    
    if ($pdi_id <= 0) {
        throw new Exception('ID do PDI inválido');
    }
    
    // Busca PDI principal
    $stmt = $pdo->prepare("
        SELECT p.*,
               c.nome_completo as colaborador_nome,
               c.foto as colaborador_foto,
               u.nome as criado_por_nome
        FROM pdis p
        INNER JOIN colaboradores c ON p.colaborador_id = c.id
        LEFT JOIN usuarios u ON p.criado_por = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pdi_id]);
    $pdi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pdi) {
        throw new Exception('PDI não encontrado');
    }
    
    // Verifica permissão
    if (!has_role(['ADMIN', 'RH']) && $usuario['id'] != $pdi['criado_por'] && $usuario['colaborador_id'] != $pdi['colaborador_id']) {
        throw new Exception('Você não tem permissão para visualizar este PDI');
    }
    
    // Busca objetivos
    $stmt = $pdo->prepare("SELECT * FROM pdi_objetivos WHERE pdi_id = ? ORDER BY ordem ASC");
    $stmt->execute([$pdi_id]);
    $objetivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca ações
    $stmt = $pdo->prepare("SELECT * FROM pdi_acoes WHERE pdi_id = ? ORDER BY ordem ASC");
    $stmt->execute([$pdi_id]);
    $acoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pdi' => $pdi,
        'objetivos' => $objetivos,
        'acoes' => $acoes
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

