<?php
/**
 * Sistema de Notificações Internas
 */

require_once __DIR__ . '/functions.php';

/**
 * Cria uma notificação
 */
function criar_notificacao($usuario_id, $colaborador_id, $tipo, $titulo, $mensagem, $link = null, $referencia_id = null, $referencia_tipo = null) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            INSERT INTO notificacoes_sistema (usuario_id, colaborador_id, tipo, titulo, mensagem, link, referencia_id, referencia_tipo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$usuario_id, $colaborador_id, $tipo, $titulo, $mensagem, $link, $referencia_id, $referencia_tipo]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
}

/**
 * Cria notificação de curtida no feed
 */
function criar_notificacao_feed_curtida($post_id, $usuario_curtiu_id, $colaborador_curtiu_id) {
    try {
        $pdo = getDB();
        
        // Busca autor do post
        $stmt = $pdo->prepare("SELECT usuario_id, colaborador_id FROM feed_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if (!$post) {
            return false;
        }
        
        $autor_usuario_id = $post['usuario_id'];
        $autor_colaborador_id = $post['colaborador_id'];
        
        // Não cria notificação se o autor curtiu seu próprio post
        if (($autor_usuario_id && $autor_usuario_id == $usuario_curtiu_id) ||
            ($autor_colaborador_id && $autor_colaborador_id == $colaborador_curtiu_id)) {
            return false;
        }
        
        // Busca nome de quem curtiu
        $nome_curtiu = 'Alguém';
        if ($usuario_curtiu_id) {
            $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_curtiu_id]);
            $user = $stmt->fetch();
            if ($user) {
                $nome_curtiu = $user['nome'];
            }
        } else if ($colaborador_curtiu_id) {
            $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_curtiu_id]);
            $colab = $stmt->fetch();
            if ($colab) {
                $nome_curtiu = $colab['nome_completo'];
            }
        }
        
        return criar_notificacao(
            $autor_usuario_id,
            $autor_colaborador_id,
            'curtida',
            'Nova curtida',
            "$nome_curtiu curtiu sua publicação",
            '../pages/feed.php#post-' . $post_id,
            $post_id,
            'feed_post'
        );
        
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação de curtida: " . $e->getMessage());
        return false;
    }
}

/**
 * Cria notificação de comentário no feed
 */
function criar_notificacao_feed_comentario($post_id, $comentario_id, $usuario_comentou_id, $colaborador_comentou_id) {
    try {
        $pdo = getDB();
        
        // Busca autor do post
        $stmt = $pdo->prepare("SELECT usuario_id, colaborador_id FROM feed_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if (!$post) {
            return false;
        }
        
        $autor_usuario_id = $post['usuario_id'];
        $autor_colaborador_id = $post['colaborador_id'];
        
        // Não cria notificação se o autor comentou seu próprio post
        if (($autor_usuario_id && $autor_usuario_id == $usuario_comentou_id) ||
            ($autor_colaborador_id && $autor_colaborador_id == $colaborador_comentou_id)) {
            return false;
        }
        
        // Busca nome de quem comentou
        $nome_comentou = 'Alguém';
        if ($usuario_comentou_id) {
            $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_comentou_id]);
            $user = $stmt->fetch();
            if ($user) {
                $nome_comentou = $user['nome'];
            }
        } else if ($colaborador_comentou_id) {
            $stmt = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_comentou_id]);
            $colab = $stmt->fetch();
            if ($colab) {
                $nome_comentou = $colab['nome_completo'];
            }
        }
        
        return criar_notificacao(
            $autor_usuario_id,
            $autor_colaborador_id,
            'comentario',
            'Novo comentário',
            "$nome_comentou comentou sua publicação",
            '../pages/feed.php#post-' . $post_id,
            $post_id,
            'feed_post'
        );
        
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação de comentário: " . $e->getMessage());
        return false;
    }
}

/**
 * Cria notificação de fechamento de pagamento
 */
