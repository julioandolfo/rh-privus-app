<?php
/**
 * API para listar posts do feed
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

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Verifica se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'feed_posts'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Tabela feed_posts não existe. Execute a migração migracao_feed.sql primeiro.');
    }
    
    $pagina = intval($_GET['pagina'] ?? 1);
    $limite = intval($_GET['limite'] ?? 20);
    $offset = ($pagina - 1) * $limite;
    
    // Garante valores positivos
    $limite = max(1, min(100, $limite)); // Entre 1 e 100
    $offset = max(0, $offset);
    
    // Busca posts
    // Nota: LIMIT e OFFSET devem ser valores literais, não placeholders preparados
    // Usa subquery para evitar duplicação e problemas com GROUP BY
    $sql = "
        SELECT 
            fp.*,
            COALESCE(
                CASE WHEN fp.usuario_id IS NOT NULL THEN MAX(u.nome) END,
                MAX(c.nome_completo)
            ) as autor_nome,
            COALESCE(
                CASE WHEN fp.usuario_id IS NOT NULL THEN MAX(u.email) END,
                MAX(c.email_pessoal)
            ) as autor_email,
            MAX(c.foto) as autor_foto,
            CASE 
                WHEN fp.usuario_id IS NOT NULL THEN 'usuario'
                ELSE 'colaborador'
            END as autor_tipo
        FROM feed_posts fp
        LEFT JOIN usuarios u ON fp.usuario_id = u.id
        LEFT JOIN colaboradores c ON (
            CASE 
                WHEN fp.colaborador_id IS NOT NULL THEN fp.colaborador_id = c.id
                WHEN fp.usuario_id IS NOT NULL AND u.colaborador_id IS NOT NULL THEN u.colaborador_id = c.id
                ELSE FALSE
            END
        )
        WHERE fp.status = 'ativo'
        GROUP BY fp.id, fp.usuario_id, fp.colaborador_id, fp.tipo, fp.conteudo, fp.imagem, fp.tipo_celebração, fp.status, fp.total_curtidas, fp.total_comentarios, fp.created_at, fp.updated_at
        ORDER BY fp.created_at DESC
        LIMIT " . intval($limite) . " OFFSET " . intval($offset) . "
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    // Para cada post, verifica se o usuário atual curtiu
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    foreach ($posts as &$post) {
        // Conta total de curtidas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM feed_curtidas WHERE post_id = ?");
        $stmt->execute([$post['id']]);
        $curtidas_data = $stmt->fetch();
        $post['total_curtidas'] = (int)($curtidas_data['total'] ?? 0);
        
        // Verifica se curtiu
        if ($usuario_id) {
            $stmt = $pdo->prepare("SELECT id FROM feed_curtidas WHERE post_id = ? AND usuario_id = ?");
            $stmt->execute([$post['id'], $usuario_id]);
        } else if ($colaborador_id) {
            $stmt = $pdo->prepare("SELECT id FROM feed_curtidas WHERE post_id = ? AND colaborador_id = ?");
            $stmt->execute([$post['id'], $colaborador_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM feed_curtidas WHERE 1=0");
            $stmt->execute();
        }
        $post['curtiu'] = $stmt->fetch() ? true : false;
        
        // Conta total de comentários
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM feed_comentarios WHERE post_id = ? AND status = 'ativo'");
        $stmt->execute([$post['id']]);
        $comentarios_data = $stmt->fetch();
        $post['total_comentarios'] = (int)($comentarios_data['total'] ?? 0);
        
        // Busca comentários
        $stmt = $pdo->prepare("
            SELECT 
                fc.*,
                COALESCE(u.nome, c.nome_completo) as autor_nome,
                COALESCE(c.foto, NULL) as autor_foto
            FROM feed_comentarios fc
            LEFT JOIN usuarios u ON fc.usuario_id = u.id
            LEFT JOIN colaboradores c ON fc.colaborador_id = c.id OR (fc.usuario_id = u.id AND u.colaborador_id = c.id)
            WHERE fc.post_id = ? AND fc.status = 'ativo'
            ORDER BY fc.created_at ASC
            LIMIT 10
        ");
        $stmt->execute([$post['id']]);
        $post['comentarios'] = $stmt->fetchAll();
        
        // Busca quem curtiu (até 10 primeiros)
        $stmt = $pdo->prepare("
            SELECT 
                fcur.*,
                COALESCE(u.nome, c.nome_completo) as autor_nome,
                COALESCE(c.foto, NULL) as autor_foto
            FROM feed_curtidas fcur
            LEFT JOIN usuarios u ON fcur.usuario_id = u.id
            LEFT JOIN colaboradores c ON fcur.colaborador_id = c.id OR (fcur.usuario_id = u.id AND u.colaborador_id = c.id)
            WHERE fcur.post_id = ?
            ORDER BY fcur.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$post['id']]);
        $post['curtidas_usuarios'] = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'pagina' => $pagina,
        'limite' => $limite
    ]);
    
} catch (PDOException $e) {
    http_response_code(400);
    error_log("Erro PDO em feed/listar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar posts: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    error_log("Erro em feed/listar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar posts: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

