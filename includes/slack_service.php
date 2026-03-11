<?php
/**
 * Slack Service - Integração com a API do Slack
 *
 * Responsável por toda comunicação com a API do Slack:
 *  - Envio de DMs para colaboradores
 *  - Postagem em canais (comunicados)
 *  - Fila de mensagens com rate limiting
 *  - Sincronização de usuários por e-mail
 */

require_once __DIR__ . '/functions.php';

define('SLACK_API_BASE', 'https://slack.com/api/');

// ─── Config ──────────────────────────────────────────────────────────────────

function slack_get_config(): ?array {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SHOW TABLES LIKE 'slack_config'");
        if ($stmt->rowCount() === 0) return null;

        $config = $pdo->query("SELECT * FROM slack_config ORDER BY id DESC LIMIT 1")
                      ->fetch(PDO::FETCH_ASSOC);

        return ($config && $config['ativo']) ? $config : null;
    } catch (Exception $e) {
        error_log('[Slack] Erro ao buscar config: ' . $e->getMessage());
        return null;
    }
}

// ─── Request base ─────────────────────────────────────────────────────────────

/**
 * Faz uma chamada à Slack Web API.
 * Métodos GET usam query string; métodos POST usam JSON body.
 */
function slack_request(string $api_method, array $params = [], string $http_method = 'POST', ?array $config = null): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return ['ok' => false, 'error' => 'Slack não configurado ou inativo'];

    $url   = SLACK_API_BASE . $api_method;
    $token = $config['bot_token'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=utf-8',
        ],
    ]);

    if ($http_method === 'GET') {
        curl_setopt($ch, CURLOPT_URL, $params ? $url . '?' . http_build_query($params) : $url);
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    error_log(sprintf(
        '[Slack] %s %s | HTTP %d | cURL: %s | Response: %s',
        $http_method, $api_method, $http_code,
        $curl_error ?: 'OK',
        substr($response ?: '', 0, 400)
    ));

    if ($curl_error) {
        return ['ok' => false, 'error' => 'cURL: ' . $curl_error];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Resposta inválida da API', 'raw' => $response];
    }

    return $data;
}

// ─── Conexão ─────────────────────────────────────────────────────────────────

/**
 * Verifica a conexão com o workspace e retorna informações do bot.
 * Usa auth.test (não requer permissões extras).
 */
function slack_verificar_conexao(?array $config = null): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return ['ok' => false, 'error' => 'Não configurado'];

    $data = slack_request('auth.test', [], 'GET', $config);

    if (!empty($data['ok'])) {
        // Salva bot_user_id e workspace no banco se ainda não tiver
        try {
            $pdo = getDB();
            $pdo->prepare("UPDATE slack_config SET bot_user_id = ?, workspace_nome = ? WHERE id = ?")
                ->execute([$data['user_id'] ?? null, $data['team'] ?? null, $config['id']]);
        } catch (Exception $e) { /* não crítico */ }
    }

    return $data;
}

// ─── Usuários ────────────────────────────────────────────────────────────────

/**
 * Busca o Slack User ID de um colaborador pelo e-mail.
 * Salva o resultado em colaboradores.slack_user_id para uso futuro.
 */
function slack_sincronizar_usuario_por_email(int $colaborador_id, string $email, ?array $config = null): ?string {
    if (!$config) $config = slack_get_config();
    if (!$config || empty($email)) return null;

    $data = slack_request('users.lookupByEmail', ['email' => $email], 'GET', $config);

    if (empty($data['ok']) || empty($data['user']['id'])) {
        error_log("[Slack] Usuário não encontrado por email {$email}: " . ($data['error'] ?? 'erro desconhecido'));
        return null;
    }

    $slack_user_id = $data['user']['id'];

    try {
        $pdo = getDB();
        $pdo->prepare("UPDATE colaboradores SET slack_user_id = ? WHERE id = ?")
            ->execute([$slack_user_id, $colaborador_id]);
    } catch (Exception $e) {
        error_log('[Slack] Erro ao salvar slack_user_id: ' . $e->getMessage());
    }

    return $slack_user_id;
}

/**
 * Retorna o Slack User ID do colaborador (busca no banco, sincroniza se ausente).
 */
