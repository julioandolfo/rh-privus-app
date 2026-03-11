<?php
/**
 * Cron - Processador da Fila de Mensagens WhatsApp
 *
 * Processa a tabela `evolution_fila_mensagens` com controle de:
 *   - Intervalo entre disparos (configurável no painel, padrão 7s + jitter)
 *   - Limite de mensagens por hora (padrão 80)
 *   - Retry automático em caso de falha (máx 3 tentativas)
 *   - Respeita agendamento (agendado_para)
 *
 * Cron recomendado: rodar a cada 1 minuto
 *   * * * * * /usr/bin/php /caminho/rh-privus/cron/processar_fila_whatsapp.php >> /var/log/fila_whatsapp.log 2>&1
 *
 * A cada execução (1 minuto), com intervalo de 7s por mensagem:
 *   60s ÷ 7s ≈ 8 mensagens por execução × 60 execuções/hora = ~480 disponíveis/hora
 *   O limite por hora (max_mensagens_por_hora) controla o teto real.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evolution_service.php';

date_default_timezone_set('America/Sao_Paulo');

$agora  = date('Y-m-d H:i:s');
$inicio = microtime(true);

// Arquivo de status persistente lido pelo painel admin
define('STATUS_FILE', __DIR__ . '/../storage/cron/fila_whatsapp_status.json');

/**
 * Grava o status atual da execução no arquivo JSON.
 * O painel de configurações lê este arquivo para exibir o histórico do cron.
 */
