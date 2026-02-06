<?php
/**
 * API para Listar Solicitações de Feedback
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $tipo = $_GET['tipo'] ?? 'recebidas'; // 'recebidas' ou 'enviadas'
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    if ($tipo === 'recebidas') {
        // Solicitações que o usuário recebeu (onde ele deve enviar feedback)
        $where = [];
        $params = [];
        
        if ($usuario_id) {
            $where[] = "fs.solicitado_usuario_id = ?";
            $params[] = $usuario_id;
        } elseif ($colaborador_id) {
            $where[] = "fs.solicitado_colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            throw new Exception('Usuário não identificado');
        }
        
        $where_sql = implode(' OR ', $where);
        
        $sql = "
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
                COALESCE(sc.foto, NULL) as solicitante_foto,
                COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
                COALESCE(slc.foto, NULL) as solicitado_foto
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
            LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
            WHERE ($where_sql)
            ORDER BY 
                CASE fs.status
                    WHEN 'pendente' THEN 1
                    WHEN 'aceita' THEN 2
                    WHEN 'recusada' THEN 3
                    WHEN 'concluida' THEN 4
                    WHEN 'expirada' THEN 5
                END,
                fs.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        
    } else {
        // Solicitações enviadas pelo usuário (onde ele pediu feedback)
        $where = [];
        $params = [];
        
        if ($usuario_id) {
            $where[] = "fs.solicitante_usuario_id = ?";
            $params[] = $usuario_id;
        } elseif ($colaborador_id) {
            $where[] = "fs.solicitante_colaborador_id = ?";
            $params[] = $colaborador_id;
        } else {
            throw new Exception('Usuário não identificado');
        }
        
        $where_sql = implode(' OR ', $where);
        
        $sql = "
            SELECT 
                fs.*,
                COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
                COALESCE(sc.foto, NULL) as solicitante_foto,
                COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
                COALESCE(slc.foto, NULL) as solicitado_foto
            FROM feedback_solicitacoes fs
            LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
            LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
            LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
            LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
            WHERE ($where_sql)
            ORDER BY 
                CASE fs.status
                    WHEN 'pendente' THEN 1
                    WHEN 'aceita' THEN 2
                    WHEN 'recusada' THEN 3
                    WHEN 'concluida' THEN 4
                    WHEN 'expirada' THEN 5
                END,
                fs.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata datas
    foreach ($solicitacoes as &$sol) {
        $sol['mensagem'] = $sol['mensagem'] ? htmlspecialchars($sol['mensagem']) : null;
        $sol['resposta_mensagem'] = $sol['resposta_mensagem'] ? htmlspecialchars($sol['resposta_mensagem']) : null;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $solicitacoes,
        'page' => $page,
        'per_page' => $per_page
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
