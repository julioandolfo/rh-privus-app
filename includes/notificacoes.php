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

