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
$icone = $input['icone'] ?? $basePath . '/assets/media/logos/favicon.png';

if (empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Mensagem é obrigatória']);
    exit;
}

// Validação: precisa de pelo menos um identificador OU broadcast
if (!$usuario_id && !$colaborador_id && !isset($input['broadcast'])) {
    echo json_encode(['success' => false, 'message' => 'Informe usuario_id, colaborador_id ou broadcast']);
    exit;
}

try {
    if (!function_exists('getDB')) {
        throw new Exception('Função getDB não encontrada');
    }
    
    $pdo = getDB();
    
    if (!$pdo) {
        throw new Exception('Não foi possível conectar ao banco de dados');
    }
    
    // Busca configurações do OneSignal
    try {
        $stmt = $pdo->query("SELECT app_id, rest_api_key FROM onesignal_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erro ao buscar configuração OneSignal: " . $e->getMessage());
        throw new Exception('Erro ao buscar configuração do OneSignal: ' . $e->getMessage());
    }
    
    if (!$config || empty($config['app_id']) || empty($config['rest_api_key'])) {
        throw new Exception('OneSignal não configurado corretamente');
    }
    
    // Busca player_ids baseado no critério
    if ($colaborador_id) {
        $stmt = $pdo->prepare("SELECT player_id FROM onesignal_subscriptions WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
    } elseif ($usuario_id) {
        $stmt = $pdo->prepare("SELECT player_id FROM onesignal_subscriptions WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        // Broadcast: envia para todos
        $stmt = $pdo->query("SELECT player_id FROM onesignal_subscriptions");
    }
    
    $subscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($subscriptions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma subscription encontrada para o destinatário'
        ]);
        exit;
    }
    
    // Prepara URL completa
    $baseUrl = get_base_url();
    if (strpos($url, 'http') !== 0) {
        $url = $baseUrl . '/' . ltrim($url, '/');
    }
    
    // Prepara ícone completo
    if (!empty($icone)) {
        if (strpos($icone, 'http') !== 0) {
            $icone = $baseUrl . '/' . ltrim($icone, '/');
        }
    } else {
        $icone = $baseUrl . $basePath . '/assets/media/logos/favicon.png';
    }
    
    // Prepara badge
    $badge = $baseUrl . $basePath . '/assets/media/logos/favicon.png';
    
    // Envia notificação via OneSignal REST API
    error_log("OneSignal API - Iniciando envio para " . count($subscriptions) . " player(s)");
    error_log("OneSignal API - Título: {$titulo}");
    error_log("OneSignal API - Mensagem: " . substr($mensagem, 0, 50));
    
    $ch = curl_init('https://onesignal.com/api/v1/notifications');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Timeout de 90 segundos para OneSignal
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Timeout de conexão de 30 segundos
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . $config['rest_api_key']
    ]);
    
    $payload = [
        'app_id' => $config['app_id'],
        'include_player_ids' => $subscriptions,
        'headings' => ['pt' => $titulo],
        'contents' => ['pt' => $mensagem],
        'url' => $url,
        'chrome_web_icon' => $icone,
        'chrome_web_badge' => $badge
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $elapsedTime = microtime(true) - $startTime;
    
    error_log("OneSignal API - Requisição concluída em " . round($elapsedTime, 2) . " segundos");
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log detalhado para debug
    error_log("OneSignal API - HTTP Code: {$httpCode}");
    error_log("OneSignal API - Player IDs: " . implode(', ', $subscriptions));
    error_log("OneSignal API - Response: " . substr($response, 0, 1000));
    
    if ($curlError) {
        error_log("OneSignal API - cURL Error: {$curlError}");
        error_log("OneSignal API - HTTP Code: {$httpCode}");
        error_log("OneSignal API - Tempo decorrido: " . round($elapsedTime, 2) . " segundos");
        error_log("OneSignal API - Número de subscriptions: " . count($subscriptions));
        
        // Mensagem mais amigável para timeout
        if (strpos($curlError, 'timeout') !== false || strpos($curlError, 'timed out') !== false) {
            throw new Exception('A requisição ao OneSignal demorou muito para responder. Isso pode acontecer se houver muitos dispositivos para notificar. Tente novamente em alguns instantes.');
        }
        
        throw new Exception('Erro ao comunicar com OneSignal: ' . $curlError);
    }
    
    if ($httpCode === 200 || $httpCode === 201) {
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("OneSignal API - JSON Decode Error: " . json_last_error_msg());
            throw new Exception('Erro ao decodificar resposta do OneSignal: ' . json_last_error_msg());
        }
        error_log("OneSignal API - Sucesso! OneSignal ID: " . ($result['id'] ?? 'N/A'));
        echo json_encode([
            'success' => true,
            'enviadas' => count($subscriptions),
            'onesignal_id' => $result['id'] ?? null,
            'response' => $result
        ]);
    } else {
        $error = json_decode($response, true);
        $errorMessage = 'Erro ao enviar notificação';
        if (isset($error['errors']) && is_array($error['errors']) && !empty($error['errors'])) {
            $errorMessage = is_array($error['errors'][0]) ? ($error['errors'][0]['message'] ?? $errorMessage) : $error['errors'][0];
        } elseif (isset($error['message'])) {
            $errorMessage = $error['message'];
        }
        error_log("OneSignal API - Erro: {$errorMessage} (HTTP {$httpCode})");
        error_log("OneSignal API - Error Response: " . print_r($error, true));
        throw new Exception($errorMessage . ' (HTTP ' . $httpCode . ')');
    }
    
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

