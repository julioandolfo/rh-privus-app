<?php
/**
 * API - Processador da Fila WhatsApp via HTTP
 *
 * Permite acionar o processamento da fila evolution_fila_mensagens
 * diretamente pelo painel admin, sem depender do cron do sistema operacional.
 *
 * Requer autenticação de admin (sessão ativa).
 */

header('Content-Type: application/json; charset=utf-8');

// Garante que erros fatais retornem JSON em vez de HTML/vazio
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => $error['message'] . ' em ' . basename($error['file']) . ':' . $error['line'],
        ]);
    }
});

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/evolution_service.php';

// ─── Autenticação ────────────────────────────────────────────────────────────
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['usuario']['role'] ?? '', ['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

// ─── Configuração e conexão ───────────────────────────────────────────────────
$config = evolution_get_config();
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Evolution API não configurada ou inativa']);
    exit;
}

$conexao = evolution_verificar_conexao($config);
if (!$conexao['connected']) {
    echo json_encode([
        'success' => false,
        'error'   => 'WhatsApp desconectado (estado: ' . ($conexao['state'] ?? 'desconhecido') . '). Conecte primeiro.',
    ]);
    exit;
}

// ─── Rate limiting ────────────────────────────────────────────────────────────
$max_por_execucao    = max(1, (int)($_GET['limite'] ?? 20));
$intervalo_segundos  = max(2, (int)($config['intervalo_entre_mensagens'] ?? 7));
$max_por_hora        = max(0, (int)($config['max_mensagens_por_hora'] ?? 80));

// Limita para não estourar timeout HTTP (~60s). Se intervalo é 30s, max 1 por vez.
$max_no_timeout = max(1, (int)floor(55 / $intervalo_segundos));
$max_por_execucao = min($max_por_execucao, $max_no_timeout);

// ─── Verifica limite por hora ─────────────────────────────────────────────────
try {
    $pdo = getDB();

    if ($max_por_hora > 0) {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM evolution_fila_mensagens
            WHERE status = 'enviado'
              AND enviado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $enviados_hora = (int)$stmt->fetchColumn();

        if ($enviados_hora >= $max_por_hora) {
            echo json_encode([
                'success'       => false,
                'error'         => "Limite por hora atingido ({$enviados_hora}/{$max_por_hora}). Aguarde.",
                'enviados_hora' => $enviados_hora,
                'limite_hora'   => $max_por_hora,
            ]);
            exit;
        }

        $max_por_execucao = min($max_por_execucao, $max_por_hora - $enviados_hora);
    }

    // ─── Busca mensagens pendentes ────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT * FROM evolution_fila_mensagens
        WHERE status = 'pendente'
          AND tentativas < 3
          AND (agendado_para IS NULL OR agendado_para <= NOW())
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$max_por_execucao]);
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($mensagens)) {
        echo json_encode([
            'success'   => true,
            'message'   => 'Nenhuma mensagem pendente na fila',
            'enviados'  => 0,
            'erros'     => 0,
            'pendentes' => 0,
        ]);
        exit;
    }

    // ─── Conta total pendente para retorno ────────────────────────────────────
    $total_pendente = (int)$pdo->query("
        SELECT COUNT(*) FROM evolution_fila_mensagens
        WHERE status = 'pendente' AND tentativas < 3
          AND (agendado_para IS NULL OR agendado_para <= NOW())
    ")->fetchColumn();

    // ─── Processa mensagens ───────────────────────────────────────────────────
    $enviados = 0;
    $erros    = 0;

    foreach ($mensagens as $msg) {
        // Marca como "enviando" para evitar processamento duplo
        $pdo->prepare("UPDATE evolution_fila_mensagens SET status = 'enviando', tentativas = tentativas + 1 WHERE id = ?")
            ->execute([$msg['id']]);

        try {
            if ($msg['tipo'] === 'pesquisa_humor') {
                $resultado = evolution_enviar_pesquisa_lista(
                    $msg['numero'],
                    $msg['mensagem'],
                    $msg['colaborador_id'] ? (int)$msg['colaborador_id'] : null
                );
            } else {
                $resultado = evolution_enviar_texto(
                    $msg['numero'],
                    $msg['mensagem'],
                    $msg['colaborador_id'] ?? null,
                    $msg['tipo'] ?? 'notificacao'
                );
            }

            if (!empty($resultado['success'])) {
                $pdo->prepare("UPDATE evolution_fila_mensagens SET status = 'enviado', enviado_em = NOW() WHERE id = ?")
                    ->execute([$msg['id']]);
                $enviados++;
            } else {
                $erro = $resultado['error'] ?? json_encode($resultado['data'] ?? 'Falha');
                $pdo->prepare("
                    UPDATE evolution_fila_mensagens
                    SET status = IF(tentativas >= 3, 'erro', 'pendente'),
                        erro_detalhe = ?
                    WHERE id = ?
                ")->execute([mb_substr($erro, 0, 500), $msg['id']]);
                $erros++;
                error_log("[WA Fila Web] Erro msg id={$msg['id']}: {$erro}");
            }
        } catch (Exception $e) {
            $pdo->prepare("
                UPDATE evolution_fila_mensagens
                SET status = IF(tentativas >= 3, 'erro', 'pendente'),
                    erro_detalhe = ?
                WHERE id = ?
            ")->execute([mb_substr($e->getMessage(), 0, 500), $msg['id']]);
            $erros++;
            error_log("[WA Fila Web] Exceção msg id={$msg['id']}: " . $e->getMessage());
        }

        // Intervalo entre mensagens (somente se houver mais para enviar)
        if ($enviados + $erros < count($mensagens)) {
            sleep($intervalo_segundos);
        }
    }

    // ─── Contagem final pendente ──────────────────────────────────────────────
    $ainda_pendente = (int)$pdo->query("
        SELECT COUNT(*) FROM evolution_fila_mensagens
        WHERE status = 'pendente' AND tentativas < 3
    ")->fetchColumn();

    echo json_encode([
        'success'        => true,
        'message'        => "Processamento concluído: {$enviados} enviada(s), {$erros} erro(s).",
        'enviados'       => $enviados,
        'erros'          => $erros,
        'ainda_pendente' => $ainda_pendente,
    ]);

} catch (Exception $e) {
    error_log('[WA Fila Web] Erro geral: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
