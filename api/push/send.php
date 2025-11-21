<?php
/**
 * API para enviar notificações push
 * Suporta: usuario_id, colaborador_id, ou broadcast
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Valida autenticação (apenas ADMIN ou RH pode enviar)
$usuario = null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
}

if (!$usuario || !in_array($usuario['role'], ['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'] ?? null;
$colaborador_id = $input['colaborador_id'] ?? null;
$titulo = $input['titulo'] ?? 'Notificação';
$mensagem = $input['mensagem'] ?? '';
$url = $input['url'] ?? '/rh-privus/pages/dashboard.php';
$icone = $input['icone'] ?? '/rh-privus/assets/media/logos/favicon.png';

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
    $pdo = getDB();
    
    // Busca chaves VAPID
    $stmt = $pdo->query("SELECT public_key, private_key FROM vapid_keys ORDER BY id DESC LIMIT 1");
    $vapid = $stmt->fetch();
    
    if (!$vapid) {
        throw new Exception('Chaves VAPID não configuradas. Execute scripts/gerar_vapid_keys.php');
    }
    
    // Configura WebPush
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:noreply@privus.com.br',
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ],
    ];
    
    $webPush = new WebPush($auth);
    
    // Busca subscriptions baseado no critério
    if ($colaborador_id) {
        // Envia para colaborador específico
        $stmt = $pdo->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE colaborador_id = ?
        ");
        $stmt->execute([$colaborador_id]);
    } elseif ($usuario_id) {
        // Envia para usuário específico
        $stmt = $pdo->prepare("
            SELECT endpoint, p256dh, auth 
            FROM push_subscriptions 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$usuario_id]);
    } else {
        // Broadcast: envia para todos
        $stmt = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
    }
    
    $subscriptions = $stmt->fetchAll();
    
    if (empty($subscriptions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma subscription encontrada para o destinatário'
        ]);
        exit;
    }
    
    $enviadas = 0;
    $falhas = 0;
    
    $payload = json_encode([
        'title' => $titulo,
        'body' => $mensagem,
        'icon' => $icone,
        'badge' => '/rh-privus/assets/media/logos/favicon.png',
        'data' => [
            'url' => $url
        ]
    ]);
    
    foreach ($subscriptions as $sub) {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys' => [
                'p256dh' => $sub['p256dh'],
                'auth' => $sub['auth']
            ]
        ]);
        
        $result = $webPush->sendOneNotification($subscription, $payload);
        
        if ($result->isSuccess()) {
            $enviadas++;
        } else {
            $falhas++;
            // Remove subscription inválida
            if ($result->isSubscriptionExpired()) {
                $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
                $stmt->execute([$sub['endpoint']]);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'enviadas' => $enviadas,
        'falhas' => $falhas,
        'total' => count($subscriptions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

