<?php
/**
 * API - Processador da Fila WhatsApp via HTTP
 *
 * Permite acionar o processamento da fila evolution_fila_mensagens
 * diretamente pelo painel admin, sem depender do cron do sistema operacional.
 *
 * Requer autenticação de admin (sessão ativa).
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/evolution_service.php';

header('Content-Type: application/json; charset=utf-8');

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
$max_por_execucao = max(1, (int)($_GET['limite'] ?? 20)); // até 20 por acionamento manual
$intervalo_ms     = max(1000, (int)($config['intervalo_entre_mensagens'] ?? 7) * 1000); // em ms
$max_por_hora     = max(0, (int)($config['max_mensagens_por_hora'] ?? 80));

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
                // Pesquisa: usa sendList para compatibilidade
                $resultado = evolution_enviar_pesquisa_lista(
                    $msg['numero'],
                    $msg['colaborador_id'] ?? null,
                    $config
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
            usleep($intervalo_ms * 1000); // converte ms para microssegundos
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
