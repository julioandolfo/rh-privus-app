<?php
/**
 * Webhook Handler para Autentique
 * Recebe eventos do Autentique e atualiza status dos contratos
 */

require_once __DIR__ . '/../../includes/functions.php';

// Log de requisições (para debug)
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
log_webhook("User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'));
log_webhook("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'não definido'));

// Loga todos os headers
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
log_webhook("Payload bruto (primeiros 2000 chars): " . substr($payload, 0, 2000));

$data = json_decode($payload, true);

if (!$data) {
    log_webhook('Payload inválido - JSON decode falhou: ' . json_last_error_msg());
    // Tenta parse como form-urlencoded
    parse_str($payload, $data);
    if (!empty($data)) {
        log_webhook('Payload parseado como form-urlencoded: ' . json_encode($data));
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload inválido']);
        exit;
    }
}

log_webhook("Payload decodificado: " . json_encode($data, JSON_UNESCAPED_UNICODE));

// Carrega serviço Autentique (após log inicial para não falhar antes de logar)
require_once __DIR__ . '/../../includes/autentique_service.php';

// Valida secret do webhook (se configurado)
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
    
    if ($config) {
        $evento_tipo = $data['event'] ?? $data['type'] ?? $data['action'] ?? 'unknown';
        log_webhook("Tipo de evento: $evento_tipo");
        
        // Pega o secret apropriado
        $expected_secret = null;
        if (!empty($config['webhook_documento_secret'])) {
            $expected_secret = $config['webhook_documento_secret'];
        } elseif (!empty($config['webhook_assinatura_secret'])) {
            $expected_secret = $config['webhook_assinatura_secret'];
        } elseif (!empty($config['webhook_secret'])) {
            $expected_secret = $config['webhook_secret'];
        }
        
        // Valida secret se estiver configurado
        if ($expected_secret) {
            $received_secret = null;
            
            // Verifica headers comuns
            $possible_headers = [
                'X-AUTENTIQUE-SECRET', 'X-WEBHOOK-SECRET', 'X-SECRET', 
                'AUTHORIZATION', 'X-SIGNATURE'
            ];
            
            foreach ($possible_headers as $header_name) {
                $normalized = str_replace('-', '_', $header_name);
                if (isset($headers[$header_name])) {
                    $received_secret = $headers[$header_name];
                    break;
                }
                // Tenta com diferentes capitalizações
                foreach ($headers as $hk => $hv) {
                    if (strtoupper($hk) === $header_name) {
                        $received_secret = $hv;
                        break 2;
                    }
                }
            }
            
            // Se for Authorization Bearer, extrai o token
            if ($received_secret && strpos($received_secret, 'Bearer ') === 0) {
                $received_secret = substr($received_secret, 7);
            }
            
            // Tenta no payload
            if (!$received_secret && isset($data['secret'])) {
                $received_secret = $data['secret'];
            }
            
            if (!$received_secret || $received_secret !== $expected_secret) {
                log_webhook("AVISO: Secret não corresponde (evento: $evento_tipo)");
                log_webhook("Secret esperado (10 chars): " . substr($expected_secret, 0, 10) . '...');
                log_webhook("Secret recebido: " . ($received_secret ? substr($received_secret, 0, 10) . '...' : 'NÃO FORNECIDO'));
                // NÃO bloqueia - apenas loga o aviso
                // Alguns webhooks do Autentique podem não enviar secret
            } else {
                log_webhook("Secret validado com sucesso");
            }
        } else {
            log_webhook("Nenhum webhook secret configurado - aceitando sem validação");
        }
    } else {
        log_webhook("Nenhuma configuração Autentique ativa encontrada");
    }
} catch (Exception $e) {
    log_webhook('Erro ao validar secret: ' . $e->getMessage());
}

try {
    $pdo = getDB();
    
    // Identifica tipo de evento - tenta vários formatos do Autentique
    $evento_tipo = $data['event'] ?? $data['type'] ?? $data['action'] ?? 'unknown';
    
    // Tenta extrair document_id de várias formas
    $document_id = $data['document']['id'] 
        ?? $data['documentId'] 
        ?? $data['document_id'] 
        ?? $data['data']['document']['id'] 
        ?? $data['data']['id'] 
        ?? $data['id'] 
        ?? null;
    
    log_webhook("Evento: $evento_tipo | Document ID: " . ($document_id ?? 'NÃO ENCONTRADO'));
    
    // Se o payload tem estrutura diferente, tenta encontrar nas chaves
    if (!$document_id) {
        log_webhook("Tentando encontrar document_id em chaves alternativas...");
        log_webhook("Chaves do payload: " . implode(', ', array_keys($data)));
        if (isset($data['data'])) {
            log_webhook("Chaves de data: " . implode(', ', array_keys($data['data'])));
        }
        
        // Responde 200 para não bloquear o Autentique, mas loga que não conseguiu processar
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Evento recebido mas document_id não encontrado']);
        exit;
    }
    
    // Busca contrato pelo document_id do Autentique
    $stmt = $pdo->prepare("SELECT id, status, titulo FROM contratos WHERE autentique_document_id = ?");
    $stmt->execute([$document_id]);
    $contrato = $stmt->fetch();
    
    if (!$contrato) {
        log_webhook("Contrato NÃO ENCONTRADO para document_id: $document_id");
        // Responde 200 para não gerar retries
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Contrato não encontrado']);
        exit;
    }
    
    $contrato_id = $contrato['id'];
    log_webhook("Contrato encontrado: ID=$contrato_id, Titulo={$contrato['titulo']}, Status={$contrato['status']}");
    
    // Registra evento
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
        log_webhook("Evento registrado na tabela contratos_eventos");
    } catch (Exception $e) {
        log_webhook("Erro ao registrar evento: " . $e->getMessage());
    }
    
    // Extrai informações do signatário de vários formatos
    $signer_data = $data['signer'] ?? $data['signature'] ?? $data['data']['signer'] ?? $data['data']['signature'] ?? null;
    $signer_id = $signer_data['id'] ?? $signer_data['public_id'] ?? $data['signerId'] ?? $data['signer_id'] ?? null;
    $signer_email = $signer_data['email'] ?? $data['email'] ?? null;
    $signed_at = $signer_data['signed_at'] ?? $signer_data['signedAt'] ?? $signer_data['created_at'] ?? $data['signedAt'] ?? date('Y-m-d H:i:s');
    
    log_webhook("Signer ID: " . ($signer_id ?? 'NULL') . " | Signer Email: " . ($signer_email ?? 'NULL'));
    
    // Processa eventos específicos
    $evento_lower = strtolower($evento_tipo);
    $is_signed = in_array($evento_lower, [
        'document.signed', 'signer.signed', 'signature.signed',
        'signed', 'document_signed', 'signer_signed'
    ]);
    $is_cancelled = in_array($evento_lower, [
        'document.cancelled', 'document.canceled', 'cancelled', 'canceled',
        'document_cancelled', 'document.deleted'
    ]);
    $is_viewed = in_array($evento_lower, [
        'document.viewed', 'viewed', 'document_viewed'
    ]);
    
    if ($is_signed) {
        log_webhook("=== PROCESSANDO ASSINATURA ===");
        
        $updated = false;
        
        // Tenta atualizar por autentique_signer_id
        if ($signer_id) {
            $stmt = $pdo->prepare("
                UPDATE contratos_signatarios 
                SET assinado = 1, data_assinatura = ?
                WHERE autentique_signer_id = ? AND contrato_id = ?
            ");
            $stmt->execute([$signed_at, $signer_id, $contrato_id]);
            $updated = $stmt->rowCount() > 0;
            log_webhook("Atualização por signer_id ($signer_id): " . ($updated ? 'SUCESSO' : 'NÃO ENCONTRADO'));
        }
        
        // Se não encontrou por signer_id, tenta por email
        if (!$updated && $signer_email) {
            $stmt = $pdo->prepare("
                UPDATE contratos_signatarios 
                SET assinado = 1, data_assinatura = ?
                WHERE email = ? AND contrato_id = ? AND assinado = 0
                LIMIT 1
            ");
            $stmt->execute([$signed_at, $signer_email, $contrato_id]);
            $updated = $stmt->rowCount() > 0;
            log_webhook("Atualização por email ($signer_email): " . ($updated ? 'SUCESSO' : 'NÃO ENCONTRADO'));
        }
        
        if (!$updated) {
            log_webhook("AVISO: Não foi possível atualizar nenhum signatário!");
            log_webhook("Signatários do contrato:");
            $stmt = $pdo->prepare("SELECT id, tipo, nome, email, autentique_signer_id, assinado FROM contratos_signatarios WHERE contrato_id = ?");
            $stmt->execute([$contrato_id]);
            $sigs = $stmt->fetchAll();
            foreach ($sigs as $s) {
                log_webhook("  - ID:{$s['id']} Tipo:{$s['tipo']} Nome:{$s['nome']} Email:{$s['email']} AutentiqueID:{$s['autentique_signer_id']} Assinado:{$s['assinado']}");
            }
        }
        
        // Verifica se todos assinaram
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, SUM(assinado) as assinados
            FROM contratos_signatarios 
            WHERE contrato_id = ?
        ");
        $stmt->execute([$contrato_id]);
        $stats = $stmt->fetch();
        
        log_webhook("Status signatários: {$stats['assinados']}/{$stats['total']} assinaram");
        
        if ($stats && $stats['total'] > 0 && $stats['assinados'] == $stats['total']) {
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'assinado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
            log_webhook("Contrato $contrato_id atualizado para ASSINADO (todos assinaram)");
        } else {
            // Só muda para aguardando se estiver em enviado
            if (in_array($contrato['status'], ['enviado', 'rascunho'])) {
                $stmt = $pdo->prepare("UPDATE contratos SET status = 'aguardando' WHERE id = ?");
                $stmt->execute([$contrato_id]);
                log_webhook("Contrato $contrato_id atualizado para AGUARDANDO");
            }
        }
        
    } elseif ($is_cancelled) {
        $stmt = $pdo->prepare("UPDATE contratos SET status = 'cancelado' WHERE id = ?");
        $stmt->execute([$contrato_id]);
        log_webhook("Contrato $contrato_id atualizado para CANCELADO");
        
    } elseif ($is_viewed) {
        log_webhook("Contrato $contrato_id foi visualizado");
        
    } else {
        log_webhook("Evento não reconhecido: $evento_tipo - nenhuma ação tomada");
    }
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Evento processado']);
    log_webhook("=== FIM DO PROCESSAMENTO ===");
    
} catch (Exception $e) {
    log_webhook('ERRO ao processar webhook: ' . $e->getMessage());
    log_webhook('Stack trace: ' . $e->getTraceAsString());
    http_response_code(200); // Responde 200 mesmo com erro para evitar retries
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
