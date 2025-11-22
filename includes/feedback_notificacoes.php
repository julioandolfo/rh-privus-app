<?php
/**
 * Funções de Notificação para Feedbacks
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notificacoes.php';
require_once __DIR__ . '/push_notifications.php';
require_once __DIR__ . '/email_templates.php';

/**
 * Cria notificação de feedback recebido
 */
function criar_notificacao_feedback_recebido($feedback_id, $remetente_usuario_id, $remetente_colaborador_id, $destinatario_usuario_id, $destinatario_colaborador_id, $anonimo = false) {
    try {
        $pdo = getDB();
        
        // Busca nome do remetente
        $nome_remetente = 'Alguém';
        if (!$anonimo) {
            if ($remetente_usuario_id) {
                $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
                $stmt->execute([$remetente_usuario_id]);
                $user = $stmt->fetch();
                if ($user) {
                    $nome_remetente = $user['nome'];
                }
            } else if ($remetente_colaborador_id) {
                $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
                $stmt->execute([$remetente_colaborador_id]);
                $colab = $stmt->fetch();
                if ($colab) {
                    $nome_remetente = $colab['nome_completo'];
                }
            }
        }
        
        $titulo = 'Novo Feedback Recebido';
        $mensagem = $anonimo ? 'Você recebeu um feedback anônimo' : "$nome_remetente enviou um feedback para você";
        $link = '../pages/feedback_meus.php?tipo=recebidos';
        
        return criar_notificacao(
            $destinatario_usuario_id,
            $destinatario_colaborador_id,
            'feedback',
            $titulo,
            $mensagem,
            $link,
            $feedback_id,
            'feedback'
        );
        
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação de feedback: " . $e->getMessage());
        return false;
    }
}

/**
 * Envia email de notificação de feedback recebido
 */
