<?php
/**
 * Sistema de Notificações do Chat
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/onesignal_service.php';
require_once __DIR__ . '/email.php';

/**
 * Envia notificação de nova conversa para RHs
 */
function enviar_notificacao_nova_conversa($conversa_id) {
    $pdo = getDB();
    
    // Busca dados da conversa
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    if (!$conversa) {
        return false;
    }
    
    // Busca usuários RH
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.nome, u.email
        FROM usuarios u
        WHERE u.role IN ('ADMIN', 'RH') AND u.status = 'ativo'
    ");
    $rhs = $stmt->fetchAll();
    
    $base_url = get_base_url();
    $url = $base_url . '/pages/chat_gestao.php?conversa=' . $conversa_id;
    
    foreach ($rhs as $rh) {
        // Verifica preferências
        $prefs = buscar_preferencias_chat($rh['id'], null);
        if (!$prefs['notificacoes_push']) {
            continue;
        }
        
        // Envia push
        try {
            onesignal_send_notification([
                'usuario_id' => $rh['id'],
                'titulo' => 'Nova Conversa',
                'mensagem' => "Nova conversa de {$conversa['colaborador_nome']}: {$conversa['titulo']}",
                'url' => $url,
                'icone' => $base_url . '/assets/chat-icon.png'
            ]);
        } catch (Exception $e) {
            error_log("Erro ao enviar push: " . $e->getMessage());
        }
        
        // Envia email se configurado
        if ($prefs['notificacoes_email']) {
            try {
                enviar_email_chat($rh['email'], 'Nova Conversa', $conversa['colaborador_nome'], $conversa['titulo'], $url);
            } catch (Exception $e) {
                error_log("Erro ao enviar email: " . $e->getMessage());
            }
        }
    }
    
    return true;
}

/**
 * Envia notificação de nova mensagem
 */
function enviar_notificacao_nova_mensagem($conversa_id, $mensagem_id) {
    $pdo = getDB();
    
    // Busca dados da conversa e mensagem
    $stmt = $pdo->prepare("
        SELECT c.*, m.mensagem, m.enviado_por_usuario_id, m.enviado_por_colaborador_id,
               col.nome_completo as colaborador_nome,
               u.nome as usuario_nome
        FROM chat_conversas c
        INNER JOIN chat_mensagens m ON c.id = m.conversa_id
        LEFT JOIN colaboradores col ON c.colaborador_id = col.id
        LEFT JOIN usuarios u ON m.enviado_por_usuario_id = u.id
        WHERE c.id = ? AND m.id = ?
    ");
    $stmt->execute([$conversa_id, $mensagem_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        return false;
    }
    
    $base_url = get_base_url();
    $url = $base_url . '/pages/chat_gestao.php?conversa=' . $conversa_id;
    
    // Se mensagem foi do colaborador, notifica RHs
    if ($data['enviado_por_colaborador_id']) {
        // Busca RHs participantes ou atribuídos
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.nome, u.email
            FROM usuarios u
            LEFT JOIN chat_participantes p ON p.usuario_id = u.id AND p.conversa_id = ? AND p.removido = FALSE
            WHERE (u.id = ? OR p.usuario_id IS NOT NULL)
            AND u.role IN ('ADMIN', 'RH') AND u.status = 'ativo'
        ");
        $stmt->execute([$conversa_id, $data['atribuido_para_usuario_id']]);
        $destinatarios = $stmt->fetchAll();
        
        foreach ($destinatarios as $rh) {
            $prefs = buscar_preferencias_chat($rh['id'], null);
            if (!$prefs['notificacoes_push']) continue;
            
            try {
                onesignal_send_notification([
                    'usuario_id' => $rh['id'],
                    'titulo' => $data['colaborador_nome'],
                    'mensagem' => substr($data['mensagem'], 0, 100),
                    'url' => $url
                ]);
            } catch (Exception $e) {
                error_log("Erro ao enviar push: " . $e->getMessage());
            }
        }
    } else {
        // Mensagem do RH, notifica colaborador
        $prefs = buscar_preferencias_chat(null, $data['colaborador_id']);
        if ($prefs['notificacoes_push']) {
            try {
                onesignal_send_notification([
                    'colaborador_id' => $data['colaborador_id'],
                    'titulo' => $data['usuario_nome'] ?? 'RH',
                    'mensagem' => substr($data['mensagem'], 0, 100),
                    'url' => $base_url . '/pages/dashboard.php?chat=' . $conversa_id
                ]);
            } catch (Exception $e) {
                error_log("Erro ao enviar push: " . $e->getMessage());
            }
        }
    }
    
    return true;
}

/**
 * Envia notificação de conversa atribuída
 */
function enviar_notificacao_conversa_atribuida($conversa_id, $usuario_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome, u.nome as usuario_nome
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        INNER JOIN usuarios u ON c.atribuido_para_usuario_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    if (!$conversa) {
        return false;
    }
    
    $prefs = buscar_preferencias_chat($usuario_id, null);
    if (!$prefs['notificacoes_push']) {
        return false;
    }
    
    $base_url = get_base_url();
    $url = $base_url . '/pages/chat_gestao.php?conversa=' . $conversa_id;
    
    try {
        onesignal_send_notification([
            'usuario_id' => $usuario_id,
            'titulo' => 'Conversa Atribuída',
            'mensagem' => "Conversa de {$conversa['colaborador_nome']} foi atribuída para você",
            'url' => $url
        ]);
    } catch (Exception $e) {
        error_log("Erro ao enviar push: " . $e->getMessage());
    }
    
    return true;
}

/**
 * Envia notificação de conversa fechada
 */
function enviar_notificacao_conversa_fechada($conversa_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT c.*, col.nome_completo as colaborador_nome
        FROM chat_conversas c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    if (!$conversa) {
        return false;
    }
    
    $prefs = buscar_preferencias_chat(null, $conversa['colaborador_id']);
    if (!$prefs['notificacoes_push']) {
        return false;
    }
    
    $base_url = get_base_url();
    
    try {
        onesignal_send_notification([
            'colaborador_id' => $conversa['colaborador_id'],
            'titulo' => 'Conversa Fechada',
            'mensagem' => "Sua conversa '{$conversa['titulo']}' foi fechada",
            'url' => $base_url . '/pages/dashboard.php?chat=' . $conversa_id
        ]);
    } catch (Exception $e) {
        error_log("Erro ao enviar push: " . $e->getMessage());
    }
    
    return true;
}

/**
 * Envia email de notificação do chat
 */
function enviar_email_chat($email, $assunto_base, $nome_colaborador, $titulo, $url) {
    $assunto = "[Chat RH] {$assunto_base}";
    $mensagem_html = "
        <h2>{$assunto_base}</h2>
        <p><strong>Colaborador:</strong> {$nome_colaborador}</p>
        <p><strong>Título:</strong> {$titulo}</p>
        <p><a href='{$url}'>Abrir Conversa</a></p>
    ";
    
    return enviar_email($email, $assunto, $mensagem_html);
}

