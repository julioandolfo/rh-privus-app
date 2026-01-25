<?php
/**
 * API para postar no feed
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
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    // Proteção contra requisições duplicadas usando request_id na sessão
    $session_key = 'last_feed_post_request';
    $session_time_key = 'last_feed_post_time';
    
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
        throw new Exception('Requisição duplicada detectada. Post já está sendo processado.');
    }
    
    $conteudo = trim($_POST['conteudo'] ?? '');
    $tipo = $_POST['tipo'] ?? 'texto';
    $tipo_celebração = $_POST['tipo_celebração'] ?? null;
    
    if (empty($conteudo)) {
        throw new Exception('Conteúdo do post é obrigatório');
    }
    
    // Remove tags perigosas mas mantém formatação HTML básica
    $conteudo_limpo = strip_tags($conteudo, '<p><br><strong><b><em><i><u><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre><span><div>');
    
    // Marca esta requisição como processada com timestamp
    if ($request_id) {
        $_SESSION[$session_key] = $request_id;
        $_SESSION[$session_time_key] = time();
    }
    
    // Verifica duplicação: post idêntico nos últimos 30 segundos (aumentado para maior segurança)
    $stmt_check = $pdo->prepare("
        SELECT id FROM feed_posts 
        WHERE usuario_id = ? 
        AND colaborador_id = ?
        AND conteudo = ?
        AND tipo = ?
        AND COALESCE(tipo_celebração, '') = COALESCE(?, '')
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmt_check->execute([$usuario_id, $colaborador_id, $conteudo_limpo, $tipo, $tipo_celebração]);
    if ($stmt_check->fetch()) {
        unset($_SESSION[$session_key]);
        throw new Exception('Post duplicado detectado. Aguarde alguns segundos antes de postar novamente.');
    }
    
    if (!in_array($tipo, ['texto', 'imagem', 'celebração'])) {
        $tipo = 'texto';
    }
    
    // Upload de imagem se houver
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/feed/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception('Formato de imagem não permitido');
        }
        
        $filename = uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filepath)) {
            $imagem = 'uploads/feed/' . $filename;
        }
    }
    
    // Insere post usando transação com lock para garantir atomicidade
    $pdo->beginTransaction();
    try {
        // Usa SELECT FOR UPDATE para lock da linha durante a transação
        // Isso previne inserções simultâneas mesmo em requisições paralelas
        $lock_key = md5($usuario_id . '|' . $colaborador_id . '|' . $conteudo_limpo . '|' . $tipo . '|' . ($tipo_celebração ?? ''));
        
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("
            SELECT id FROM feed_posts 
            WHERE usuario_id = ? 
            AND colaborador_id = ?
            AND conteudo = ?
            AND tipo = ?
            AND COALESCE(tipo_celebração, '') = COALESCE(?, '')
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
            FOR UPDATE
        ");
        $stmt_check2->execute([$usuario_id, $colaborador_id, $conteudo_limpo, $tipo, $tipo_celebração]);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            throw new Exception('Post duplicado detectado. Aguarde alguns segundos antes de postar novamente.');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO feed_posts (usuario_id, colaborador_id, tipo, conteudo, imagem, tipo_celebração, status)
            VALUES (?, ?, ?, ?, ?, ?, 'ativo')
        ");
        $stmt->execute([$usuario_id, $colaborador_id, $tipo, $conteudo_limpo, $imagem, $tipo_celebração]);
        $post_id = $pdo->lastInsertId();
        
        // Adiciona pontos dentro da transação
        require_once __DIR__ . '/../../includes/pontuacao.php';
        $pontos_ganhos = adicionar_pontos('postar_feed', $usuario_id, $colaborador_id, $post_id, 'feed_post');
        
        // Busca quantidade de pontos da ação
        $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'postar_feed' AND ativo = 1");
        $stmt_pontos->execute();
        $config_pontos = $stmt_pontos->fetch();
        $pontos_valor = $config_pontos ? $config_pontos['pontos'] : 20;
        
        // Busca novo total de pontos
        $novos_pontos = obter_pontos($usuario_id, $colaborador_id);
        
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Post publicado com sucesso!',
        'post_id' => $post_id,
        'pontos_ganhos' => $pontos_ganhos ? $pontos_valor : 0,
        'pontos_totais' => $novos_pontos['pontos_totais'] ?? 0
    ]);
    
} catch (Exception $e) {
    // Limpa a flag de requisição em caso de erro
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

