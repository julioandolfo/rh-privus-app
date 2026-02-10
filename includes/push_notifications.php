<?php
/**
 * Funções helper para enviar notificações push
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/onesignal_service.php';

if (!function_exists('push_log')) {
    function push_log($message)
    {
        if (function_exists('log_push_debug')) {
            log_push_debug($message);
        } else {
            error_log($message);
        }
    }
}

/**
 * Envia notificação push para um colaborador específico
 * 
 * @param int $colaborador_id ID do colaborador
 * @param string $titulo Título da notificação
 * @param string $mensagem Mensagem da notificação
 * @param string $url URL para abrir ao clicar (opcional)
 * @param string $tipo Tipo da notificação (promocao, ocorrencia, etc)
 * @param int $referencia_id ID da referência (opcional)
 * @param string $referencia_tipo Tipo da referência (opcional)
 * @return array ['success' => bool, 'enviadas' => int, 'message' => string, 'notificacao_id' => int]
 */
function enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url = null, $tipo = 'geral', $referencia_id = null, $referencia_tipo = null) {
    try {
        $pdo = getDB();
        
        // Busca usuário do colaborador
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
        $usuario = $stmt->fetch();
        $usuario_id = $usuario['id'] ?? null;
        
        // Cria notificação no banco de dados
        require_once __DIR__ . '/notificacoes.php';
        $notificacao_id = criar_notificacao(
            $usuario_id,
            $colaborador_id,
            $tipo,
            $titulo,
            $mensagem,
            $url,
            $referencia_id,
            $referencia_tipo
        );
        
        if (!$notificacao_id) {
            throw new Exception('Erro ao criar notificação no banco de dados');
        }
        
        // Gera token único para login automático (válido por 7 dias)
        $token = bin2hex(random_bytes(32));
        $expira_em = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Constrói URL com token para login automático
        $basePath = get_base_url();
        $url_notificacao = $basePath . '/pages/notificacao_view.php?id=' . $notificacao_id . '&token=' . $token;
        
        // Registra notificação push no banco
        $stmt = $pdo->prepare("
            INSERT INTO notificacoes_push (notificacao_id, usuario_id, colaborador_id, token, titulo, mensagem, url, expira_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $notificacao_id,
            $usuario_id,
            $colaborador_id,
            $token,
            $titulo,
            $mensagem,
            $url_notificacao,
            $expira_em
        ]);
        $push_id = $pdo->lastInsertId();
        
        // Envia push notification
        $result = onesignal_send_notification([
            'colaborador_id' => $colaborador_id,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url_notificacao,
        ], 'push_log');
        
        // Atualiza status de envio
        if ($result['enviadas'] > 0) {
            $stmt = $pdo->prepare("UPDATE notificacoes_push SET enviado = 1, enviado_em = NOW() WHERE id = ?");
            $stmt->execute([$push_id]);
        }
        
        return [
            'success' => true,
            'enviadas' => $result['enviadas'],
            'message' => 'Notificação enviada com sucesso',
            'notificacao_id' => $notificacao_id,
            'push_id' => $push_id
        ];
        
    } catch (Exception $e) {
        push_log("enviar_push_colaborador - Exceção: " . $e->getMessage());
        return [
            'success' => false,
            'enviadas' => 0,
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}

/**
 * Envia notificação push para um usuário específico
 * 
 * @param int $usuario_id ID do usuário
 * @param string $titulo Título da notificação
 * @param string $mensagem Mensagem da notificação
 * @param string $url URL para abrir ao clicar (opcional)
 * @param string $tipo Tipo da notificação (promocao, ocorrencia, etc)
 * @param int $referencia_id ID da referência (opcional)
 * @param string $referencia_tipo Tipo da referência (opcional)
 * @return array ['success' => bool, 'enviadas' => int, 'message' => string, 'notificacao_id' => int]
 */
function enviar_push_usuario($usuario_id, $titulo, $mensagem, $url = null, $tipo = 'geral', $referencia_id = null, $referencia_tipo = null) {
    try {
        $pdo = getDB();
        
        // Busca colaborador do usuário
        $stmt = $pdo->prepare("SELECT colaborador_id FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        $colaborador_id = $usuario['colaborador_id'] ?? null;
        
        // Cria notificação no banco de dados
        require_once __DIR__ . '/notificacoes.php';
        $notificacao_id = criar_notificacao(
            $usuario_id,
            $colaborador_id,
            $tipo,
            $titulo,
            $mensagem,
            $url,
            $referencia_id,
            $referencia_tipo
        );
        
        if (!$notificacao_id) {
            throw new Exception('Erro ao criar notificação no banco de dados');
        }
        
        // Gera token único para login automático (válido por 7 dias)
        $token = bin2hex(random_bytes(32));
        $expira_em = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Constrói URL com token para login automático
        $basePath = get_base_url();
        $url_notificacao = $basePath . '/pages/notificacao_view.php?id=' . $notificacao_id . '&token=' . $token;
        
        // Registra notificação push no banco
        $stmt = $pdo->prepare("
            INSERT INTO notificacoes_push (notificacao_id, usuario_id, colaborador_id, token, titulo, mensagem, url, expira_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $notificacao_id,
            $usuario_id,
            $colaborador_id,
            $token,
            $titulo,
            $mensagem,
            $url_notificacao,
            $expira_em
        ]);
        $push_id = $pdo->lastInsertId();
        
        // Envia push notification
        $result = onesignal_send_notification([
            'usuario_id' => $usuario_id,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url_notificacao,
        ], 'push_log');
        
        // Atualiza status de envio
        if ($result['enviadas'] > 0) {
            $stmt = $pdo->prepare("UPDATE notificacoes_push SET enviado = 1, enviado_em = NOW() WHERE id = ?");
            $stmt->execute([$push_id]);
        }
        
        return [
            'success' => true,
            'enviadas' => $result['enviadas'],
            'message' => 'Notificação enviada com sucesso',
            'notificacao_id' => $notificacao_id,
            'push_id' => $push_id
        ];
        
    } catch (Exception $e) {
        push_log("enviar_push_usuario - Exceção: " . $e->getMessage());
        return [
            'success' => false,
            'enviadas' => 0,
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}

/**
 * Envia notificação push para múltiplos colaboradores
 * 
 * @param array $colaboradores_ids Array com IDs dos colaboradores
 * @param string $titulo Título da notificação
 * @param string $mensagem Mensagem da notificação
 * @param string $url URL para abrir ao clicar (opcional)
 * @return array ['success' => bool, 'enviadas' => int, 'falhas' => int]
 */
function enviar_push_colaboradores($colaboradores_ids, $titulo, $mensagem, $url = null) {
    $enviadas_total = 0;
    $falhas = 0;
    
    foreach ($colaboradores_ids as $colab_id) {
        $result = enviar_push_colaborador($colab_id, $titulo, $mensagem, $url);
        if ($result['success']) {
            $enviadas_total += $result['enviadas'];
        } else {
            $falhas++;
        }
    }
    
    return [
        'success' => $enviadas_total > 0,
        'enviadas' => $enviadas_total,
        'falhas' => $falhas
    ];
}