function salvar_status_cron(array $dados): void {
    $existente = [];
    if (file_exists(STATUS_FILE)) {
        $json = @file_get_contents(STATUS_FILE);
        if ($json) $existente = json_decode($json, true) ?? [];
    }

    // Mantém as últimas 20 execuções
    $historico = $existente['historico'] ?? [];
    array_unshift($historico, $dados);
    $historico = array_slice($historico, 0, 20);

    $payload = [
        'ultima_execucao'    => $dados['iniciado_em'],
        'ultimo_status'      => $dados['status'],
        'ultimo_enviados'    => $dados['enviados'] ?? 0,
        'ultimo_erros'       => $dados['erros']    ?? 0,
        'total_execucoes'    => ($existente['total_execucoes'] ?? 0) + 1,
        'historico'          => $historico,
    ];

    @file_put_contents(STATUS_FILE, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "[{$agora}] Iniciando processador da fila WhatsApp\n";

// ─── Verificações iniciais ─────────────────────────────────────────────────────
$config = evolution_get_config();

if (!$config) {
    echo "⚠️  Evolution API não configurada. Encerrando.\n";
    salvar_status_cron(['iniciado_em' => $agora, 'status' => 'sem_config', 'enviados' => 0, 'erros' => 0, 'duracao_s' => 0, 'pendentes' => 0, 'detalhe' => 'Evolution API não configurada']);
    exit(0);
}

$conexao = evolution_verificar_conexao($config);
if (!$conexao['connected']) {
    $estado = $conexao['state'] ?? 'desconhecido';
    echo "⚠️  WhatsApp desconectado (estado: {$estado}). Fila pausada.\n";
    salvar_status_cron(['iniciado_em' => $agora, 'status' => 'desconectado', 'enviados' => 0, 'erros' => 0, 'duracao_s' => 0, 'pendentes' => 0, 'detalhe' => "WA desconectado: {$estado}"]);
    exit(0);
}

// ─── Parâmetros de rate limiting ──────────────────────────────────────────────
$intervalo_segundos  = max(3, (int)($config['intervalo_entre_mensagens'] ?? 7)); // mínimo 3s
$jitter_max          = (int)ceil($intervalo_segundos * 0.4); // até 40% de variação aleatória
$max_por_hora        = max(0, (int)($config['max_mensagens_por_hora'] ?? 80));

// Tempo máximo de processamento por execução (deixa 10s de folga para o próximo cron)
$tempo_max_execucao  = 50; // segundos

echo "⚙️  Intervalo: {$intervalo_segundos}s ±{$jitter_max}s | Limite/hora: " . ($max_por_hora ?: 'ilimitado') . "\n";

// ─── Verifica limite por hora ──────────────────────────────────────────────────
try {
    $pdo = getDB();

    if ($max_por_hora > 0) {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM evolution_fila_mensagens
            WHERE status = 'enviado'
              AND enviado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $enviadas_ultima_hora = (int)$stmt->fetchColumn();

        if ($enviadas_ultima_hora >= $max_por_hora) {
            echo "🛑 Limite de {$max_por_hora} mensagens/hora atingido ({$enviadas_ultima_hora} enviadas). Aguardando próxima hora.\n";
            salvar_status_cron(['iniciado_em' => $agora, 'status' => 'limite_hora', 'enviados' => 0, 'erros' => 0, 'duracao_s' => 0, 'pendentes' => 0, 'detalhe' => "Limite horário: {$enviadas_ultima_hora}/{$max_por_hora}"]);
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
    // Quantidade máxima a tentar nesta execução
    // Baseado no tempo disponível e intervalo entre envios
    $max_esta_execucao = min(
        (int)floor($tempo_max_execucao / max(1, $intervalo_segundos)),
        $max_por_hora > 0 ? $restante_hora : 999
    );

    $stmt = $pdo->prepare("
        SELECT id, colaborador_id, numero, titulo, mensagem, url, tipo, tentativas
        FROM evolution_fila_mensagens
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
    echo "✅ Fila vazia. Nada a enviar.\n";
    salvar_status_cron(['iniciado_em' => $agora, 'status' => 'fila_vazia', 'enviados' => 0, 'erros' => 0, 'duracao_s' => round(microtime(true) - $inicio, 1), 'pendentes' => 0, 'detalhe' => 'Fila sem mensagens pendentes']);
    exit(0);
}

// ─── Processa cada mensagem ───────────────────────────────────────────────────
foreach ($fila as $item) {
    $elapsed = microtime(true) - $inicio;
    if ($elapsed >= $tempo_max_execucao) {
        echo "⏱️  Tempo máximo de execução atingido. Mensagens restantes serão processadas no próximo ciclo.\n";
        break;
    }

    $id             = (int)$item['id'];
    $colaborador_id = $item['colaborador_id'] ? (int)$item['colaborador_id'] : null;
    $tipo           = $item['tipo'];
    $nome_display   = $colaborador_id ? "collab#{$colaborador_id}" : $item['numero'];

    echo "  [{$id}] {$tipo} → {$nome_display} ({$item['numero']})... ";

    // Marca como "enviando" para evitar processamento duplo em execuções paralelas
    try {
        $pdo->prepare("UPDATE evolution_fila_mensagens SET status = 'enviando', tentativas = tentativas + 1 WHERE id = ? AND status = 'pendente'")
            ->execute([$id]);
    } catch (Exception $e) {
        echo "⚠️  Skip (já processando)\n";
        continue;
    }

    // ── Envia de acordo com o tipo ─────────────────────────────────────────────
    $result = ['success' => false, 'error' => 'tipo desconhecido'];

    try {
        if ($tipo === 'pesquisa_humor') {
            // Pesquisa de humor: usa sendList com fallback em texto
            $result = evolution_enviar_pesquisa_lista($item['numero'], $item['mensagem'], $colaborador_id);

            // Atualiza o controle de envios da pesquisa
            if ($result['success'] && $colaborador_id) {
                $pdo->prepare("
                    UPDATE humor_pesquisa_envios SET enviado = 1
                    WHERE colaborador_id = ? AND data_envio = CURDATE()
                ")->execute([$colaborador_id]);
            }
        } else {
            // Notificação padrão (texto livre)
            $result = evolution_enviar_texto($item['numero'], $item['mensagem'], $colaborador_id, $tipo);
        }
    } catch (Exception $e) {
        $result = ['success' => false, 'error' => $e->getMessage()];
    }

    // ── Atualiza status na fila ────────────────────────────────────────────────
    if ($result['success']) {
        $pdo->prepare("
            UPDATE evolution_fila_mensagens
            SET status = 'enviado', enviado_em = NOW(), erro_detalhe = NULL
            WHERE id = ?
        ")->execute([$id]);

        echo "✅ Enviado\n";
        $enviados++;
    } else {
        $tentativas_atuais = (int)$item['tentativas'] + 1;
        $novo_status = $tentativas_atuais >= 3 ? 'erro' : 'pendente';
        $erro_msg = $result['error'] ?? ($result['raw'] ?? 'erro desconhecido');

        $pdo->prepare("
            UPDATE evolution_fila_mensagens
            SET status = ?, erro_detalhe = ?
            WHERE id = ?
        ")->execute([$novo_status, substr($erro_msg, 0, 500), $id]);

        echo "❌ Erro (tentativa {$tentativas_atuais}/3): " . substr($erro_msg, 0, 80) . "\n";
        $erros++;
    }

    // ── Pausa controlada entre envios ─────────────────────────────────────────
    // Intervalo base + jitter aleatório para parecer mais humano
    if ($enviados + $erros < $total) {
        $jitter  = rand(0, $jitter_max * 1000000); // em microssegundos
        $pausa   = ($intervalo_segundos * 1000000) + $jitter;
        usleep($pausa);
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

// Conta pendentes restantes para o status
try {
    $pendentes_restantes = (int)$pdo->query("SELECT COUNT(*) FROM evolution_fila_mensagens WHERE status = 'pendente' AND tentativas < 3")->fetchColumn();
} catch (Exception $e) {
    $pendentes_restantes = 0;
}

salvar_status_cron([
    'iniciado_em'  => $agora,
    'finalizado_em'=> date('Y-m-d H:i:s'),
    'status'       => $erros === 0 ? 'ok' : ($enviados > 0 ? 'parcial' : 'erro'),
    'enviados'     => $enviados,
    'erros'        => $erros,
    'processados'  => $enviados + $erros,
    'total_fila'   => $total,
    'pendentes'    => $pendentes_restantes,
    'duracao_s'    => $duracao,
    'detalhe'      => "Enviados: {$enviados} | Erros: {$erros} | Duração: {$duracao}s",
]);