function enviar_email_feedback_recebido($feedback_id) {
    try {
        $pdo = getDB();
        
        // Busca dados do feedback
        $stmt = $pdo->prepare("
            SELECT 
                f.*,
                COALESCE(ru.nome, rc.nome_completo) as remetente_nome,
                COALESCE(du.nome, dc.nome_completo) as destinatario_nome,
                COALESCE(du.email, dc.email_pessoal) as destinatario_email
            FROM feedbacks f
            LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
            LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
            LEFT JOIN usuarios du ON f.destinatario_usuario_id = du.id
            LEFT JOIN colaboradores dc ON f.destinatario_colaborador_id = dc.id OR (f.destinatario_usuario_id = du.id AND du.colaborador_id = dc.id)
            WHERE f.id = ?
        ");
        $stmt->execute([$feedback_id]);
        $feedback = $stmt->fetch();
        
        if (!$feedback || empty($feedback['destinatario_email'])) {
            return ['success' => false, 'message' => 'Feedback não encontrado ou destinatário sem email.'];
        }
        
        // Busca avaliações
        $stmt_av = $pdo->prepare("
            SELECT fa.item_id, fa.nota, fi.nome as item_nome
            FROM feedback_avaliacoes fa
            INNER JOIN feedback_itens fi ON fa.item_id = fi.id
            WHERE fa.feedback_id = ?
        ");
        $stmt_av->execute([$feedback_id]);
        $avaliacoes = $stmt_av->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepara variáveis do email
        $remetente_nome = $feedback['anonimo'] ? 'Anônimo' : ($feedback['remetente_nome'] ?? 'Alguém');
        $baseUrl = get_base_url();
        $link_feedback = $baseUrl . '/pages/feedback_meus.php?tipo=recebidos';
        
        // Monta HTML das avaliações
        $avaliacoes_html = '';
        if (!empty($avaliacoes)) {
            $avaliacoes_html = '<div class="avaliacoes-box">
                <strong style="display: block; margin-bottom: 15px; color: #333;">Avaliações:</strong>
                <ul>';
            foreach ($avaliacoes as $av) {
                $estrelas = '';
                for ($i = 1; $i <= 5; $i++) {
                    $estrelas .= $i <= $av['nota'] ? '★' : '☆';
                }
                $avaliacoes_html .= '<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                    <span style="font-weight: 600;">' . htmlspecialchars($av['item_nome']) . ':</span> 
                    <span class="estrelas" style="color: #ffc700; font-size: 18px;">' . $estrelas . '</span>
                </li>';
            }
            $avaliacoes_html .= '</ul></div>';
        } else {
            $avaliacoes_html = ''; // Se não houver avaliações, deixa vazio
        }
        
        // Monta badges de informações
        $anonimo_html = '';
        $presencial_html = '';
        if ($feedback['anonimo']) {
            $anonimo_html = '<span class="info-badge" style="display: inline-block; background-color: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 12px; margin: 5px 5px 5px 0;">🔒 Feedback Anônimo</span>';
        }
        if ($feedback['presencial']) {
            $presencial_html = '<span class="info-badge" style="display: inline-block; background-color: #d1ecf1; color: #0c5460; padding: 4px 12px; border-radius: 12px; font-size: 12px; margin: 5px 5px 5px 0;">👥 Feedback Presencial</span>';
        }
        
        // Tenta usar template de email se existir
        $template = buscar_template_email('feedback_recebido');
        
        if ($template) {
            $variaveis = [
                'nome_completo' => $feedback['destinatario_nome'],
                'remetente_nome' => $remetente_nome,
                'conteudo' => nl2br(htmlspecialchars($feedback['conteudo'])),
                'avaliacoes' => $avaliacoes_html ?: '<p style="color: #999; font-style: italic;">Nenhuma avaliação específica foi atribuída.</p>',
                'link_feedback' => $link_feedback,
                'anonimo' => $anonimo_html,
                'presencial' => $presencial_html
            ];
            
            return enviar_email_template('feedback_recebido', $feedback['destinatario_email'], $variaveis);
        } else {
            // Email padrão se não houver template
            $assunto = 'Novo Feedback Recebido - RH Privus';
            $mensagem_html = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #009ef7; color: white; padding: 20px; text-align: center; }
                        .content { background-color: #f9f9f9; padding: 30px; }
                        .button { display: inline-block; background-color: #009ef7; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>RH Privus</h2>
                        </div>
                        <div class='content'>
                            <p>Olá, <strong>{$feedback['destinatario_nome']}</strong>!</p>
                            <p>Você recebeu um novo feedback de <strong>{$remetente_nome}</strong>.</p>
                            {$avaliacoes_html}
                            <p><strong>Conteúdo do Feedback:</strong></p>
                            <div style='background-color: white; padding: 15px; border-left: 4px solid #009ef7; margin: 15px 0;'>
                                " . nl2br(htmlspecialchars($feedback['conteudo'])) . "
                            </div>
                            <p style='text-align: center;'>
                                <a href='{$link_feedback}' class='button'>Ver Feedback</a>
                            </p>
                            <p><small>Se você não solicitou esta notificação, pode ignorar este email.</small></p>
                        </div>
                        <div class='footer'>
                            <p>Este é um email automático, por favor não responda.</p>
                            <p>&copy; " . date('Y') . " RH Privus - Todos os direitos reservados</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            require_once __DIR__ . '/email.php';
            return enviar_email($feedback['destinatario_email'], $assunto, $mensagem_html, [
                'nome_destinatario' => $feedback['destinatario_nome']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email de feedback: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Envia push notification de feedback recebido
 */
function enviar_push_feedback_recebido($feedback_id, $destinatario_usuario_id, $destinatario_colaborador_id, $remetente_nome, $anonimo = false) {
    try {
        // Verifica preferência de notificação push antes de enviar
        require_once __DIR__ . '/push_preferences.php';
        
        if (!verificar_preferencia_push($destinatario_usuario_id, $destinatario_colaborador_id, 'feedback_recebido')) {
            // Usuário desativou notificações push para este tipo
            return [
                'success' => true, 
                'enviadas' => 0,
                'message' => 'Notificação push desativada pelo usuário'
            ];
        }
        
        $titulo = 'Novo Feedback Recebido';
        $mensagem = $anonimo ? 'Você recebeu um feedback anônimo' : "$remetente_nome enviou um feedback para você";
        $url = '../pages/feedback_meus.php?tipo=recebidos';
        
        if ($destinatario_usuario_id) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_usuario($destinatario_usuario_id, $titulo, $mensagem, $url);
        } elseif ($destinatario_colaborador_id) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_colaborador($destinatario_colaborador_id, $titulo, $mensagem, $url);
        }
        
        return ['success' => false, 'message' => 'Destinatário não identificado'];
        
    } catch (Exception $e) {
        error_log("Erro ao enviar push de feedback: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Notifica sobre feedback recebido (todas as formas)
 */
function notificar_feedback_recebido($feedback_id) {
    try {
        $pdo = getDB();
        
        // Busca dados do feedback
        $stmt = $pdo->prepare("
            SELECT 
                f.*,
                COALESCE(ru.nome, rc.nome_completo) as remetente_nome,
                COALESCE(du.nome, dc.nome_completo) as destinatario_nome
            FROM feedbacks f
            LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
            LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
            LEFT JOIN usuarios du ON f.destinatario_usuario_id = du.id
            LEFT JOIN colaboradores dc ON f.destinatario_colaborador_id = dc.id OR (f.destinatario_usuario_id = du.id AND du.colaborador_id = dc.id)
            WHERE f.id = ?
        ");
        $stmt->execute([$feedback_id]);
        $feedback = $stmt->fetch();
        
        if (!$feedback) {
            return false;
        }
        
        // 1. Cria notificação interna
        criar_notificacao_feedback_recebido(
            $feedback_id,
            $feedback['remetente_usuario_id'],
            $feedback['remetente_colaborador_id'],
            $feedback['destinatario_usuario_id'],
            $feedback['destinatario_colaborador_id'],
            $feedback['anonimo']
        );
        
        // 2. Envia email
        enviar_email_feedback_recebido($feedback_id);
        
        // 3. Envia push notification
        enviar_push_feedback_recebido(
            $feedback_id,
            $feedback['destinatario_usuario_id'],
            $feedback['destinatario_colaborador_id'],
            $feedback['remetente_nome'] ?? 'Alguém',
            $feedback['anonimo']
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao notificar feedback recebido: " . $e->getMessage());
        return false;
    }
}

