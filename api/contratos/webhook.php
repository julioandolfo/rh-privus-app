<?php
/**
 * Webhook Handler para Autentique
 * Recebe eventos do Autentique e atualiza status dos contratos
 * 
 * Estrutura real do payload Autentique:
 * {
 *   "id": "base64_webhook_id",
 *   "object": "webhook",
 *   "event": {
 *     "type": "signature.accepted|signature.viewed|signature.updated|signature.rejected",
 *     "data": {
 *       "public_id": "signer_uuid",
 *       "user": { "name", "email", "cpf" },
 *       "document": "document_hash",
 *       "signed": "datetime|null",
 *       "viewed": "datetime|null",
 *       "rejected": "datetime|null"
 *     }
 *   }
 * }
 */

require_once __DIR__ . '/../../includes/functions.php';

// Log de requisições
$log_file = __DIR__ . '/../../logs/webhook_autentique.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function log_webhook($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

log_webhook("=== REQUISIÇÃO RECEBIDA ===");
log_webhook("Método: " . $_SERVER['REQUEST_METHOD']);
log_webhook("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));

// Headers relevantes
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $header_name = str_replace('_', '-', substr($key, 5));
        $headers[$header_name] = $value;
    }
}
log_webhook("Headers: " . json_encode($headers, JSON_UNESCAPED_UNICODE));

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_webhook('Método não permitido: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Lê payload
$payload = file_get_contents('php://input');
log_webhook("Payload (primeiros 500 chars): " . substr($payload, 0, 500));

$data = json_decode($payload, true);

if (!$data) {
    log_webhook('JSON inválido: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

// Carrega serviço Autentique
require_once __DIR__ . '/../../includes/autentique_service.php';

// ============================================================
// EXTRAI DADOS DO PAYLOAD AUTENTIQUE
// ============================================================

// Tipo de evento: $data['event']['type']
$event = $data['event'] ?? [];
$evento_tipo = $event['type'] ?? 'unknown';
$event_data = $event['data'] ?? [];

// Document ID: $data['event']['data']['document']
$document_id = $event_data['document'] ?? null;

// Signer info
$signer_public_id = $event_data['public_id'] ?? null;
$signer_user = $event_data['user'] ?? [];
$signer_email = $signer_user['email'] ?? null;
$signer_name = $signer_user['name'] ?? null;
$signer_cpf = $signer_user['cpf'] ?? null;
$signed_at = $event_data['signed'] ?? null;
$viewed_at = $event_data['viewed'] ?? null;
$rejected_at = $event_data['rejected'] ?? null;

log_webhook("Evento: $evento_tipo");
log_webhook("Document ID: " . ($document_id ?? 'NULL'));
log_webhook("Signer: public_id=$signer_public_id email=$signer_email nome=$signer_name");
log_webhook("Signed: " . ($signed_at ?? 'NULL') . " | Viewed: " . ($viewed_at ?? 'NULL'));

// Validação do secret (via X-AUTENTIQUE-SIGNATURE header)
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    // O Autentique envia X-AUTENTIQUE-SIGNATURE como HMAC
    // Por enquanto apenas logamos - não bloqueia o processamento
    $autentique_signature = $headers['X-AUTENTIQUE-SIGNATURE'] ?? null;
    if ($autentique_signature) {
        log_webhook("X-Autentique-Signature recebida: " . substr($autentique_signature, 0, 20) . "...");
    } else {
        log_webhook("X-Autentique-Signature: não enviada");
    }
} catch (Exception $e) {
    log_webhook('Erro ao carregar config: ' . $e->getMessage());
}

// ============================================================
// PROCESSA O EVENTO
// ============================================================

if (!$document_id) {
    log_webhook("Document ID não encontrado no payload - ignorando");
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Document ID não encontrado']);
    exit;
}

try {
    $pdo = getDB();
    
    // Busca contrato pelo document_id do Autentique
    $stmt = $pdo->prepare("SELECT id, status, titulo FROM contratos WHERE autentique_document_id = ?");
    $stmt->execute([$document_id]);
    $contrato = $stmt->fetch();
    
    if (!$contrato) {
        log_webhook("Contrato NÃO ENCONTRADO para document_id: $document_id");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Contrato não encontrado']);
        exit;
    }
    
    $contrato_id = $contrato['id'];
    log_webhook("Contrato encontrado: ID=$contrato_id | Titulo={$contrato['titulo']} | Status={$contrato['status']}");
    
    // Registra evento no histórico
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contratos_eventos (contrato_id, tipo_evento, dados_json)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $contrato_id,
            $evento_tipo,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Exception $e) {
        log_webhook("Erro ao registrar evento: " . $e->getMessage());
    }
    
    // ============================================================
    // PROCESSA POR TIPO DE EVENTO
    // ============================================================
    
    // signature.accepted = signatário assinou
    if ($evento_tipo === 'signature.accepted' && $signed_at) {
        log_webhook("=== PROCESSANDO ASSINATURA ===");
        
        $updated = false;
        
        // 1. Tenta match por autentique_signer_id (public_id)
        if ($signer_public_id) {
            $stmt = $pdo->prepare("
                UPDATE contratos_signatarios 
                SET assinado = 1, data_assinatura = ?
                WHERE autentique_signer_id = ? AND contrato_id = ?
            ");
            $stmt->execute([$signed_at, $signer_public_id, $contrato_id]);
            $updated = $stmt->rowCount() > 0;
            log_webhook("Match por autentique_signer_id ($signer_public_id): " . ($updated ? 'SIM' : 'NÃO'));
        }
        
        // 2. Se não encontrou, tenta por email
        if (!$updated && $signer_email) {
            $stmt = $pdo->prepare("
                UPDATE contratos_signatarios 
                SET assinado = 1, data_assinatura = ?, autentique_signer_id = ?
                WHERE email = ? AND contrato_id = ? AND assinado = 0
                LIMIT 1
            ");
            $stmt->execute([$signed_at, $signer_public_id, $signer_email, $contrato_id]);
            $updated = $stmt->rowCount() > 0;
            log_webhook("Match por email ($signer_email): " . ($updated ? 'SIM' : 'NÃO'));
        }
        
        // 3. Se ainda não encontrou, loga todos os signatários para debug
        if (!$updated) {
            log_webhook("AVISO: Nenhum signatário atualizado!");
            $stmt = $pdo->prepare("SELECT id, tipo, nome, email, autentique_signer_id, assinado FROM contratos_signatarios WHERE contrato_id = ?");
            $stmt->execute([$contrato_id]);
            foreach ($stmt->fetchAll() as $s) {
                log_webhook("  Signatário local: tipo={$s['tipo']} email={$s['email']} autentique_id={$s['autentique_signer_id']} assinado={$s['assinado']}");
            }
        }
        
        // Verifica se TODOS assinaram
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, SUM(assinado) as assinados
            FROM contratos_signatarios 
            WHERE contrato_id = ?
        ");
        $stmt->execute([$contrato_id]);
        $stats = $stmt->fetch();
        
        log_webhook("Progresso: {$stats['assinados']}/{$stats['total']} assinaram");
        
        if ($stats && $stats['total'] > 0 && $stats['assinados'] == $stats['total']) {
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'assinado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            log_webhook(">>> Contrato $contrato_id TOTALMENTE ASSINADO!");
        } elseif ($stats['assinados'] > 0 && $contrato['status'] !== 'assinado') {
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'aguardando' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            log_webhook(">>> Contrato $contrato_id atualizado para AGUARDANDO");
        }
        
    // signature.viewed = signatário visualizou
    } elseif ($evento_tipo === 'signature.viewed') {
        log_webhook("Documento visualizado por: $signer_email ($signer_name)");
        
    // signature.updated = atualização de signatário    
    } elseif ($evento_tipo === 'signature.updated') {
        log_webhook("Signatário atualizado: $signer_email ($signer_name)");
        
        // Se tem assinatura, processa como assinatura
        if ($signed_at) {
            log_webhook("signature.updated com signed_at - processando como assinatura");
            
            $updated = false;
            if ($signer_public_id) {
                $stmt = $pdo->prepare("
                    UPDATE contratos_signatarios 
                    SET assinado = 1, data_assinatura = ?
                    WHERE autentique_signer_id = ? AND contrato_id = ?
                ");
                $stmt->execute([$signed_at, $signer_public_id, $contrato_id]);
                $updated = $stmt->rowCount() > 0;
            }
            if (!$updated && $signer_email) {
                $stmt = $pdo->prepare("
                    UPDATE contratos_signatarios 
                    SET assinado = 1, data_assinatura = ?, autentique_signer_id = ?
                    WHERE email = ? AND contrato_id = ? AND assinado = 0
                    LIMIT 1
                ");
                $stmt->execute([$signed_at, $signer_public_id, $signer_email, $contrato_id]);
                $updated = $stmt->rowCount() > 0;
            }
            
            log_webhook("Atualização: " . ($updated ? 'SUCESSO' : 'NÃO ENCONTRADO'));
            
            // Verifica se todos assinaram
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(assinado) as assinados FROM contratos_signatarios WHERE contrato_id = ?");
            $stmt->execute([$contrato_id]);
            $stats = $stmt->fetch();
            
            if ($stats && $stats['total'] > 0 && $stats['assinados'] == $stats['total']) {
                $stmt = $pdo->prepare("UPDATE contratos SET status = 'assinado' WHERE id = ?");
                $stmt->execute([$contrato_id]);
                log_webhook(">>> Contrato $contrato_id TOTALMENTE ASSINADO!");
            } elseif ($stats['assinados'] > 0) {
                $stmt = $pdo->prepare("UPDATE contratos SET status = 'aguardando' WHERE id = ?");
                $stmt->execute([$contrato_id]);
            }
        }
        
    // signature.rejected = signatário rejeitou
    } elseif ($evento_tipo === 'signature.rejected') {
        log_webhook("Assinatura REJEITADA por: $signer_email ($signer_name)");
        $reason = $event_data['reason'] ?? 'Sem motivo informado';
        log_webhook("Motivo: $reason");
        
    // document.* = eventos de documento
    } elseif (strpos($evento_tipo, 'document.') === 0) {
        log_webhook("Evento de documento: $evento_tipo");
        
        if ($evento_tipo === 'document.cancelled' || $evento_tipo === 'document.deleted') {
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'cancelado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            log_webhook(">>> Contrato $contrato_id CANCELADO");
        }
        
    } else {
        log_webhook("Evento não tratado: $evento_tipo");
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Evento processado']);
    log_webhook("=== FIM ===");
    
} catch (Exception $e) {
    log_webhook('ERRO: ' . $e->getMessage());
    log_webhook('Stack: ' . $e->getTraceAsString());
    http_response_code(200); // Sempre 200 para evitar retries
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
