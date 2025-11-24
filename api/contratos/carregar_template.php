<?php
/**
 * API para carregar template e gerar preview
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/contratos_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$template_id = intval($_GET['template_id'] ?? 0);
$colaborador_id = intval($_GET['colaborador_id'] ?? 0);

if ($template_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do template inválido']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca template
    $stmt = $pdo->prepare("SELECT conteudo_html FROM contratos_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Template não encontrado');
    }
    
    $preview = '';
    
    // Se tem colaborador, gera preview
    if ($colaborador_id > 0) {
        $colaborador = buscar_dados_colaborador_completos($colaborador_id);
        if ($colaborador) {
            $preview = substituir_variaveis_contrato($template['conteudo_html'], $colaborador);
        }
    }
    
    echo json_encode([
        'success' => true,
        'conteudo_html' => $template['conteudo_html'],
        'preview' => $preview
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

