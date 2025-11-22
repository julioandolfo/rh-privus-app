<?php
/**
 * API para enviar notificações push via OneSignal
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/../../includes/functions.php';
} catch (Throwable $e) {
    error_log("Erro ao carregar functions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar dependências: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Valida autenticação (apenas ADMIN ou RH pode enviar)
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Tenta passar sessão via cookie se não estiver disponível
    if (!isset($_SESSION['usuario']) && isset($_COOKIE[session_name()])) {
        session_start();
    }
    
    if (!isset($_SESSION['usuario']) || !in_array($_SESSION['usuario']['role'], ['ADMIN', 'RH'])) {
        error_log("Acesso negado - Sessão: " . (isset($_SESSION['usuario']) ? 'existe' : 'não existe'));
        error_log("Role: " . ($_SESSION['usuario']['role'] ?? 'não definido'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
} catch (Throwable $e) {
    error_log("Erro na validação de sessão: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$colaborador_id = $input['colaborador_id'] ?? null;
$titulo = $input['titulo'] ?? 'Notificação';
$mensagem = $input['mensagem'] ?? '';
// Detecta base path automaticamente
$basePath = '/rh'; // Padrão produção
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($requestUri, '/rh-privus') !== false) {
    $basePath = '/rh-privus';
} elseif (strpos($requestUri, '/rh/') !== false || preg_match('#^/rh[^a-z]#', $requestUri)) {
    $basePath = '/rh';
}

$url = $input['url'] ?? $basePath . '/pages/dashboard.php';
$icone = $input['icone'] ?? $basePath . '/assets/avatar-privus.png';

if (empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Mensagem é obrigatória']);
    exit;
}

// Validação: precisa de pelo menos um identificador OU broadcast
if (!$usuario_id && !$colaborador_id && !isset($input['broadcast'])) {
    echo json_encode(['success' => false, 'message' => 'Informe usuario_id, colaborador_id ou broadcast']);
    exit;
}

require_once __DIR__ . '/../../includes/onesignal_service.php';

try {
    $result = onesignal_send_notification([
        'usuario_id' => $usuario_id,
        'colaborador_id' => $colaborador_id,
        'broadcast' => isset($input['broadcast']) ? (bool)$input['broadcast'] : false,
        'titulo' => $titulo,
        'mensagem' => $mensagem,
        'url' => $url,
        'icone' => $icone,
    ]);
    
    echo json_encode([
        'success' => true,
        'enviadas' => $result['enviadas'],
        'onesignal_id' => $result['onesignal_id'] ?? null,
        'response' => $result['response'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log("Erro em send.php: " . $e->getMessage());
    error_log("Arquivo: " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

