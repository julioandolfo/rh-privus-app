<?php
/**
 * API para comentar em post
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $post_id = $_POST['post_id'] ?? null;
    $comentario = trim($_POST['comentario'] ?? '');
    $request_id = $_POST['request_id'] ?? null;
    
    if (empty($post_id)) {
        throw new Exception('ID do post é obrigatório');
    }
    
    if (empty($comentario)) {
        throw new Exception('Comentário não pode estar vazio');
    }
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Proteção contra requisições duplicadas usando request_id na sessão
    $session_key = 'last_feed_comment_request';
    $session_time_key = 'last_feed_comment_time';
    
    // Limpa requisições antigas (mais de 5 segundos)
    if (isset($_SESSION[$session_time_key])) {
        $time_diff = time() - $_SESSION[$session_time_key];
        if ($time_diff > 5) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
    }
    
    // Verifica se é requisição duplicada
    if ($request_id && isset($_SESSION[$session_key]) && $_SESSION[$session_key] === $request_id) {
        throw new Exception('Requisição duplicada detectada. Comentário já está sendo processado.');
    }
    
    // Marca esta requisição como processada com timestamp
    if ($request_id) {
        $_SESSION[$session_key] = $request_id;
        $_SESSION[$session_time_key] = time();
    }
    
    // Verifica duplicação: comentário idêntico nos últimos 30 segundos
    $stmt_check = $pdo->prepare("
        SELECT id FROM feed_comentarios 
        WHERE post_id = ?
        AND usuario_id = ? 
        AND colaborador_id = ?
        AND comentario = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmt_check->execute([$post_id, $usuario_id, $colaborador_id, $comentario]);
    if ($stmt_check->fetch()) {
        unset($_SESSION[$session_key]);
        unset($_SESSION[$session_time_key]);
        throw new Exception('Comentário duplicado detectado. Aguarde alguns segundos antes de comentar novamente.');
    }
    
    // Insere comentário usando transação
    $pdo->beginTransaction();
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("
            SELECT id FROM feed_comentarios 
            WHERE post_id = ?
            AND usuario_id = ? 
            AND colaborador_id = ?
            AND comentario = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
            FOR UPDATE
        ");
        $stmt_check2->execute([$post_id, $usuario_id, $colaborador_id, $comentario]);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            throw new Exception('Comentário duplicado detectado. Aguarde alguns segundos antes de comentar novamente.');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO feed_comentarios (post_id, usuario_id, colaborador_id, comentario, status)
            VALUES (?, ?, ?, ?, 'ativo')
        ");
        $stmt->execute([$post_id, $usuario_id, $colaborador_id, $comentario]);
        $comentario_id = $pdo->lastInsertId();
    
        // Adiciona pontos dentro da transação
        require_once __DIR__ . '/../../includes/pontuacao.php';
        $pontos_ganhos = adicionar_pontos('comentar_feed', $usuario_id, $colaborador_id, $comentario_id, 'feed_comentario');
        
        // Busca quantidade de pontos da ação
        $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'comentar_feed' AND ativo = 1");
        $stmt_pontos->execute();
        $config_pontos = $stmt_pontos->fetch();
        $pontos_valor = $config_pontos ? $config_pontos['pontos'] : 5;
        
        // Busca novo total de pontos
        $novos_pontos = obter_pontos($usuario_id, $colaborador_id);
        
        // Atualiza contador de comentários
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM feed_comentarios WHERE post_id = ? AND status = 'ativo'");
        $stmt->execute([$post_id]);
        $total = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("UPDATE feed_posts SET total_comentarios = ? WHERE id = ?");
        $stmt->execute([$total, $post_id]);
        
        // Cria notificação para o autor do post
        require_once __DIR__ . '/../../includes/notificacoes.php';
        criar_notificacao_feed_comentario($post_id, $comentario_id, $usuario_id, $colaborador_id);
        
        $pdo->commit();
        
        // Limpa a flag de requisição após sucesso
        if (isset($session_key)) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        // Limpa a flag de requisição em caso de erro também
        if (isset($session_key)) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
        throw $e;
    }
    
    // Busca dados do comentário criado
    if ($usuario_id) {
        $stmt = $pdo->prepare("
            SELECT fc.*, u.nome as autor_nome, c.foto as autor_foto
            FROM feed_comentarios fc
            LEFT JOIN usuarios u ON fc.usuario_id = u.id
            LEFT JOIN colaboradores c ON u.colaborador_id = c.id OR fc.colaborador_id = c.id
            WHERE fc.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT fc.*, c.nome_completo as autor_nome, c.foto as autor_foto
            FROM feed_comentarios fc
            LEFT JOIN colaboradores c ON fc.colaborador_id = c.id
            WHERE fc.id = ?
        ");
    }
    $stmt->execute([$comentario_id]);
    $comentario_data = $stmt->fetch();
    
    $response = [
        'success' => true,
        'message' => 'Comentário adicionado com sucesso!',
        'comentario' => $comentario_data,
        'total_comentarios' => $total
    ];
    
    // Adiciona info de pontos se ganhou
    if (isset($pontos_ganhos) && $pontos_ganhos) {
        $response['pontos_ganhos'] = $pontos_valor ?? 5;
        $response['pontos_totais'] = $novos_pontos['pontos_totais'] ?? 0;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Limpa a flag de requisição em caso de erro
    $session_key = 'last_feed_comment_request';
    $session_time_key = 'last_feed_comment_time';
    if (isset($session_key) && isset($_SESSION[$session_key])) {
        unset($_SESSION[$session_key]);
    }
    if (isset($session_time_key) && isset($_SESSION[$session_time_key])) {
        unset($_SESSION[$session_time_key]);
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

