<?php
/**
 * API para curtir/descurtir post
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
    
    if (empty($post_id)) {
        throw new Exception('ID do post é obrigatório');
    }
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Verifica se já curtiu
    if ($usuario_id) {
        $stmt = $pdo->prepare("SELECT id FROM feed_curtidas WHERE post_id = ? AND usuario_id = ?");
        $stmt->execute([$post_id, $usuario_id]);
    } else if ($colaborador_id) {
        $stmt = $pdo->prepare("SELECT id FROM feed_curtidas WHERE post_id = ? AND colaborador_id = ?");
        $stmt->execute([$post_id, $colaborador_id]);
    } else {
        throw new Exception('Usuário não identificado');
    }
    
    $curtida_existente = $stmt->fetch();
    
    // Inicia transação
    $pdo->beginTransaction();
    try {
        
        if ($curtida_existente) {
            // Remove curtida
            if ($usuario_id) {
                $stmt = $pdo->prepare("DELETE FROM feed_curtidas WHERE post_id = ? AND usuario_id = ?");
                $stmt->execute([$post_id, $usuario_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM feed_curtidas WHERE post_id = ? AND colaborador_id = ?");
                $stmt->execute([$post_id, $colaborador_id]);
            }
            
            $acao = 'descurtido';
        } else {
            // Adiciona curtida
            $stmt = $pdo->prepare("INSERT INTO feed_curtidas (post_id, usuario_id, colaborador_id) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $usuario_id, $colaborador_id]);
            
            // Adiciona pontos apenas na primeira curtida
            require_once __DIR__ . '/../../includes/pontuacao.php';
            adicionar_pontos('curtir_feed', $usuario_id, $colaborador_id, $post_id, 'feed_post');
            
            // Cria notificação para o autor do post
            require_once __DIR__ . '/../../includes/notificacoes.php';
            criar_notificacao_feed_curtida($post_id, $usuario_id, $colaborador_id);
            
            $acao = 'curtido';
        }
        
        // Atualiza contador de curtidas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM feed_curtidas WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $total = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("UPDATE feed_posts SET total_curtidas = ? WHERE id = ?");
        $stmt->execute([$total, $post_id]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Post $acao com sucesso!",
        'curtido' => !$curtida_existente,
        'total_curtidas' => $total
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