function slack_get_user_id(int $colaborador_id, ?array $config = null): ?string {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT slack_user_id, slack_ativo, email_pessoal FROM colaboradores WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$colaborador_id]);
        $colab = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colab || !$colab['slack_ativo']) return null;

        if (!empty($colab['slack_user_id'])) {
            return $colab['slack_user_id'];
        }

        // Tenta sincronizar pelo e-mail
        if (!empty($colab['email_pessoal'])) {
            return slack_sincronizar_usuario_por_email($colaborador_id, $colab['email_pessoal'], $config);
        }
    } catch (Exception $e) {
        error_log('[Slack] Erro ao buscar user_id: ' . $e->getMessage());
    }

    return null;
}

// ─── Envio ───────────────────────────────────────────────────────────────────

/**
 * Monta e envia uma mensagem Block Kit para um canal ou DM.
 * Usa fallback em texto simples para clientes sem suporte a Blocks.
 *
 * @param string $canal_destino  User ID (Uxxxxxxx) ou canal (#geral, Cxxxxxxx)
 * @param string $titulo         Texto do header
 * @param string $mensagem       Corpo (suporta *negrito*, _itálico_, `código`)
 * @param string $url            URL do botão "Ver detalhes" (opcional)
 * @param int|null $colaborador_id  Para log
 * @param string $tipo           notificacao | comunicado | manual
 */
function slack_enviar_mensagem(
    string $canal_destino,
    string $titulo,
    string $mensagem,
    string $url = '',
    ?int $colaborador_id = null,
    string $tipo = 'notificacao',
    ?array $config = null
): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return ['ok' => false, 'error' => 'Slack não configurado'];

    // ── Block Kit ──────────────────────────────────────────────────────────────
    $blocks = [
        [
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => $titulo, 'emoji' => true],
        ],
        [
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => $mensagem],
        ],
    ];

    if (!empty($url)) {
        $blocks[] = [
            'type'     => 'actions',
            'elements' => [[
                'type'      => 'button',
                'text'      => ['type' => 'plain_text', 'text' => '🔗 Ver detalhes', 'emoji' => true],
                'url'       => $url,
                'style'     => 'primary',
            ]],
        ];
    }

    $blocks[] = [
        'type'     => 'context',
        'elements' => [['type' => 'mrkdwn', 'text' => '_RH Privus • ' . date('d/m/Y H:i') . '_']],
    ];

    $payload = [
        'channel'   => $canal_destino,
        'text'      => "{$titulo}\n{$mensagem}" . ($url ? "\n{$url}" : ''), // fallback
        'blocks'    => $blocks,
        'unfurl_links' => false,
        'unfurl_media' => false,
    ];

    $result = slack_request('chat.postMessage', $payload, 'POST', $config);

    // ── Log ───────────────────────────────────────────────────────────────────
    slack_log_mensagem(
        $colaborador_id,
        $canal_destino,
        $tipo,
        $titulo,
        $mensagem,
        $result['ok'] ? 'enviado' : 'erro',
        $result['ts']    ?? null,
        $result['ok'] ? null : ($result['error'] ?? 'erro desconhecido')
    );

    return $result;
}

// ─── Fila ─────────────────────────────────────────────────────────────────────

/**
 * Enfileira uma mensagem Slack para processamento controlado pelo cron.
 */
