<?php
/**
 * API para excluir anotação
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir anotações']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $anotacao_id = (int)($_POST['id'] ?? 0);
    
    if ($anotacao_id <= 0) {
        throw new Exception('ID da anotação inválido');
    }
    
    // Busca anotação
    $stmt = $pdo->prepare("SELECT * FROM anotacoes_sistema WHERE id = ?");
    $stmt->execute([$anotacao_id]);
    $anotacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anotacao) {
        throw new Exception('Anotação não encontrada');
    }
    
    // Verifica permissão (só pode excluir se for ADMIN/RH ou se for o criador)
    if (!has_role(['ADMIN', 'RH']) && $anotacao['usuario_id'] != $usuario['id']) {
        throw new Exception('Você não tem permissão para excluir esta anotação');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Registra no histórico antes de excluir
        $stmt_hist = $pdo->prepare("
            INSERT INTO anotacoes_historico (anotacao_id, usuario_id, acao, dados_anteriores)
            VALUES (?, ?, 'excluida', ?)
        ");
        $stmt_hist->execute([
            $anotacao_id,
            $usuario['id'],
            json_encode([
                'titulo' => $anotacao['titulo'],
                'tipo' => $anotacao['tipo']
            ])
        ]);
        
        // Exclui anotação (cascade vai excluir comentários, visualizações, etc)
        $stmt = $pdo->prepare("DELETE FROM anotacoes_sistema WHERE id = ?");
        $stmt->execute([$anotacao_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Anotação excluída com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

