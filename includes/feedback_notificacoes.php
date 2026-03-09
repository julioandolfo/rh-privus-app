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
        
        $titulo = 'Novo Feedback Recebido 💬';
        $mensagem = $anonimo ? 'Você recebeu um feedback anônimo' : "$remetente_nome enviou um feedback para você";
        $url = get_base_url() . '/pages/feedback_meus.php?tipo=recebidos';
        
        if ($destinatario_usuario_id) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_usuario(
                $destinatario_usuario_id,
                $titulo,
                $mensagem,
                $url,
                'feedback',
                $feedback_id,
                'feedback'
            );
        } elseif ($destinatario_colaborador_id) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_colaborador(
                $destinatario_colaborador_id,
                $titulo,
                $mensagem,
                $url,
                'feedback',
                $feedback_id,
                'feedback'
            );
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

/**
 * Notifica sobre solicitação de feedback recebida
 */
function notificar_solicitacao_feedback($solicitacao_id) {
    try {
        $pdo = getDB();
        
        // Busca dados da solicitação
        $stmt = $pdo->prepare("
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
                COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
                COALESCE(slu.email, slc.email_pessoal) as solicitado_email
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
            LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
            WHERE fs.id = ?
        ");
        $stmt->execute([$solicitacao_id]);
        $solicitacao = $stmt->fetch();
        
        if (!$solicitacao) {
            return false;
        }
        
        // 1. Cria notificação interna
        $titulo = 'Nova Solicitação de Feedback';
        $mensagem = $solicitacao['solicitante_nome'] . ' está pedindo que você envie um feedback sobre ele(a)';
        $link = '../pages/feedback_solicitacoes.php?tipo=recebidas';
        
        criar_notificacao(
            $solicitacao['solicitado_usuario_id'],
            $solicitacao['solicitado_colaborador_id'],
            'feedback_solicitacao',
            $titulo,
            $mensagem,
            $link,
            $solicitacao_id,
            'feedback_solicitacao'
        );
        
        // 2. Envia email
        enviar_email_solicitacao_feedback($solicitacao_id);
        
        // 3. Envia push notification
        enviar_push_solicitacao_feedback($solicitacao_id);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao notificar solicitação de feedback: " . $e->getMessage());
        return false;
    }
}

/**
 * Envia email de solicitação de feedback
 */
function enviar_email_solicitacao_feedback($solicitacao_id) {
    try {
        $pdo = getDB();
        
        // Busca dados da solicitação
        $stmt = $pdo->prepare("
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
                COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
                COALESCE(slu.email, slc.email_pessoal) as solicitado_email
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
            LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
            WHERE fs.id = ?
        ");
        $stmt->execute([$solicitacao_id]);
        $solicitacao = $stmt->fetch();
        
        if (!$solicitacao || empty($solicitacao['solicitado_email'])) {
            return ['success' => false, 'message' => 'Solicitação não encontrada ou solicitado sem email.'];
        }
        
        $baseUrl = get_base_url();
        $link_solicitacao = $baseUrl . '/pages/feedback_solicitacoes.php?tipo=recebidas';
        
        // Tenta usar template de email se existir
        $template = buscar_template_email('solicitacao_feedback');
        
        if ($template) {
            $variaveis = [
                'nome_completo' => $solicitacao['solicitado_nome'],
                'solicitante_nome' => $solicitacao['solicitante_nome'],
                'mensagem' => $solicitacao['mensagem'] ? nl2br(htmlspecialchars($solicitacao['mensagem'])) : '<p style="color: #999; font-style: italic;">Nenhuma mensagem adicional.</p>',
                'prazo' => $solicitacao['prazo'] ? date('d/m/Y', strtotime($solicitacao['prazo'])) : 'Sem prazo definido',
                'link_solicitacao' => $link_solicitacao
            ];
            
            return enviar_email_template('solicitacao_feedback', $solicitacao['solicitado_email'], $variaveis);
        } else {
            // Email padrão se não houver template
            $assunto = 'Solicitação de Feedback - RH Privus';
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
                            <p>Olá, <strong>{$solicitacao['solicitado_nome']}</strong>!</p>
                            <p><strong>{$solicitacao['solicitante_nome']}</strong> está solicitando que você envie um feedback sobre ele(a).</p>
                            " . ($solicitacao['mensagem'] ? "
                            <div style='background-color: white; padding: 15px; border-left: 4px solid #009ef7; margin: 15px 0;'>
                                <strong>Mensagem:</strong><br>
                                " . nl2br(htmlspecialchars($solicitacao['mensagem'])) . "
                            </div>
                            " : "") . "
                            " . ($solicitacao['prazo'] ? "<p><strong>Prazo sugerido:</strong> " . date('d/m/Y', strtotime($solicitacao['prazo'])) . "</p>" : "") . "
                            <p style='text-align: center;'>
                                <a href='{$link_solicitacao}' class='button'>Ver Solicitação</a>
                            </p>
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
            return enviar_email($solicitacao['solicitado_email'], $assunto, $mensagem_html, [
                'nome_destinatario' => $solicitacao['solicitado_nome']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email de solicitação de feedback: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Envia push de solicitação de feedback
 */
function enviar_push_solicitacao_feedback($solicitacao_id) {
    try {
        $pdo = getDB();
        
        // Busca dados da solicitação
        $stmt = $pdo->prepare("
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            WHERE fs.id = ?
        ");
        $stmt->execute([$solicitacao_id]);
        $solicitacao = $stmt->fetch();
        
        if (!$solicitacao) {
            return ['success' => false, 'message' => 'Solicitação não encontrada'];
        }
        
        // Verifica preferência de notificação push
        require_once __DIR__ . '/push_preferences.php';
        
        if (!verificar_preferencia_push($solicitacao['solicitado_usuario_id'], $solicitacao['solicitado_colaborador_id'], 'feedback_solicitacao')) {
            return [
                'success' => true, 
                'enviadas' => 0,
                'message' => 'Notificação push desativada pelo usuário'
            ];
        }
        
        $titulo = 'Nova Solicitação de Feedback 💭';
        $mensagem = $solicitacao['solicitante_nome'] . ' está pedindo que você envie um feedback';
        $url = get_base_url() . '/pages/feedback_solicitacoes.php?tipo=recebidas';
        
        if ($solicitacao['solicitado_usuario_id']) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_usuario(
                $solicitacao['solicitado_usuario_id'],
                $titulo,
                $mensagem,
                $url,
                'feedback',
                $solicitacao_id,
                'feedback_solicitacao'
            );
        } elseif ($solicitacao['solicitado_colaborador_id']) {
            require_once __DIR__ . '/push_notifications.php';
            return enviar_push_colaborador(
                $solicitacao['solicitado_colaborador_id'],
                $titulo,
                $mensagem,
                $url,
                'feedback',
                $solicitacao_id,
                'feedback_solicitacao'
            );
        }
        
        return ['success' => false, 'message' => 'Solicitado não identificado'];
        
    } catch (Exception $e) {
        error_log("Erro ao enviar push de solicitação de feedback: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Notifica sobre resposta de solicitação (aceita/recusada)
 */
function notificar_resposta_solicitacao($solicitacao_id, $acao) {
    try {
        $pdo = getDB();
        
        // Busca dados da solicitação
        $stmt = $pdo->prepare("
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
                COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
                COALESCE(su.email, sc.email_pessoal) as solicitante_email
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
            LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
            WHERE fs.id = ?
        ");
        $stmt->execute([$solicitacao_id]);
        $solicitacao = $stmt->fetch();
        
        if (!$solicitacao) {
            return false;
        }
        
        // 1. Cria notificação interna
        $titulo = $acao === 'aceitar' ? 'Solicitação de Feedback Aceita' : 'Solicitação de Feedback Recusada';
        $mensagem = $solicitacao['solicitado_nome'] . ($acao === 'aceitar' ? ' aceitou sua solicitação de feedback' : ' recusou sua solicitação de feedback');
        $link = '../pages/feedback_solicitacoes.php?tipo=enviadas';
        
        criar_notificacao(
            $solicitacao['solicitante_usuario_id'],
            $solicitacao['solicitante_colaborador_id'],
            'feedback_solicitacao_resposta',
            $titulo,
            $mensagem,
            $link,
            $solicitacao_id,
            'feedback_solicitacao'
        );
        
        // 2. Envia push notification
        $emoji = $acao === 'aceitar' ? '✅' : '❌';
        $titulo_push = $acao === 'aceitar' ? 'Solicitação Aceita! ' . $emoji : 'Solicitação Recusada ' . $emoji;
        $mensagem_push = $mensagem;
        $link_push = get_base_url() . '/pages/feedback_solicitacoes.php?tipo=enviadas';
        
        if ($solicitacao['solicitante_usuario_id']) {
            require_once __DIR__ . '/push_notifications.php';
            enviar_push_usuario(
                $solicitacao['solicitante_usuario_id'],
                $titulo_push,
                $mensagem_push,
                $link_push,
                'feedback',
                $solicitacao_id,
                'feedback_resposta'
            );
        } elseif ($solicitacao['solicitante_colaborador_id']) {
            require_once __DIR__ . '/push_notifications.php';
            enviar_push_colaborador(
                $solicitacao['solicitante_colaborador_id'],
                $titulo_push,
                $mensagem_push,
                $link_push,
                'feedback',
                $solicitacao_id,
                'feedback_resposta'
            );
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao notificar resposta de solicitação: " . $e->getMessage());
        return false;
    }
}