function slack_enfileirar_mensagem(
    ?int $colaborador_id,
    string $canal_destino,
    string $titulo,
    string $mensagem,
    string $url = '',
    string $tipo = 'notificacao',
    ?\DateTime $agendado_para = null
): bool {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO slack_fila_mensagens
                (colaborador_id, canal_destino, titulo, mensagem, url, tipo, status, agendado_para)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $canal_destino,
            $titulo,
            $mensagem,
            $url,
            $tipo,
            $agendado_para ? $agendado_para->format('Y-m-d H:i:s') : null,
        ]);
        return true;
    } catch (Exception $e) {
        error_log('[Slack] Erro ao enfileirar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Envia notificação individual para um colaborador via DM no Slack.
 * Enfileira para envio controlado pelo cron.
 * Respeita slack_ativo do colaborador e notificacoes_slack_ativas da config.
 */
function slack_notificar_colaborador(int $colaborador_id, string $titulo, string $mensagem, string $url = ''): array {
    try {
        $config = slack_get_config();
        if (!$config) {
            error_log("[Slack] slack_notificar_colaborador: slack_config não encontrada ou inativa (colaborador_id={$colaborador_id}).");
            return ['ok' => false, 'error' => 'Slack não configurado ou inativo'];
        }
        if (empty($config['notificacoes_slack_ativas'])) {
            error_log("[Slack] slack_notificar_colaborador: notificacoes_slack_ativas=0 na slack_config (colaborador_id={$colaborador_id}).");
            return ['ok' => false, 'error' => 'Notificações Slack desativadas'];
        }

        $slack_user_id = slack_get_user_id($colaborador_id, $config);
        if (!$slack_user_id) {
            error_log("[Slack] slack_notificar_colaborador: colaborador_id={$colaborador_id} sem slack_user_id cadastrado.");
            return ['ok' => false, 'error' => 'Colaborador sem Slack User ID configurado'];
        }

        // Formata mensagem para o contexto de DM
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
        $stmt->execute([$colaborador_id]);
        $nome = $stmt->fetchColumn() ?: '';

        $texto = empty($nome) ? $mensagem : "Olá, *{$nome}*! 👋\n\n{$mensagem}";

        slack_enfileirar_mensagem($colaborador_id, $slack_user_id, $titulo, $texto, $url, 'notificacao');

        return ['ok' => true, 'queued' => true];
    } catch (Exception $e) {
        error_log('[Slack] Erro ao notificar colaborador: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Posta um comunicado no canal padrão do Slack (broadcast).
 * Enfileira para envio controlado.
 */
function slack_comunicado_no_canal(string $titulo, string $mensagem, string $url = '', ?array $config = null): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return ['ok' => false, 'error' => 'Slack não configurado'];
    if (!$config['comunicados_no_canal']) return ['ok' => false, 'error' => 'Postagem em canal desativada'];

    $canal = trim($config['canal_comunicados'] ?? '');
    if (empty($canal)) return ['ok' => false, 'error' => 'Canal de comunicados não configurado'];

    slack_enfileirar_mensagem(null, $canal, $titulo, $mensagem, $url, 'comunicado');

    return ['ok' => true, 'queued' => true, 'canal' => $canal];
}

// ─── Log ────────────────────────────────────────────────────────────────────

function slack_log_mensagem(
    ?int $colaborador_id,
    string $canal_destino,
    string $tipo,
    string $titulo,
    string $mensagem,
    string $status,
    ?string $ts,
    ?string $erro
): void {
    try {
        $pdo = getDB();
        $pdo->prepare("
            INSERT INTO slack_mensagens_log
                (colaborador_id, canal_destino, tipo, titulo, mensagem, status, ts, erro_detalhe)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$colaborador_id, $canal_destino, $tipo, $titulo, $mensagem, $status, $ts, $erro]);
    } catch (Exception $e) {
        error_log('[Slack] Erro ao salvar log: ' . $e->getMessage());
    }
}

// ─── Utilitários ─────────────────────────────────────────────────────────────

/**
 * Retorna a lista de canais públicos do workspace.
 * Útil para o dropdown de seleção de canal no painel.
 */
function slack_listar_canais(?array $config = null): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return [];

    $data = slack_request('conversations.list', [
        'types'            => 'public_channel',
        'exclude_archived' => true,
        'limit'            => 200,
    ], 'GET', $config);

    return $data['channels'] ?? [];
}

/**
 * Sincroniza slack_user_id de todos os colaboradores ativos com e-mail.
 * Retorna array ['sincronizados' => int, 'nao_encontrados' => int, 'erros' => int].
 */
function slack_sincronizar_todos(?array $config = null): array {
    if (!$config) $config = slack_get_config();
    if (!$config) return ['sincronizados' => 0, 'nao_encontrados' => 0, 'erros' => 0];

    $sincronizados   = 0;
    $nao_encontrados = 0;
    $erros           = 0;

    try {
        $pdo = getDB();
        $colaboradores = $pdo->query("
            SELECT id, email_pessoal FROM colaboradores
            WHERE status = 'ativo' AND slack_ativo = 1
              AND email_pessoal IS NOT NULL AND email_pessoal != ''
              AND (slack_user_id IS NULL OR slack_user_id = '')
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($colaboradores as $c) {
            usleep(500000); // 0.5s entre chamadas (rate limit users.lookupByEmail)
            $uid = slack_sincronizar_usuario_por_email((int)$c['id'], $c['email_pessoal'], $config);
            if ($uid) $sincronizados++;
            else $nao_encontrados++;
        }
    } catch (Exception $e) {
        error_log('[Slack] Erro na sincronização em massa: ' . $e->getMessage());
        $erros++;
    }

    return compact('sincronizados', 'nao_encontrados', 'erros');
}
