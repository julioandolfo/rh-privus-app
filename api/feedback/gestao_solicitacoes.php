<?php
/**
 * API para Gestão de Solicitações de Feedback (RH/ADMIN)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Verifica permissão - apenas ADMIN e RH
if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    // Filtros
    $solicitante_id = $_GET['solicitante_id'] ?? null;
    $solicitado_id = $_GET['solicitado_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    if ($solicitante_id) {
        $where[] = "fs.solicitante_colaborador_id = ?";
        $params[] = $solicitante_id;
    }
    
    if ($solicitado_id) {
        $where[] = "fs.solicitado_colaborador_id = ?";
        $params[] = $solicitado_id;
    }
    
    if ($status && in_array($status, ['pendente', 'aceita', 'recusada', 'concluida', 'expirada'])) {
        $where[] = "fs.status = ?";
        $params[] = $status;
    }
    
    if ($data_inicio) {
        $where[] = "DATE(fs.created_at) >= ?";
        $params[] = $data_inicio;
    }
    
    if ($data_fim) {
        $where[] = "DATE(fs.created_at) <= ?";
        $params[] = $data_fim;
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Buscar estatísticas
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_solicitacoes,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as total_pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) as total_aceitas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) as total_recusadas,
            SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as total_concluidas,
            COUNT(DISTINCT solicitante_colaborador_id) as total_solicitantes,
            COUNT(DISTINCT solicitado_colaborador_id) as total_solicitados
        FROM feedback_solicitacoes fs
        $where_sql
    ");
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Buscar solicitações
    $sql = "
        SELECT 
            fs.*,
            COALESCE(su.nome, sc.nome_completo) as solicitante_nome,
            COALESCE(sc.foto, NULL) as solicitante_foto,
            COALESCE(sc.email_corporativo, sc.email_pessoal) as solicitante_email,
            COALESCE(slu.nome, slc.nome_completo) as solicitado_nome,
            COALESCE(slc.foto, NULL) as solicitado_foto,
            COALESCE(slc.email_corporativo, slc.email_pessoal) as solicitado_email
        FROM feedback_solicitacoes fs
        LEFT JOIN usuarios su ON fs.solicitante_usuario_id = su.id
        LEFT JOIN colaboradores sc ON fs.solicitante_colaborador_id = sc.id OR (fs.solicitante_usuario_id = su.id AND su.colaborador_id = sc.id)
        LEFT JOIN usuarios slu ON fs.solicitado_usuario_id = slu.id
        LEFT JOIN colaboradores slc ON fs.solicitado_colaborador_id = slc.id OR (fs.solicitado_usuario_id = slu.id AND slu.colaborador_id = slc.id)
        $where_sql
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
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM feedback_solicitacoes fs
        $where_sql
    ");
    $stmt_count->execute(array_slice($params, 0, -2)); // Remove LIMIT e OFFSET
    $total = $stmt_count->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $solicitacoes,
        'stats' => $stats,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
