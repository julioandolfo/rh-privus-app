<?php
/**
 * Webhook Handler para Autentique
 * Recebe eventos do Autentique e atualiza status dos contratos
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/autentique_service.php';

// Log de requisições (para debug)
$log_file = __DIR__ . '/../../logs/webhook_autentique.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function log_webhook($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_webhook('Método não permitido: ' . $_SERVER['REQUEST_METHOD']);
    exit;
}

// Lê dados do webhook
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

log_webhook('Webhook recebido: ' . json_encode($data));

if (!$data) {
    http_response_code(400);
    log_webhook('Payload inválido');
    exit;
}

// Valida secret do webhook (se configurado)
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    if ($config) {
        // Identifica tipo de evento para saber qual secret validar
        $evento_tipo = $data['event'] ?? $data['type'] ?? 'unknown';
        $is_document_event = strpos($evento_tipo, 'document.') === 0;
        $is_signer_event = strpos($evento_tipo, 'signer.') === 0 || $evento_tipo === 'document.signed';
        
        // Pega o secret apropriado
        $expected_secret = null;
        if ($is_document_event && !empty($config['webhook_documento_secret'])) {
            $expected_secret = $config['webhook_documento_secret'];
        } elseif ($is_signer_event && !empty($config['webhook_assinatura_secret'])) {
            $expected_secret = $config['webhook_assinatura_secret'];
        } elseif (!empty($config['webhook_secret'])) {
            // Fallback para secret antigo (compatibilidade)
            $expected_secret = $config['webhook_secret'];
        }
        
        // Valida secret se estiver configurado
        if ($expected_secret) {
            // Função helper para pegar todos os headers
            if (!function_exists('getallheaders')) {
                function getallheaders() {
                    $headers = [];
                    foreach ($_SERVER as $name => $value) {
                        if (substr($name, 0, 5) == 'HTTP_') {
                            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                        }
                    }
                    return $headers;
                }
            }
            
            // Tenta vários headers possíveis (depende da implementação do Autentique)
            $received_secret = null;
            $headers = getallheaders();
            
            // Verifica headers comuns para webhook secrets
            $possible_headers = [
                'X-Autentique-Secret',
                'X-Webhook-Secret',
                'X-Secret',
                'Authorization' // Alguns serviços enviam como Bearer token
            ];
            
            foreach ($possible_headers as $header_name) {
                // Normaliza nome do header (HTTP_ prefixo e uppercase)
                $http_header = 'HTTP_' . strtoupper(str_replace('-', '_', $header_name));
                if (isset($_SERVER[$http_header])) {
                    $received_secret = $_SERVER[$http_header];
                    // Se for Authorization Bearer, extrai o token
                    if ($header_name === 'Authorization' && strpos($received_secret, 'Bearer ') === 0) {
                        $received_secret = substr($received_secret, 7);
                    }
                    break;
                }
                // Também verifica em getallheaders() (mais direto)
                if (isset($headers[$header_name])) {
                    $received_secret = $headers[$header_name];
                    if ($header_name === 'Authorization' && strpos($received_secret, 'Bearer ') === 0) {
                        $received_secret = substr($received_secret, 7);
                    }
                    break;
                }
            }
            
            // Se não encontrou no header, tenta no payload (alguns serviços enviam assim)
            if (!$received_secret && isset($data['secret'])) {
                $received_secret = $data['secret'];
            }
            
            if (!$received_secret || $received_secret !== $expected_secret) {
                http_response_code(401);
                log_webhook('Secret inválido para evento: ' . $evento_tipo);
                log_webhook('Headers recebidos: ' . json_encode($headers));
                log_webhook('Secret esperado (primeiros 10 chars): ' . substr($expected_secret, 0, 10) . '...');
                log_webhook('Secret recebido: ' . ($received_secret ? substr($received_secret, 0, 10) . '...' : 'não fornecido'));
                echo json_encode(['success' => false, 'message' => 'Secret inválido']);
                exit;
            }
            
            log_webhook('Secret validado com sucesso para evento: ' . $evento_tipo);
        }
    }
} catch (Exception $e) {
    log_webhook('Erro ao validar secret: ' . $e->getMessage());
    // Continua mesmo se houver erro na validação (não bloqueia webhook)
}

try {
    $pdo = getDB();
    
    // Identifica tipo de evento
    $evento_tipo = $data['event'] ?? $data['type'] ?? 'unknown';
    $document_id = $data['document']['id'] ?? $data['documentId'] ?? null;
    
    if (!$document_id) {
        log_webhook('Document ID não encontrado no payload');
        http_response_code(400);
        exit;
    }
    
    // Busca contrato pelo document_id do Autentique
    $stmt = $pdo->prepare("SELECT id FROM contratos WHERE autentique_document_id = ?");
    $stmt->execute([$document_id]);
    $contrato = $stmt->fetch();
    
    if (!$contrato) {
        log_webhook("Contrato não encontrado para document_id: $document_id");
        http_response_code(404);
        exit;
    }
    
    $contrato_id = $contrato['id'];
    
    // Registra evento
    $stmt = $pdo->prepare("
        INSERT INTO contratos_eventos (contrato_id, tipo_evento, dados_json)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $contrato_id,
        $evento_tipo,
        json_encode($data)
    ]);
    
    // Processa eventos específicos
    switch ($evento_tipo) {
        case 'document.signed':
        case 'signer.signed':
            // Documento ou signatário assinou
            $signer_id = $data['signer']['id'] ?? $data['signerId'] ?? null;
            $signed_at = $data['signer']['signedAt'] ?? $data['signedAt'] ?? date('Y-m-d H:i:s');
            
            if ($signer_id) {
                // Atualiza signatário
                $stmt = $pdo->prepare("
                    UPDATE contratos_signatarios 
                    SET assinado = 1, data_assinatura = ?
                    WHERE autentique_signer_id = ? AND contrato_id = ?
                ");
                $stmt->execute([$signed_at, $signer_id, $contrato_id]);
                
                log_webhook("Signatário $signer_id assinou contrato $contrato_id");
            }
            
            // Verifica se todos assinaram
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total, SUM(assinado) as assinados
                FROM contratos_signatarios 
                WHERE contrato_id = ?
            ");
            $stmt->execute([$contrato_id]);
            $stats = $stmt->fetch();
            
            if ($stats && $stats['total'] > 0 && $stats['assinados'] == $stats['total']) {
                // Todos assinaram
                $stmt = $pdo->prepare("UPDATE contratos SET status = 'assinado' WHERE id = ?");
                $stmt->execute([$contrato_id]);
                log_webhook("Contrato $contrato_id totalmente assinado");
                
                // TODO: Enviar notificação de conclusão
            } else {
                // Ainda há pendências
                $stmt = $pdo->prepare("UPDATE contratos SET status = 'aguardando' WHERE id = ?");
                $stmt->execute([$contrato_id]);
            }
            break;
            
        case 'document.viewed':
            // Documento foi visualizado
            log_webhook("Contrato $contrato_id visualizado");
            break;
            
        case 'document.cancelled':
            // Documento foi cancelado
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'cancelado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            log_webhook("Contrato $contrato_id cancelado");
            break;
    }
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Evento processado']);
    
} catch (Exception $e) {
    log_webhook('Erro ao processar webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

