<?php
/**
 * Webhook - Evolution API (WhatsApp)
 * 
 * Recebe eventos da Evolution API quando colaboradores respondem mensagens.
 * Processa respostas da pesquisa de humor e registra no banco de dados.
 * 
 * Configure na Evolution API:
 *   URL: https://seudominio.com.br/api/evolution/webhook.php
 *   Eventos: MESSAGES_UPSERT
 */

header('Content-Type: application/json');

// Evita saída de erros PHP no response
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/evolution_service.php';

// ─── Validação do request ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload_raw = file_get_contents('php://input');
$payload     = json_decode($payload_raw, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// ─── Log bruto do webhook ────────────────────────────────────────────────────
$evento    = $payload['event'] ?? ($payload['type'] ?? 'unknown');
$instancia = $payload['instance'] ?? ($payload['instanceName'] ?? null);
$pdo       = getDB();

try {
    $stmt = $pdo->prepare("
        INSERT INTO evolution_webhooks_log (evento, instancia, payload_raw, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$evento, $instancia, $payload_raw]);
    $webhook_log_id = $pdo->lastInsertId();
} catch (Exception $e) {
    error_log('[EvolutionWebhook] Erro ao salvar log: ' . $e->getMessage());
    $webhook_log_id = null;
}

// ─── Processa apenas mensagens recebidas ─────────────────────────────────────
$eventos_mensagem = ['messages.upsert', 'MESSAGES_UPSERT', 'message'];
if (!in_array($evento, $eventos_mensagem)) {
    echo json_encode(['status' => 'ignored', 'event' => $evento]);
    exit;
}

// ─── Extrai dados da mensagem ─────────────────────────────────────────────────
$messages = $payload['data'] ?? $payload['messages'] ?? [];
if (isset($payload['data']['key'])) {
    // Formato single message
    $messages = [$payload['data']];
}

foreach ($messages as $msg) {
    processar_mensagem($msg, $pdo, $webhook_log_id);
}

echo json_encode(['status' => 'ok', 'processed' => count($messages)]);

// ─── Função de processamento de mensagem ─────────────────────────────────────
function processar_mensagem(array $msg, PDO $pdo, ?int $webhook_log_id): void {
    try {
        // Ignora mensagens enviadas pelo próprio bot (fromMe = true)
        $from_me = $msg['key']['fromMe'] ?? false;
        if ($from_me) {
            return;
        }

        // Extrai número do remetente
        $remote_jid = $msg['key']['remoteJid'] ?? '';
        // Formato: 5511999999999@s.whatsapp.net ou grupo@g.us
        if (str_contains($remote_jid, '@g.us')) {
            // Ignora mensagens de grupos
            return;
        }

        $numero_raw = preg_replace('/@.*$/', '', $remote_jid);
        $numero     = preg_replace('/\D/', '', $numero_raw);

        if (empty($numero)) {
            return;
        }

        // Extrai texto da mensagem
        $texto = extrair_texto_mensagem($msg);

        if (empty($texto)) {
            return;
        }

        // Busca colaborador pelo número
        $colaborador = evolution_buscar_colaborador_por_numero($numero);

        // Atualiza log do webhook
        if ($webhook_log_id) {
            $stmt = $pdo->prepare("
                UPDATE evolution_webhooks_log
                SET numero_remetente = ?, mensagem_recebida = ?, colaborador_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$numero, $texto, $colaborador['id'] ?? null, $webhook_log_id]);
        }

        if (!$colaborador) {
            // Número não cadastrado - ignora silenciosamente
            return;
        }

        $colaborador_id = (int)$colaborador['id'];

        // ── Determina contexto da resposta ─────────────────────────────────
        // Verifica se há pesquisa enviada hoje e colaborador ainda não respondeu na tabela emocoes
        $hoje = date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT id FROM humor_pesquisa_envios
            WHERE colaborador_id = ? AND data_envio = ? AND enviado = 1 AND respondido = 0
        ");
        $stmt->execute([$colaborador_id, $hoje]);
        $pesquisa_pendente = $stmt->fetch();

        // Confirma que não registrou ainda em emocoes hoje
        if ($pesquisa_pendente) {
            $stmt = $pdo->prepare("
                SELECT e.id FROM emocoes e
                LEFT JOIN usuarios u ON u.id = e.usuario_id
                WHERE (e.colaborador_id = ? OR u.colaborador_id = ?) AND e.data_registro = ?
                LIMIT 1
            ");
            $stmt->execute([$colaborador_id, $colaborador_id, $hoje]);
            if ($stmt->fetch()) {
                $pesquisa_pendente = null; // já respondeu via outro canal
            }
        }

        if ($pesquisa_pendente) {
            $processado = evolution_processar_resposta_humor($colaborador_id, $texto);
            $acao = $processado ? 'humor_registrado' : 'humor_resposta_invalida';

            if ($webhook_log_id) {
                $stmt = $pdo->prepare("UPDATE evolution_webhooks_log SET processado = 1, acao_tomada = ? WHERE id = ?");
                $stmt->execute([$acao, $webhook_log_id]);
            }
            return;
        }

        // ── Comandos de texto livre ────────────────────────────────────────
        $texto_lower = mb_strtolower(trim($texto));

        // Comando: colaborador digita "humor" para receber a pesquisa manualmente
        if (in_array($texto_lower, ['humor', 'como me sinto', 'pesquisa', '/humor'])) {
            evolution_enviar_pesquisa_humor($colaborador_id);
            $acao = 'pesquisa_humor_solicitada';

            if ($webhook_log_id) {
                $stmt = $pdo->prepare("UPDATE evolution_webhooks_log SET processado = 1, acao_tomada = ? WHERE id = ?");
                $stmt->execute([$acao, $webhook_log_id]);
            }
            return;
        }

        // Sem contexto reconhecido — não faz nada (evita resposta automática indevida)
        if ($webhook_log_id) {
            $stmt = $pdo->prepare("UPDATE evolution_webhooks_log SET processado = 1, acao_tomada = 'sem_contexto' WHERE id = ?");
            $stmt->execute([$webhook_log_id]);
        }

    } catch (Exception $e) {
        error_log('[EvolutionWebhook] Erro ao processar mensagem: ' . $e->getMessage());
    }
}

/**
 * Extrai o texto de uma mensagem (suporta diferentes formatos da Evolution API)
 */
function extrair_texto_mensagem(array $msg): string {
    // Resposta de botão
    $button_reply = $msg['message']['buttonsResponseMessage']['selectedButtonId']
        ?? $msg['message']['interactiveResponseMessage']['nativeFlowResponseMessage']['paramsJson']
        ?? null;

    if ($button_reply) {
        // Pode vir como JSON: {"id":"5","title":"Ótimo 😄"}
        $decoded = json_decode($button_reply, true);
        return $decoded['id'] ?? $button_reply;
    }

    // Lista de resposta (list message)
    $list_reply = $msg['message']['listResponseMessage']['singleSelectReply']['selectedRowId'] ?? null;
    if ($list_reply) {
        return $list_reply;
    }

    // Texto simples
    $texto = $msg['message']['conversation']
        ?? $msg['message']['extendedTextMessage']['text']
        ?? $msg['message']['ephemeralMessage']['message']['extendedTextMessage']['text']
        ?? '';

    return trim($texto);
}
