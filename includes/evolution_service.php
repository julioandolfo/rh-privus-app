<?php
/**
 * Evolution API Service - Integração WhatsApp
 * 
 * Responsável por toda comunicação com a Evolution API para
 * envio de mensagens WhatsApp, gestão de instâncias e processamento
 * de webhooks recebidos.
 */

require_once __DIR__ . '/functions.php';

/**
 * Busca a configuração ativa da Evolution API
 */
function evolution_get_config(): ?array {
    try {
        $pdo = getDB();

        $stmt = $pdo->query("SHOW TABLES LIKE 'evolution_config'");
        if ($stmt->rowCount() === 0) {
            return null;
        }

        $stmt = $pdo->query("SELECT * FROM evolution_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config || !$config['ativo']) {
            return null;
        }

        return $config;
    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao buscar config: ' . $e->getMessage());
        return null;
    }
}

/**
 * Faz uma requisição HTTP para a Evolution API
 */
function evolution_request(string $method, string $endpoint, array $body = [], ?array $config = null): array {
    if (!$config) {
        $config = evolution_get_config();
    }

    if (!$config) {
        return ['success' => false, 'error' => 'Evolution API não configurada ou inativa.'];
    }

    $url = rtrim($config['api_url'], '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . $config['api_key'],
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response     = curl_exec($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error   = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => 'cURL error: ' . $curl_error];
    }

    $data = json_decode($response, true);

    return [
        'success'   => $http_code >= 200 && $http_code < 300,
        'http_code' => $http_code,
        'data'      => $data,
        'raw'       => $response,
    ];
}

/**
 * Normaliza número de telefone para formato WA (somente dígitos, com DDI 55)
 */
function evolution_normalizar_numero(string $numero): string {
    $numero = preg_replace('/\D/', '', $numero);

    // Remove DDI 55 se já vier e readiciona corretamente
    if (strlen($numero) === 13 && str_starts_with($numero, '55')) {
        // Celular com DDI: 55 + DDD(2) + 9 + número(8) = 13 dígitos — OK
        return $numero;
    }

    if (strlen($numero) === 12 && str_starts_with($numero, '55')) {
        // Fixo com DDI: 55 + DDD(2) + número(8) = 12 dígitos — OK
        return $numero;
    }

    if (strlen($numero) === 11) {
        // Celular sem DDI: DDD(2) + 9 + número(8)
        return '55' . $numero;
    }

    if (strlen($numero) === 10) {
        // Fixo sem DDI: DDD(2) + número(8)
        return '55' . $numero;
    }

    // Retorna como veio (melhor esforço)
    return '55' . $numero;
}

/**
 * Envia mensagem de texto simples via WhatsApp
 *
 * @param string $numero      Número do destinatário (com ou sem DDI)
 * @param string $mensagem    Texto da mensagem
 * @param int|null $colaborador_id  Para log
 * @param string $tipo        Tipo para o log (notificacao, pesquisa_humor, manual)
 */
function evolution_enviar_texto(string $numero, string $mensagem, ?int $colaborador_id = null, string $tipo = 'notificacao'): array {
    $config = evolution_get_config();

    if (!$config) {
        return ['success' => false, 'error' => 'Evolution API não configurada'];
    }

    $numero_formatado = evolution_normalizar_numero($numero);
    $instance         = $config['instance_name'];

    $body = [
        'number'  => $numero_formatado,
        'text'    => $mensagem,
        'options' => ['delay' => 500],
    ];

    $result = evolution_request('POST', "message/sendText/{$instance}", $body, $config);

    // Registra no log
    evolution_log_mensagem(
        $colaborador_id,
        $numero_formatado,
        $tipo,
        $mensagem,
        $result['success'] ? 'enviado' : 'erro',
        $result['data'] ?? null,
        $result['data']['key']['id'] ?? null,
        $result['success'] ? null : ($result['error'] ?? $result['raw'] ?? null)
    );

    return $result;
}

/**
 * Envia mensagem com botões de resposta rápida
 *
 * @param string   $numero
 * @param string   $titulo     Texto principal
 * @param string   $descricao  Subtexto (footer)
 * @param array    $botoes     [['id' => '1', 'text' => 'Excelente 😄'], ...]
 * @param int|null $colaborador_id
 */
