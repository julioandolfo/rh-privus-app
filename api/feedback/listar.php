<?php
/**
 * API para Listar Feedbacks
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
    
    $tipo = $_GET['tipo'] ?? 'todos'; // 'enviados', 'recebidos', 'todos'
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)20; // Garante que é inteiro
    $offset = (int)(($page - 1) * $limit); // Garante que é inteiro
    
    $remetente_usuario_id = $usuario['id'] ?? null;
    $remetente_colaborador_id = $usuario['colaborador_id'] ?? null;
    
    // Monta query base
    $where_conditions = [];
    $params = [];
    
    if ($tipo === 'enviados') {
        if ($remetente_usuario_id) {
            $where_conditions[] = "f.remetente_usuario_id = ?";
            $params[] = $remetente_usuario_id;
        } elseif ($remetente_colaborador_id) {
            $where_conditions[] = "f.remetente_colaborador_id = ?";
            $params[] = $remetente_colaborador_id;
        } else {
            echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
            exit;
        }
    } elseif ($tipo === 'recebidos') {
        if ($remetente_usuario_id) {
            $where_conditions[] = "f.destinatario_usuario_id = ?";
            $params[] = $remetente_usuario_id;
        } elseif ($remetente_colaborador_id) {
            $where_conditions[] = "f.destinatario_colaborador_id = ?";
            $params[] = $remetente_colaborador_id;
        } else {
            echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
            exit;
        }
    } else {
        // Todos: enviados OU recebidos
        if ($remetente_usuario_id) {
            $where_conditions[] = "(f.remetente_usuario_id = ? OR f.destinatario_usuario_id = ?)";
            $params[] = $remetente_usuario_id;
            $params[] = $remetente_usuario_id;
        } elseif ($remetente_colaborador_id) {
            $where_conditions[] = "(f.remetente_colaborador_id = ? OR f.destinatario_colaborador_id = ?)";
            $params[] = $remetente_colaborador_id;
            $params[] = $remetente_colaborador_id;
        } else {
            echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
            exit;
        }
    }
    
    $where_conditions[] = "f.status = 'ativo'";
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
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
            -- Destinatário
            COALESCE(du.nome, dc.nome_completo) as destinatario_nome,
            COALESCE(dc.foto, NULL) as destinatario_foto,
            -- Verifica se é remetente ou destinatário
            CASE 
                WHEN (f.remetente_usuario_id = ? OR f.remetente_colaborador_id = ?) THEN 'remetente'
                ELSE 'destinatario'
            END as tipo_relacao
        FROM feedbacks f
        LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
        LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
        LEFT JOIN usuarios du ON f.destinatario_usuario_id = du.id
        LEFT JOIN colaboradores dc ON f.destinatario_colaborador_id = dc.id OR (f.destinatario_usuario_id = du.id AND du.colaborador_id = dc.id)
        $where_sql
        ORDER BY f.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $params_query = array_merge($params, [
        $remetente_usuario_id ?? 0,
        $remetente_colaborador_id ?? 0
    ]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_query);
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
        
        // Se for anônimo e usuário não for o remetente, oculta nome do remetente
        if ($feedback['anonimo'] && $feedback['tipo_relacao'] !== 'remetente') {
            $feedback['remetente_nome'] = 'Anônimo';
            $feedback['remetente_foto'] = null;
        }
        
        // Se usuário não for remetente, não mostra anotações internas
        if ($feedback['tipo_relacao'] !== 'remetente') {
            $feedback['anotacoes_internas'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $feedbacks,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

