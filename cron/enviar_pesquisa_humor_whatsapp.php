<?php
/**
 * Cron - Envio da Pesquisa de Humor Diário via WhatsApp
 * 
 * Deve ser executado DIARIAMENTE, de preferência a cada hora para respeitar
 * o horário configurado no painel. O script verifica internamente se está
 * no horário correto antes de disparar.
 * 
 * Cron recomendado (a cada hora, das 7h às 12h):
 *   0 7-12 * * * /usr/bin/php /caminho/para/rh-privus/cron/enviar_pesquisa_humor_whatsapp.php >> /var/log/humor_whatsapp.log 2>&1
 * 
 * Ou executar exatamente no horário configurado (ex: 9h):
 *   0 9 * * 1-5 /usr/bin/php /caminho/para/rh-privus/cron/enviar_pesquisa_humor_whatsapp.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evolution_service.php';

date_default_timezone_set('America/Sao_Paulo');

$now   = new DateTime();
$hoje  = $now->format('Y-m-d');
$hora_atual = (int)$now->format('H');
$min_atual  = (int)$now->format('i');
$dia_semana = (int)$now->format('w'); // 0=Dom, 6=Sáb

echo "=== PESQUISA DE HUMOR WHATSAPP ===\n";
echo "Data/Hora: " . $now->format('Y-m-d H:i:s') . "\n\n";

// ─── Verifica configuração ────────────────────────────────────────────────────
$config = evolution_get_config();

if (!$config) {
    echo "❌ Evolution API não configurada ou inativa. Encerrando.\n";
    exit(0);
}

if (!$config['pesquisa_humor_ativa']) {
    echo "ℹ️  Pesquisa de humor desativada nas configurações. Encerrando.\n";
    exit(0);
}

// ─── Verifica dia da semana ───────────────────────────────────────────────────
$dias_ativos = array_map('intval', explode(',', $config['dias_pesquisa_humor'] ?? '1,2,3,4,5'));
if (!in_array($dia_semana, $dias_ativos)) {
    echo "ℹ️  Hoje ({$dia_semana}) não é dia de pesquisa. Dias ativos: " . implode(',', $dias_ativos) . ". Encerrando.\n";
    exit(0);
}

// ─── Verifica se já passou do horário mínimo configurado ─────────────────────
// Envia a partir do horário configurado em diante (sem janela rígida).
// A proteção contra duplicatas é feita pela query que exclui quem já recebeu hoje.
$horario_config = substr($config['horario_pesquisa_humor'] ?? '09:00:00', 0, 5);
[$hora_conf, $min_conf] = array_map('intval', explode(':', $horario_config));

$minutos_agora  = $hora_atual * 60 + $min_atual;
$minutos_config = $hora_conf * 60 + $min_conf;

if ($minutos_agora < $minutos_config) {
    echo "ℹ️  Ainda não chegou o horário de envio. Configurado: {$horario_config}, Agora: " . $now->format('H:i') . ". Encerrando.\n";
    exit(0);
}

echo "✅ Horário OK (a partir de {$horario_config}, agora: " . $now->format('H:i') . ")\n\n";

// ─── Busca colaboradores ativos com telefone ─────────────────────────────────
try {
    $pdo = getDB();

    $colaboradores = $pdo->query("
        SELECT c.id, c.nome_completo, c.telefone, 'colaborador' AS tipo
        FROM colaboradores c
        WHERE c.status = 'ativo'
          AND c.whatsapp_ativo = 1
          AND c.telefone IS NOT NULL
          AND c.telefone != ''
          AND c.id NOT IN (
              SELECT colaborador_id FROM humor_pesquisa_envios WHERE data_envio = CURDATE()
          )
          AND c.id NOT IN (
              SELECT e.colaborador_id FROM emocoes e WHERE e.data_registro = CURDATE() AND e.colaborador_id IS NOT NULL
          )
          AND c.id NOT IN (
              SELECT u.colaborador_id FROM emocoes e
              INNER JOIN usuarios u ON u.id = e.usuario_id
              WHERE e.data_registro = CURDATE() AND u.colaborador_id IS NOT NULL
          )
        ORDER BY c.nome_completo
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Busca também usuários sem colaborador vinculado que tenham telefone
    $usuarios_sem_colab = $pdo->query("
        SELECT u.id, u.nome AS nome_completo, u.telefone, 'usuario' AS tipo
        FROM usuarios u
        WHERE u.status = 'ativo'
          AND u.whatsapp_ativo = 1
          AND u.telefone IS NOT NULL
          AND u.telefone != ''
          AND (u.colaborador_id IS NULL OR u.colaborador_id = 0)
          AND u.id NOT IN (
              SELECT e.usuario_id FROM emocoes e WHERE e.data_registro = CURDATE() AND e.usuario_id IS NOT NULL
          )
        ORDER BY u.nome
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "❌ Erro ao buscar destinatários: " . $e->getMessage() . "\n";
    exit(1);
}

$destinatarios = array_merge($colaboradores, $usuarios_sem_colab);
$total    = count($destinatarios);
$enviados = 0;
$erros    = 0;

echo "📋 Destinatários: " . count($colaboradores) . " colaboradores + " . count($usuarios_sem_colab) . " usuários = {$total} total\n\n";

if ($total === 0) {
    echo "ℹ️  Nenhum destinatário pendente. Diagnóstico:\n";
    try {
        $diag_total_c  = (int)$pdo->query("SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo'")->fetchColumn();
        $diag_sem_tel  = (int)$pdo->query("SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo' AND (telefone IS NULL OR telefone = '')")->fetchColumn();
        $diag_optout   = (int)$pdo->query("SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo' AND whatsapp_ativo = 0")->fetchColumn();
        $diag_com_wa   = (int)$pdo->query("SELECT COUNT(*) FROM colaboradores WHERE status = 'ativo' AND whatsapp_ativo = 1 AND telefone IS NOT NULL AND telefone != ''")->fetchColumn();
        $diag_total_u  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo' AND (colaborador_id IS NULL OR colaborador_id = 0) AND telefone IS NOT NULL AND telefone != '' AND whatsapp_ativo = 1")->fetchColumn();
        $diag_ja_env   = (int)$pdo->query("SELECT COUNT(*) FROM humor_pesquisa_envios WHERE data_envio = CURDATE()")->fetchColumn();
        $diag_ja_emoc  = (int)$pdo->query("SELECT COUNT(DISTINCT COALESCE(e.colaborador_id, u.colaborador_id)) FROM emocoes e LEFT JOIN usuarios u ON u.id = e.usuario_id WHERE e.data_registro = CURDATE()")->fetchColumn();

        echo "   - Colaboradores ativos total:  {$diag_total_c}\n";
        echo "   - Sem telefone cadastrado:     {$diag_sem_tel}\n";
        echo "   - Com whatsapp_ativo=0:        {$diag_optout}\n";
        echo "   - Com WA configurado e ativo:  {$diag_com_wa}\n";
        echo "   - Usuários (sem colab) com WA: {$diag_total_u}\n";
        echo "   - Já receberam pesquisa hoje:  {$diag_ja_env}\n";
        echo "   - Já registraram emoção hoje:  {$diag_ja_emoc}\n";
    } catch (Exception $de) {
        echo "   ❌ Erro no diagnóstico: " . $de->getMessage() . "\n";
    }
    exit(0);
}

// ─── Envia pesquisa para cada destinatário ────────────────────────────────────
foreach ($destinatarios as $dest) {
    $nome = $dest['nome_completo'];
    $id   = (int)$dest['id'];
    $tipo = $dest['tipo'];

    echo "  [{$tipo}] Enviando para {$nome} ({$dest['telefone']})... ";

    if ($tipo === 'colaborador') {
        $result = evolution_enviar_pesquisa_humor($id, $config['mensagem_pesquisa_humor'] ?? '');
    } else {
        // Usuário sem colaborador: enfileira manualmente como texto simples
        $primeiro_nome = explode(' ', $nome)[0];
        $msg_custom = $config['mensagem_pesquisa_humor'] ?? '';
        if (empty($msg_custom)) {
            $msg_custom = "Bom dia, *{$primeiro_nome}*! 😊\n\nComo você está se sentindo hoje?\nResponda com um número:\n\n5 - 😄 Muito feliz\n4 - 🙂 Feliz\n3 - 😐 Neutro\n2 - 😕 Triste\n1 - 😞 Muito triste\n\n_Sua resposta nos ajuda a cuidar melhor do time!_";
        } else {
            $msg_custom = str_replace('{nome}', $primeiro_nome, $msg_custom);
        }

        $numero = evolution_normalizar_numero($dest['telefone']);
        $enfileirado = evolution_enfileirar_mensagem(null, $numero, '🌡️ Como você está hoje?', $msg_custom, '', 'pesquisa_humor');
        $result = ['success' => $enfileirado];
    }

    if (!empty($result['success'])) {
        echo "✅ Enfileirado\n";
        $enviados++;
    } else {
        echo "❌ Erro: " . ($result['error'] ?? 'desconhecido') . "\n";
        $erros++;
    }

    usleep(100000);
}

// ─── Resumo ───────────────────────────────────────────────────────────────────
echo "\n=== RESUMO ===\n";
echo "Total de colaboradores: {$total}\n";
echo "Enviados com sucesso:   {$enviados}\n";
echo "Erros:                  {$erros}\n";
echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