function evolution_enviar_botoes(string $numero, string $titulo, string $descricao, array $botoes, ?int $colaborador_id = null, string $tipo = 'pesquisa_humor'): array {
    $config = evolution_get_config();

    if (!$config) {
        return ['success' => false, 'error' => 'Evolution API não configurada'];
    }

    $numero_formatado = evolution_normalizar_numero($numero);
    $instance         = $config['instance_name'];

    $buttons = array_map(fn($b) => [
        'type'        => 'reply',
        'reply'       => ['id' => (string)$b['id'], 'title' => $b['text']],
    ], $botoes);

    $body = [
        'number'   => $numero_formatado,
        'title'    => $titulo,
        'description' => $descricao,
        'footer'   => 'RH Privus',
        'buttons'  => $buttons,
    ];

    $result = evolution_request('POST', "message/sendButtons/{$instance}", $body, $config);

    evolution_log_mensagem(
        $colaborador_id,
        $numero_formatado,
        $tipo,
        $titulo . "\n" . $descricao,
        $result['success'] ? 'enviado' : 'erro',
        $result['data'] ?? null,
        $result['data']['key']['id'] ?? null,
        $result['success'] ? null : ($result['error'] ?? $result['raw'] ?? null)
    );

    // Fallback: se botões não suportados, envia texto simples
    if (!$result['success']) {
        $texto_fallback = "*{$titulo}*\n{$descricao}\n\n";
        foreach ($botoes as $b) {
            $texto_fallback .= "Digite *{$b['id']}* para {$b['text']}\n";
        }
        return evolution_enviar_texto($numero, $texto_fallback, $colaborador_id, $tipo);
    }

    return $result;
}

/**
 * Verifica o status da conexão de uma instância
 */
function evolution_verificar_conexao(?array $config = null): array {
    if (!$config) {
        $config = evolution_get_config();
    }

    if (!$config) {
        return ['success' => false, 'connected' => false, 'error' => 'Não configurado'];
    }

    $result = evolution_request('GET', "instance/connectionState/{$config['instance_name']}", [], $config);

    if (!$result['success']) {
        return ['success' => false, 'connected' => false, 'error' => $result['error'] ?? 'Erro de conexão'];
    }

    $state = $result['data']['instance']['state'] ?? $result['data']['state'] ?? 'unknown';

    return [
        'success'   => true,
        'connected' => $state === 'open',
        'state'     => $state,
        'data'      => $result['data'],
    ];
}

/**
 * Envia notificação do sistema para um colaborador via WhatsApp
 * Respeita o opt-in do colaborador (whatsapp_ativo)
 *
 * @param int    $colaborador_id
 * @param string $titulo
 * @param string $mensagem
 * @param string $url   URL opcional para incluir na mensagem
 */
