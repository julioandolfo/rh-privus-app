<?php
/**
 * Cron - Processador da Fila de Mensagens Slack
 *
 * Processa a tabela `slack_fila_mensagens` com controle de:
 *   - Intervalo entre disparos (padrão 2s — Slack é mais permissivo que WA)
 *   - Limite de mensagens por hora (padrão 300)
 *   - Retry automático em caso de falha (máx 3 tentativas)
 *   - Suporte a DMs (User ID) e canais (#geral, C...)
 *
 * Cron recomendado: a cada 1 minuto
 *   * * * * * /usr/bin/php /caminho/rh-privus/cron/processar_fila_slack.php >> /var/log/fila_slack.log 2>&1
 *
 * Rate limits da Slack Web API (chat.postMessage):
 *   Tier 3 = 50+ requests/minuto por método
 *   Com 2s de intervalo: ~30 msgs/min por execução — bem dentro do limite
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/slack_service.php';

date_default_timezone_set('America/Sao_Paulo');

$agora = date('Y-m-d H:i:s');
$inicio = microtime(true);

echo "[{$agora}] Iniciando processador da fila Slack\n";

// ─── Verificações iniciais ─────────────────────────────────────────────────────
$config = slack_get_config();

if (!$config) {
    echo "⚠️  Slack não configurado. Encerrando.\n";
    exit(0);
}

// Verifica conexão (auth.test)
$conexao = slack_verificar_conexao($config);
if (empty($conexao['ok'])) {
    echo "⚠️  Falha na autenticação Slack: " . ($conexao['error'] ?? 'erro desconhecido') . ". Fila pausada.\n";
    exit(0);
}
echo "✅ Conectado ao workspace: " . ($conexao['team'] ?? '?') . " (bot: " . ($conexao['user'] ?? '?') . ")\n";

// ─── Parâmetros de rate limiting ──────────────────────────────────────────────
$intervalo_segundos = max(1, (int)($config['intervalo_entre_mensagens'] ?? 2));
$jitter_max         = max(1, (int)ceil($intervalo_segundos * 0.5));
$max_por_hora       = max(0, (int)($config['max_mensagens_por_hora'] ?? 300));
$tempo_max_execucao = 55; // segundos (deixa 5s para o próximo cron)

echo "⚙️  Intervalo: {$intervalo_segundos}s ±{$jitter_max}s | Limite/hora: " . ($max_por_hora ?: 'ilimitado') . "\n";

// ─── Verifica limite por hora ──────────────────────────────────────────────────
try {
    $pdo = getDB();

    $restante_hora = PHP_INT_MAX;
    if ($max_por_hora > 0) {
        $enviadas_ultima_hora = (int)$pdo->query("
            SELECT COUNT(*) FROM slack_fila_mensagens
            WHERE status = 'enviado' AND enviado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ")->fetchColumn();

        if ($enviadas_ultima_hora >= $max_por_hora) {
            echo "🛑 Limite de {$max_por_hora} mensagens/hora atingido ({$enviadas_ultima_hora} enviadas). Aguardando.\n";
            exit(0);
        }

        $restante_hora = $max_por_hora - $enviadas_ultima_hora;
        echo "📊 Enviadas na última hora: {$enviadas_ultima_hora}/{$max_por_hora} (restam {$restante_hora} slots)\n";
    }

} catch (Exception $e) {
    echo "❌ Erro ao verificar limite: " . $e->getMessage() . "\n";
    exit(1);
}

// ─── Busca mensagens pendentes ────────────────────────────────────────────────
try {
    $max_esta_execucao = min(
        (int)floor($tempo_max_execucao / max(1, $intervalo_segundos)),
        $restante_hora
    );

    $stmt = $pdo->prepare("
        SELECT id, colaborador_id, canal_destino, titulo, mensagem, url, tipo, tentativas
        FROM slack_fila_mensagens
        WHERE status = 'pendente'
          AND tentativas < 3
          AND (agendado_para IS NULL OR agendado_para <= NOW())
        ORDER BY agendado_para ASC, id ASC
        LIMIT ?
    ");
    $stmt->execute([$max_esta_execucao]);
    $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "❌ Erro ao buscar fila: " . $e->getMessage() . "\n";
    exit(1);
}

$total    = count($fila);
$enviados = 0;
$erros    = 0;

echo "📋 Mensagens pendentes nesta execução: {$total} (máx: {$max_esta_execucao})\n\n";

if ($total === 0) {
    echo "✅ Fila vazia.\n";
    exit(0);
}

// ─── Processa cada mensagem ───────────────────────────────────────────────────
foreach ($fila as $item) {
    $elapsed = microtime(true) - $inicio;
    if ($elapsed >= $tempo_max_execucao) {
        echo "⏱️  Tempo máximo atingido. Restantes serão processados no próximo ciclo.\n";
        break;
    }

    $id             = (int)$item['id'];
    $colaborador_id = $item['colaborador_id'] ? (int)$item['colaborador_id'] : null;
    $canal          = $item['canal_destino'];
    $tipo           = $item['tipo'];
    $display        = $colaborador_id ? "collab#{$colaborador_id}" : $canal;

    echo "  [{$id}] {$tipo} → {$display}... ";

    // Marca como "enviando" para evitar duplicação em execuções paralelas
    $updated = $pdo->prepare("
        UPDATE slack_fila_mensagens SET status = 'enviando', tentativas = tentativas + 1
        WHERE id = ? AND status = 'pendente'
    ")->execute([$id]);

    if (!$updated) {
        echo "⚠️  Skip (já em processamento)\n";
        continue;
    }

    // ── Envia ─────────────────────────────────────────────────────────────────
    try {
        $result = slack_enviar_mensagem(
            $canal,
            $item['titulo'],
            $item['mensagem'],
            $item['url'] ?? '',
            $colaborador_id,
            $tipo,
            $config
        );
    } catch (Exception $e) {
        $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    // ── Atualiza status ───────────────────────────────────────────────────────
    if (!empty($result['ok'])) {
        $pdo->prepare("
            UPDATE slack_fila_mensagens SET status = 'enviado', enviado_em = NOW(), erro_detalhe = NULL
            WHERE id = ?
        ")->execute([$id]);

        echo "✅ Enviado (ts: " . ($result['ts'] ?? '-') . ")\n";
        $enviados++;
    } else {
        $tentativas_atuais = (int)$item['tentativas'] + 1;
        $novo_status = $tentativas_atuais >= 3 ? 'erro' : 'pendente';
        $erro_msg    = $result['error'] ?? 'erro desconhecido';

        $pdo->prepare("
            UPDATE slack_fila_mensagens SET status = ?, erro_detalhe = ? WHERE id = ?
        ")->execute([$novo_status, substr($erro_msg, 0, 500), $id]);

        echo "❌ Erro (tentativa {$tentativas_atuais}/3): {$erro_msg}\n";
        $erros++;
    }

    // ── Pausa entre envios ────────────────────────────────────────────────────
    if ($enviados + $erros < $total) {
        $jitter = rand(0, $jitter_max * 1000000);
        usleep(($intervalo_segundos * 1000000) + $jitter);
    }
}

// ─── Resumo ───────────────────────────────────────────────────────────────────
$duracao = round(microtime(true) - $inicio, 1);
echo "\n=== RESUMO ===\n";
echo "Processados:  " . ($enviados + $erros) . "/{$total}\n";
echo "Enviados:     {$enviados}\n";
echo "Erros:        {$erros}\n";
echo "Duração:      {$duracao}s\n";
echo "Finalizado:   " . date('Y-m-d H:i:s') . "\n";
