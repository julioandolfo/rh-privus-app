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
 * @return array ['success' => bool, 'enviadas' => int, 'message' => string]
 */
function enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url = null) {
    try {
        $pdo = getDB();
        
        if (!$url) {
            $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_id]);
            if ($stmt->fetch()) {
                $basePath = get_base_url();
                $url = $basePath . '/pages/colaborador_view.php?id=' . $colaborador_id;
            }
        }
        
        if (strpos($url, 'http') !== 0) {
            $basePath = get_base_url();
            $url = $basePath . '/' . ltrim($url, '/');
        }
        
        $result = onesignal_send_notification([
            'colaborador_id' => $colaborador_id,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url,
        ], 'push_log');
        
        return [
            'success' => true,
            'enviadas' => $result['enviadas'],
            'message' => 'Notificação enviada com sucesso',
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
 * @return array ['success' => bool, 'enviadas' => int, 'message' => string]
 */
function enviar_push_usuario($usuario_id, $titulo, $mensagem, $url = null) {
    try {
        if (!$url) {
            $basePath = get_base_url();
            $url = $basePath . '/pages/dashboard.php';
        }
        
        if (strpos($url, 'http') !== 0) {
            $basePath = get_base_url();
            $url = $basePath . '/' . ltrim($url, '/');
        }
        
        $result = onesignal_send_notification([
            'usuario_id' => $usuario_id,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url,
        ], 'push_log');
        
        return [
            'success' => true,
            'enviadas' => $result['enviadas'],
            'message' => 'Notificação enviada com sucesso'
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

