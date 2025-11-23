<?php
/**
 * API para Gestão de Feedbacks (RH/ADMIN)
 * Lista todos os feedbacks do sistema com filtros
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Verifica permissão - apenas ADMIN e RH
if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    
    // Filtros
    $remetente_id = $_GET['remetente_id'] ?? null;
    $destinatario_id = $_GET['destinatario_id'] ?? null;
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    $anonimo = $_GET['anonimo'] ?? null; // 'sim', 'nao', null
    $presencial = $_GET['presencial'] ?? null; // 'sim', 'nao', null
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    // Monta condições WHERE
    $where_conditions = ["f.status = 'ativo'"];
    $params = [];
    
    if ($remetente_id) {
        $where_conditions[] = "(f.remetente_usuario_id = ? OR f.remetente_colaborador_id = ?)";
        $params[] = $remetente_id;
        $params[] = $remetente_id;
    }
    
    if ($destinatario_id) {
        $where_conditions[] = "(f.destinatario_usuario_id = ? OR f.destinatario_colaborador_id = ?)";
        $params[] = $destinatario_id;
        $params[] = $destinatario_id;
    }
    
    if ($data_inicio) {
        $where_conditions[] = "DATE(f.created_at) >= ?";
        $params[] = $data_inicio;
    }
    
    if ($data_fim) {
        $where_conditions[] = "DATE(f.created_at) <= ?";
        $params[] = $data_fim;
    }
    
    if ($anonimo === 'sim') {
        $where_conditions[] = "f.anonimo = 1";
    } elseif ($anonimo === 'nao') {
        $where_conditions[] = "f.anonimo = 0";
    }
    
    if ($presencial === 'sim') {
        $where_conditions[] = "f.presencial = 1";
    } elseif ($presencial === 'nao') {
        $where_conditions[] = "f.presencial = 0";
    }
    
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
    
    // Query para contar total
    $count_sql = "
        SELECT COUNT(DISTINCT f.id) as total
        FROM feedbacks f
        $where_sql
    ";
    
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total = $stmt_count->fetch()['total'];
    
    // Query para buscar feedbacks
    $sql = "
        SELECT 
            f.id,
            f.remetente_usuario_id,
            f.remetente_colaborador_id,
            f.destinatario_usuario_id,
            f.destinatario_colaborador_id,
            f.template_id,
            f.conteudo,
            f.anonimo,
            f.presencial,
            f.anotacoes_internas,
            f.created_at,
            f.updated_at,
            -- Remetente
            COALESCE(ru.nome, rc.nome_completo) as remetente_nome,
            COALESCE(rc.foto, NULL) as remetente_foto,
            ru.email as remetente_email,
            -- Destinatário
            COALESCE(du.nome, dc.nome_completo) as destinatario_nome,
            COALESCE(dc.foto, NULL) as destinatario_foto,
            du.email as destinatario_email
        FROM feedbacks f
        LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
        LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
        LEFT JOIN usuarios du ON f.destinatario_usuario_id = du.id
        LEFT JOIN colaboradores dc ON f.destinatario_colaborador_id = dc.id OR (f.destinatario_usuario_id = du.id AND du.colaborador_id = dc.id)
        $where_sql
        ORDER BY f.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca avaliações e respostas para cada feedback
    foreach ($feedbacks as &$feedback) {
        // Busca avaliações
        $stmt_av = $pdo->prepare("
            SELECT fa.item_id, fa.nota, fi.nome as item_nome
            FROM feedback_avaliacoes fa
            INNER JOIN feedback_itens fi ON fa.item_id = fi.id
            WHERE fa.feedback_id = ?
        ");
        $stmt_av->execute([$feedback['id']]);
        $feedback['avaliacoes'] = $stmt_av->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca respostas
        $stmt_resp = $pdo->prepare("
            SELECT 
                fr.id,
                fr.resposta,
                fr.resposta_pai_id,
                fr.created_at,
                COALESCE(u.nome, c.nome_completo) as autor_nome,
                COALESCE(c.foto, NULL) as autor_foto,
                fr.usuario_id,
                fr.colaborador_id
            FROM feedback_respostas fr
            LEFT JOIN usuarios u ON fr.usuario_id = u.id
            LEFT JOIN colaboradores c ON fr.colaborador_id = c.id OR (fr.usuario_id = u.id AND u.colaborador_id = c.id)
            WHERE fr.feedback_id = ? AND fr.status = 'ativo'
            ORDER BY fr.created_at ASC
        ");
        $stmt_resp->execute([$feedback['id']]);
        $feedback['respostas'] = $stmt_resp->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Busca estatísticas gerais (aplicando os mesmos filtros)
    $stats_sql = "
        SELECT 
            COUNT(*) as total_feedbacks,
            SUM(CASE WHEN anonimo = 1 THEN 1 ELSE 0 END) as total_anonimos,
            SUM(CASE WHEN presencial = 1 THEN 1 ELSE 0 END) as total_presenciais,
            COUNT(DISTINCT CASE WHEN remetente_usuario_id IS NOT NULL THEN remetente_usuario_id END) + 
            COUNT(DISTINCT CASE WHEN remetente_colaborador_id IS NOT NULL THEN remetente_colaborador_id END) as total_remetentes,
            COUNT(DISTINCT CASE WHEN destinatario_usuario_id IS NOT NULL THEN destinatario_usuario_id END) + 
            COUNT(DISTINCT CASE WHEN destinatario_colaborador_id IS NOT NULL THEN destinatario_colaborador_id END) as total_destinatarios
        FROM feedbacks f
        $where_sql
    ";
    
    $stmt_stats = $pdo->prepare($stats_sql);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $feedbacks,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