function criar_notificacao_fechamento_pagamento($colaborador_id, $mes_referencia) {
    try {
        $pdo = getDB();
        
        // Busca usuário do colaborador
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
        $usuario = $stmt->fetch();
        
        $usuario_id = $usuario['id'] ?? null;
        
        return criar_notificacao(
            $usuario_id,
            $colaborador_id,
            'fechamento_pagamento',
            'Fechamento de Pagamento',
            "Seu pagamento de $mes_referencia foi processado",
            '../pages/meus_pagamentos.php',
            null,
            'pagamento'
        );
        
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação de fechamento: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém notificações não lidas
 */
function obter_notificacoes_nao_lidas($usuario_id = null, $colaborador_id = null, $limite = 10) {
    try {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($usuario_id) {
            $where[] = "usuario_id = ?";
            $params[] = $usuario_id;
        } else if ($colaborador_id) {
            $where[] = "colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            return [];
        }
        
        $where_sql = implode(' AND ', $where);
        $limite = (int)$limite; // Garante que é inteiro
        
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes_sistema
            WHERE $where_sql AND lida = 0
            ORDER BY created_at DESC
            LIMIT $limite
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao obter notificações: " . $e->getMessage());
        return [];
    }
}

/**
 * Conta notificações não lidas
 */
function contar_notificacoes_nao_lidas($usuario_id = null, $colaborador_id = null) {
    try {
        $pdo = getDB();
        
        $where = [];
        $params = [];
        
        if ($usuario_id) {
            $where[] = "usuario_id = ?";
            $params[] = $usuario_id;
        } else if ($colaborador_id) {
            $where[] = "colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            return 0;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes_sistema WHERE $where_sql AND lida = 0");
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return intval($result['total'] ?? 0);
        
    } catch (PDOException $e) {
        error_log("Erro ao contar notificações: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marca notificação como lida
 * Verifica se a notificação pertence ao usuário antes de marcar
 */
function marcar_notificacao_lida($notificacao_id, $usuario_id = null, $colaborador_id = null) {
    try {
        $pdo = getDB();
        
        // Se não passou usuario_id/colaborador_id, tenta pegar da sessão
        if ($usuario_id === null && $colaborador_id === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['usuario'])) {
                $usuario = $_SESSION['usuario'];
                $usuario_id = $usuario['id'] ?? null;
                $colaborador_id = $usuario['colaborador_id'] ?? null;
            }
        }
        
        // Primeiro verifica se a notificação existe
        $stmt_check = $pdo->prepare("SELECT id, usuario_id, colaborador_id, lida FROM notificacoes_sistema WHERE id = ?");
        $stmt_check->execute([$notificacao_id]);
        $notif = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$notif) {
            error_log("Notificação $notificacao_id não encontrada no banco");
            return false;
        }
        
        // Verifica se a notificação pertence ao usuário
        $pertence = false;
        if ($usuario_id && $notif['usuario_id'] == $usuario_id) {
            $pertence = true;
        } else if ($colaborador_id && $notif['colaborador_id'] == $colaborador_id) {
            $pertence = true;
        }
        
        if (!$pertence) {
            error_log("Notificação $notificacao_id não pertence ao usuário");
            error_log("Notificação tem - Usuario ID: " . ($notif['usuario_id'] ?? 'NULL') . ", Colaborador ID: " . ($notif['colaborador_id'] ?? 'NULL'));
            error_log("Usuário tem - Usuario ID: " . ($usuario_id ?? 'NULL') . ", Colaborador ID: " . ($colaborador_id ?? 'NULL'));
            return false;
        }
        
        // Se já está marcada como lida, retorna true (idempotente)
        if ($notif['lida'] == 1) {
            error_log("Notificação $notificacao_id já está marcada como lida");
            return true;
        }
        
        // Atualiza a notificação
        $stmt = $pdo->prepare("UPDATE notificacoes_sistema SET lida = 1 WHERE id = ?");
        $stmt->execute([$notificacao_id]);
        
        return $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
        return false;
    }
}

/**
 * Envia notificações de anotação (email e push)
 */
function enviar_notificacoes_anotacao($anotacao_id, $pdo = null) {
    if (!$pdo) {
        $pdo = getDB();
    }
    
    try {
        // Busca anotação
        $stmt = $pdo->prepare("
            SELECT a.*, u.nome as criador_nome
            FROM anotacoes_sistema a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$anotacao_id]);
        $anotacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$anotacao) {
            return false;
        }
        
        // Se notificação já foi enviada, não envia novamente
        if ($anotacao['notificacao_enviada']) {
            return true;
        }
        
        // Busca destinatários
        $destinatarios = [];
        
        // Destinatários específicos
        if ($anotacao['destinatarios_usuarios']) {
            $usuarios_ids = json_decode($anotacao['destinatarios_usuarios'], true);
            if (is_array($usuarios_ids)) {
                foreach ($usuarios_ids as $uid) {
                    $destinatarios[] = ['tipo' => 'usuario', 'id' => $uid];
                }
            }
        }
        
        if ($anotacao['destinatarios_colaboradores']) {
            $colabs_ids = json_decode($anotacao['destinatarios_colaboradores'], true);
            if (is_array($colabs_ids)) {
                foreach ($colabs_ids as $cid) {
                    $destinatarios[] = ['tipo' => 'colaborador', 'id' => $cid];
                }
            }
        }
        
        // Público alvo
        if ($anotacao['publico_alvo'] === 'todos') {
            $stmt = $pdo->query("SELECT id FROM usuarios WHERE role IN ('ADMIN', 'RH', 'GESTOR')");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as $u) {
                $destinatarios[] = ['tipo' => 'usuario', 'id' => $u['id']];
            }
        } elseif ($anotacao['publico_alvo'] === 'empresa' && $anotacao['empresa_id']) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id
                FROM usuarios u
                INNER JOIN colaboradores c ON u.colaborador_id = c.id
                WHERE c.empresa_id = ? AND u.role IN ('ADMIN', 'RH', 'GESTOR')
            ");
            $stmt->execute([$anotacao['empresa_id']]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as $u) {
                $destinatarios[] = ['tipo' => 'usuario', 'id' => $u['id']];
            }
        } elseif ($anotacao['publico_alvo'] === 'setor' && $anotacao['setor_id']) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id
                FROM usuarios u
                INNER JOIN colaboradores c ON u.colaborador_id = c.id
                WHERE c.setor_id = ? AND u.role IN ('ADMIN', 'RH', 'GESTOR')
            ");
            $stmt->execute([$anotacao['setor_id']]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as $u) {
                $destinatarios[] = ['tipo' => 'usuario', 'id' => $u['id']];
            }
        }
        
        // Remove duplicatas
        $destinatarios = array_unique($destinatarios, SORT_REGULAR);
        
        require_once __DIR__ . '/email.php';
        require_once __DIR__ . '/push_notifications.php';
        
        $titulo_notificacao = "Nova Anotação: " . $anotacao['titulo'];
        $mensagem_notificacao = substr(strip_tags($anotacao['conteudo']), 0, 200) . '...';
        $url = get_base_url() . '/pages/dashboard.php#anotacao_' . $anotacao_id;
        
        $enviados_email = 0;
        $enviados_push = 0;
        
        foreach ($destinatarios as $dest) {
            if ($dest['tipo'] === 'usuario') {
                // Busca dados do usuário
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$dest['id']]);
                $usuario_dest = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$usuario_dest) continue;
                
                // Email
                if ($anotacao['notificar_email'] && !empty($usuario_dest['email'])) {
                    $email_html = "
                        <h2>Nova Anotação no Sistema</h2>
                        <p><strong>Título:</strong> " . htmlspecialchars($anotacao['titulo']) . "</p>
                        <p><strong>Criado por:</strong> " . htmlspecialchars($anotacao['criador_nome']) . "</p>
                        <p><strong>Prioridade:</strong> " . ucfirst($anotacao['prioridade']) . "</p>
                        <p><strong>Conteúdo:</strong></p>
                        <div>" . nl2br(htmlspecialchars($anotacao['conteudo'])) . "</div>
                        <p><a href='" . $url . "'>Ver anotação completa</a></p>
                    ";
                    
                    $result = enviar_email(
                        $usuario_dest['email'],
                        $titulo_notificacao,
                        $email_html,
                        ['nome_destinatario' => $usuario_dest['nome']]
                    );
                    
                    if ($result['success']) {
                        $enviados_email++;
                    }
                }
                
                // Push
                if ($anotacao['notificar_push']) {
                    $result = enviar_push_usuario($dest['id'], $titulo_notificacao, $mensagem_notificacao, $url);
                    if ($result['success']) {
                        $enviados_push++;
                    }
                }
                
            } elseif ($dest['tipo'] === 'colaborador') {
                // Busca dados do colaborador
                $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
                $stmt->execute([$dest['id']]);
                $colab_dest = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$colab_dest) continue;
                
                // Busca usuário associado
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE colaborador_id = ?");
                $stmt->execute([$dest['id']]);
                $usuario_dest = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Email
                if ($anotacao['notificar_email'] && !empty($colab_dest['email_pessoal'])) {
                    $email_html = "
                        <h2>Nova Anotação no Sistema</h2>
                        <p><strong>Título:</strong> " . htmlspecialchars($anotacao['titulo']) . "</p>
                        <p><strong>Criado por:</strong> " . htmlspecialchars($anotacao['criador_nome']) . "</p>
                        <p><strong>Prioridade:</strong> " . ucfirst($anotacao['prioridade']) . "</p>
                        <p><strong>Conteúdo:</strong></p>
                        <div>" . nl2br(htmlspecialchars($anotacao['conteudo'])) . "</div>
                        <p><a href='" . $url . "'>Ver anotação completa</a></p>
                    ";
                    
                    $result = enviar_email(
                        $colab_dest['email_pessoal'],
                        $titulo_notificacao,
                        $email_html,
                        ['nome_destinatario' => $colab_dest['nome_completo']]
                    );
                    
                    if ($result['success']) {
                        $enviados_email++;
                    }
                }
                
                // Push
                if ($anotacao['notificar_push'] && $usuario_dest) {
                    $result = enviar_push_usuario($usuario_dest['id'], $titulo_notificacao, $mensagem_notificacao, $url);
                    if ($result['success']) {
                        $enviados_push++;
                    }
                }
            }
        }
        
        // Marca como enviada
        $stmt = $pdo->prepare("
            UPDATE anotacoes_sistema
            SET notificacao_enviada = 1
            WHERE id = ?
        ");
        $stmt->execute([$anotacao_id]);
        
        return [
            'success' => true,
            'enviados_email' => $enviados_email,
            'enviados_push' => $enviados_push
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao enviar notificações de anotação: " . $e->getMessage());
        return false;
    }
}