function evolution_notificar_colaborador(int $colaborador_id, string $titulo, string $mensagem, string $url = ''): array {
    try {
        $pdo = getDB();

        // Verifica opt-in e número do colaborador
        $stmt = $pdo->prepare("
            SELECT nome_completo, whatsapp_numero, whatsapp_ativo
            FROM colaboradores
            WHERE id = ? AND status = 'ativo'
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador || !$colaborador['whatsapp_ativo'] || empty($colaborador['whatsapp_numero'])) {
            return ['success' => false, 'error' => 'Colaborador sem WhatsApp configurado ou opt-out'];
        }

        // Verifica se notificações WA estão ativas na config
        $config = evolution_get_config();
        if (!$config || !$config['notificacoes_whatsapp_ativas']) {
            return ['success' => false, 'error' => 'Notificações WhatsApp desativadas'];
        }

        // Monta mensagem formatada
        $nome = $colaborador['nome_completo'];
        $texto = "👋 Olá, *{$nome}*!\n\n";
        $texto .= "*{$titulo}*\n\n";
        $texto .= $mensagem;

        if (!empty($url)) {
            $texto .= "\n\n🔗 Acesse: {$url}";
        }

        $texto .= "\n\n_RH Privus_";

        return evolution_enviar_texto($colaborador['whatsapp_numero'], $texto, $colaborador_id, 'notificacao');

    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao notificar colaborador: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envia pesquisa de humor para um colaborador
 *
 * @param int    $colaborador_id
 * @param string $mensagem_customizada  Mensagem personalizada (opcional)
 */
function evolution_enviar_pesquisa_humor(int $colaborador_id, string $mensagem_customizada = ''): array {
    try {
        $pdo = getDB();

        $stmt = $pdo->prepare("
            SELECT nome_completo, whatsapp_numero, whatsapp_ativo
            FROM colaboradores
            WHERE id = ? AND status = 'ativo'
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador || !$colaborador['whatsapp_ativo'] || empty($colaborador['whatsapp_numero'])) {
            return ['success' => false, 'error' => 'Colaborador sem WhatsApp configurado'];
        }

        $nome      = explode(' ', $colaborador['nome_completo'])[0]; // Primeiro nome
        $hoje      = date('Y-m-d');

        // Verifica se já foi enviado hoje (tabela de controle de envios)
        $stmt = $pdo->prepare("SELECT id FROM humor_pesquisa_envios WHERE colaborador_id = ? AND data_envio = ?");
        $stmt->execute([$colaborador_id, $hoje]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Pesquisa já enviada hoje para este colaborador'];
        }

        // Verifica também se já registrou emoção hoje de qualquer canal
        $stmt = $pdo->prepare("
            SELECT e.id FROM emocoes e
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            WHERE (e.colaborador_id = ? OR u.colaborador_id = ?) AND e.data_registro = ?
            LIMIT 1
        ");
        $stmt->execute([$colaborador_id, $colaborador_id, $hoje]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Colaborador já registrou emoção hoje'];
        }

        $config = evolution_get_config();

        if (!empty($mensagem_customizada)) {
            $texto_intro = $mensagem_customizada;
        } elseif (!empty($config['mensagem_pesquisa_humor'])) {
            $texto_intro = str_replace('{nome}', $nome, $config['mensagem_pesquisa_humor']);
        } else {
            $texto_intro = "Bom dia, *{$nome}*! 😊\n\nComo você está se sentindo hoje?\nSua resposta nos ajuda a cuidar melhor do time!";
        }

        $botoes = [
            ['id' => '5', 'text' => '😄 Ótimo'],
            ['id' => '4', 'text' => '🙂 Bem'],
            ['id' => '3', 'text' => '😐 Regular'],
            ['id' => '2', 'text' => '😕 Mal'],
            ['id' => '1', 'text' => '😞 Muito mal'],
        ];

        $result = evolution_enviar_botoes(
            $colaborador['whatsapp_numero'],
            '🌡️ Pesquisa de Humor Diário',
            $texto_intro,
            $botoes,
            $colaborador_id,
            'pesquisa_humor'
        );

        // Registra o envio na tabela de controle
        $log_id = null;
        if ($result['success']) {
            $stmt_log = $pdo->prepare("SELECT id FROM evolution_mensagens_log WHERE colaborador_id = ? ORDER BY id DESC LIMIT 1");
            $stmt_log->execute([$colaborador_id]);
            $log_row  = $stmt_log->fetch();
            $log_id   = $log_row['id'] ?? null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO humor_pesquisa_envios (colaborador_id, data_envio, enviado, mensagem_log_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enviado = VALUES(enviado), mensagem_log_id = VALUES(mensagem_log_id)
        ");
        $stmt->execute([$colaborador_id, $hoje, $result['success'] ? 1 : 0, $log_id]);

        return $result;

    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao enviar pesquisa humor: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Processa resposta de humor recebida via webhook
 * Salva diretamente na tabela `emocoes` (mesma do sistema web) com canal='whatsapp'
 *
 * @param int    $colaborador_id
 * @param string $resposta  Texto ou ID do botão enviado pelo colaborador
 */
function evolution_processar_resposta_humor(int $colaborador_id, string $resposta): bool {
    try {
        $pdo  = getDB();
        $hoje = date('Y-m-d');

        // Mapeia resposta para nota 1-5 (compatível com nivel_emocao da tabela emocoes)
        // Botões: ID 5=Ótimo, 4=Bem, 3=Regular, 2=Mal, 1=Muito mal
        $humor_map = [
            '5' => 5, '4' => 4, '3' => 3, '2' => 2, '1' => 1,
            '😄' => 5, '🙂' => 4, '😐' => 3, '😕' => 2, '😞' => 1,
            'ótimo' => 5, 'otimo' => 5, 'bem' => 4, 'regular' => 3, 'mal' => 2, 'muito mal' => 1,
        ];

        $resposta_lower = mb_strtolower(trim($resposta));
        $nivel_emocao   = null;

        foreach ($humor_map as $chave => $valor) {
            if (str_contains($resposta_lower, (string)$chave)) {
                $nivel_emocao = $valor;
                break;
            }
        }

        if ($nivel_emocao === null) {
            return false;
        }

        // Busca usuario_id vinculado ao colaborador
        $stmt = $pdo->prepare("
            SELECT c.whatsapp_numero, c.nome_completo, u.id as usuario_id
            FROM colaboradores c
            LEFT JOIN usuarios u ON u.colaborador_id = c.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador) {
            return false;
        }

        $usuario_id = $colaborador['usuario_id'] ?? null;

        // Verifica se já registrou emoção hoje (web ou whatsapp)
        if ($usuario_id) {
            $stmt = $pdo->prepare("SELECT id FROM emocoes WHERE usuario_id = ? AND data_registro = ?");
            $stmt->execute([$usuario_id, $hoje]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM emocoes WHERE colaborador_id = ? AND data_registro = ?");
            $stmt->execute([$colaborador_id, $hoje]);
        }

        if ($stmt->fetch()) {
            // Já registrou hoje — envia mensagem amigável
            $nome = explode(' ', $colaborador['nome_completo'])[0];
            if (!empty($colaborador['whatsapp_numero'])) {
                $msg = "ℹ️ *{$nome}*, você já registrou sua emoção hoje!\n\nObrigado pela participação. 💙";
                evolution_enviar_texto($colaborador['whatsapp_numero'], $msg, $colaborador_id, 'resposta');
            }
            return false;
        }

        // Salva na tabela emocoes com canal='whatsapp'
        $stmt = $pdo->prepare("
            INSERT INTO emocoes (usuario_id, colaborador_id, nivel_emocao, descricao, data_registro, canal)
            VALUES (?, ?, ?, ?, ?, 'whatsapp')
        ");
        $stmt->execute([$usuario_id, $colaborador_id, $nivel_emocao, null, $hoje]);

        // Marca pesquisa como respondida (tabela de controle de envios)
        $stmt = $pdo->prepare("
            UPDATE humor_pesquisa_envios SET respondido = 1
            WHERE colaborador_id = ? AND data_envio = ?
        ");
        $stmt->execute([$colaborador_id, $hoje]);

        // Envia confirmação ao colaborador
        $emojis_conf = [5 => '😄', 4 => '🙂', 3 => '😐', 2 => '😕', 1 => '😞'];
        $labels_conf = [5 => 'Muito feliz', 4 => 'Feliz', 3 => 'Neutro', 2 => 'Triste', 1 => 'Muito triste'];
        $nome        = explode(' ', $colaborador['nome_completo'])[0];
        $emoji_conf  = $emojis_conf[$nivel_emocao] ?? '';
        $label_conf  = $labels_conf[$nivel_emocao] ?? '';

        if (!empty($colaborador['whatsapp_numero'])) {
            $confirmacao = "✅ Obrigado, *{$nome}*! Registramos que você está *{$label_conf}* {$emoji_conf} hoje.\n\n_Sua resposta é importante para o time!_ 💙";
            evolution_enviar_texto($colaborador['whatsapp_numero'], $confirmacao, $colaborador_id, 'resposta');
        }

        return true;

    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao processar resposta humor: ' . $e->getMessage());
        return false;
    }
}

/**
 * Registra mensagem no log do banco de dados
 */
function evolution_log_mensagem(
    ?int $colaborador_id,
    string $numero,
    string $tipo,
    string $mensagem,
    string $status,
    ?array $response_data,
    ?string $message_id,
    ?string $erro
): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO evolution_mensagens_log
                (colaborador_id, numero_destino, tipo, mensagem, status, response_data, message_id, erro_detalhe, enviado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $numero,
            $tipo,
            $mensagem,
            $status,
            $response_data ? json_encode($response_data) : null,
            $message_id,
            $erro,
            $status === 'enviado' ? date('Y-m-d H:i:s') : null,
        ]);
    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao salvar log: ' . $e->getMessage());
    }
}

/**
 * Busca colaborador pelo número de WhatsApp
 *
 * @param string $numero  Número normalizado (com DDI)
 */
function evolution_buscar_colaborador_por_numero(string $numero): ?array {
    try {
        $pdo = getDB();

        // Remove DDI 55 para comparar com o número armazenado sem DDI
        $sem_ddi   = preg_replace('/^55/', '', preg_replace('/\D/', '', $numero));
        $com_ddi   = '55' . $sem_ddi;
        $variantes = [$sem_ddi, $com_ddi, '+' . $com_ddi];

        $placeholders = implode(',', array_fill(0, count($variantes), '?'));

        $stmt = $pdo->prepare("
            SELECT id, nome_completo, whatsapp_numero, whatsapp_ativo
            FROM colaboradores
            WHERE REGEXP_REPLACE(whatsapp_numero, '[^0-9]', '') IN ({$placeholders})
            AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute($variantes);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (Exception $e) {
        error_log('[EvolutionAPI] Erro ao buscar colaborador por número: ' . $e->getMessage());
        return null;
    }
}
