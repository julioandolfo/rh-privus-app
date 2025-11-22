<?php
/**
 * API para buscar métricas completas de um colaborador
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
    
    $colaborador_id = (int)($_GET['colaborador_id'] ?? 0);
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    
    if ($colaborador_id <= 0) {
        throw new Exception('ID do colaborador inválido');
    }
    
    // Verifica permissão
    if (!can_access_colaborador($colaborador_id)) {
        throw new Exception('Você não tem permissão para visualizar este colaborador');
    }
    
    // Monta condições de data para feedbacks
    $where_fb = [];
    $params_fb = [];
    if ($data_inicio) {
        $where_fb[] = "DATE(f.created_at) >= ?";
        $params_fb[] = $data_inicio;
    }
    if ($data_fim) {
        $where_fb[] = "DATE(f.created_at) <= ?";
        $params_fb[] = $data_fim;
    }
    $where_fb_sql = !empty($where_fb) ? " AND " . implode(" AND ", $where_fb) : "";
    
    // 1. FEEDBACKS ENVIADOS
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM feedbacks f
        WHERE (f.remetente_colaborador_id = ? OR 
               (f.remetente_usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?)))
        AND f.status = 'ativo'
        $where_fb_sql
    ");
    $params_fb_env = array_merge([$colaborador_id, $colaborador_id], $params_fb);
    $stmt->execute($params_fb_env);
    $feedbacks_enviados = $stmt->fetch()['total'];
    
    // 2. FEEDBACKS RECEBIDOS
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM feedbacks f
        WHERE (f.destinatario_colaborador_id = ? OR 
               (f.destinatario_usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?)))
        AND f.status = 'ativo'
        $where_fb_sql
    ");
    $params_fb_rec = array_merge([$colaborador_id, $colaborador_id], $params_fb);
    $stmt->execute($params_fb_rec);
    $feedbacks_recebidos = $stmt->fetch()['total'];
    
    // 3. HUMORES/EMOÇÕES RESPONDIDOS
    $where_emocao = '';
    $params_emocao = [$colaborador_id];
    if ($data_inicio) {
        $where_emocao .= " AND e.data_registro >= ?";
        $params_emocao[] = $data_inicio;
    }
    if ($data_fim) {
        $where_emocao .= " AND e.data_registro <= ?";
        $params_emocao[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               AVG(e.nivel_emocao) as media
        FROM emocoes e
        WHERE (e.colaborador_id = ? OR 
               e.usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?))
        $where_emocao
    ");
    $params_emocao_full = array_merge([$colaborador_id, $colaborador_id], array_slice($params_emocao, 1));
    $stmt->execute($params_emocao_full);
    $humores = $stmt->fetch();
    $humores_respondidos = (int)$humores['total'];
    $media_humor = $humores['media'] ? round($humores['media'], 1) : null;
    
    // 4. REUNIÕES 1:1 COMO COLABORADOR
    $where_1on1_colab = '';
    $params_1on1_colab = [$colaborador_id];
    if ($data_inicio) {
        $where_1on1_colab .= " AND r.data_reuniao >= ?";
        $params_1on1_colab[] = $data_inicio;
    }
    if ($data_fim) {
        $where_1on1_colab .= " AND r.data_reuniao <= ?";
        $params_1on1_colab[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM reunioes_1on1 r
        WHERE r.liderado_id = ?
        $where_1on1_colab
    ");
    $stmt->execute($params_1on1_colab);
    $reunioes_1on1_colaborador = $stmt->fetch()['total'];
    
    // 5. REUNIÕES 1:1 COMO GESTOR
    $where_1on1_gestor = '';
    $params_1on1_gestor = [$colaborador_id];
    if ($data_inicio) {
        $where_1on1_gestor .= " AND r.data_reuniao >= ?";
        $params_1on1_gestor[] = $data_inicio;
    }
    if ($data_fim) {
        $where_1on1_gestor .= " AND r.data_reuniao <= ?";
        $params_1on1_gestor[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM reunioes_1on1 r
        WHERE r.lider_id = ?
        $where_1on1_gestor
    ");
    $stmt->execute($params_1on1_gestor);
    $reunioes_1on1_gestor = $stmt->fetch()['total'];
    
    // 6. CELEBRAÇÕES ENVIADAS
    $where_celebr = ["(c.remetente_id = ? OR c.remetente_usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?))"];
    $params_celebr = [$colaborador_id, $colaborador_id];
    if ($data_inicio) {
        $where_celebr[] = "DATE(c.created_at) >= ?";
        $params_celebr[] = $data_inicio;
    }
    if ($data_fim) {
        $where_celebr[] = "DATE(c.created_at) <= ?";
        $params_celebr[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM celebracoes c
        WHERE " . implode(' AND ', $where_celebr) . "
    ");
    $stmt->execute($params_celebr);
    $celebracoes_enviadas = $stmt->fetch()['total'];
    
    // 7. HISTÓRICO DE HUMORES (para gráfico)
    $where_hist = ["(e.colaborador_id = ? OR e.usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?))"];
    $params_hist = [$colaborador_id, $colaborador_id];
    if ($data_inicio) {
        $where_hist[] = "e.data_registro >= ?";
        $params_hist[] = $data_inicio;
    }
    if ($data_fim) {
        $where_hist[] = "e.data_registro <= ?";
        $params_hist[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT e.data_registro,
               e.nivel_emocao,
               e.descricao,
               e.created_at
        FROM emocoes e
        WHERE " . implode(' AND ', $where_hist) . "
        ORDER BY e.data_registro DESC, e.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params_hist);
    $historico_humores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. FEEDBACKS RECEBIDOS COM DETALHES
    $stmt = $pdo->prepare("
        SELECT f.*,
               COALESCE(ru.nome, rc.nome_completo) as remetente_nome,
               COALESCE(rc.foto, NULL) as remetente_foto,
               COALESCE(ru.role, 'COLABORADOR') as remetente_role
        FROM feedbacks f
        LEFT JOIN usuarios ru ON f.remetente_usuario_id = ru.id
        LEFT JOIN colaboradores rc ON f.remetente_colaborador_id = rc.id OR (f.remetente_usuario_id = ru.id AND ru.colaborador_id = rc.id)
        WHERE (f.destinatario_colaborador_id = ? OR 
               (f.destinatario_usuario_id IN (SELECT id FROM usuarios WHERE colaborador_id = ?)))
        AND f.status = 'ativo'
        $where_fb_sql
        ORDER BY f.created_at DESC
        LIMIT 50
    ");
    $params_fb_det = array_merge([$colaborador_id, $colaborador_id], $params_fb);
    $stmt->execute($params_fb_det);
    $feedbacks_recebidos_detalhes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca avaliações para cada feedback
    foreach ($feedbacks_recebidos_detalhes as &$fb) {
        $stmt_av = $pdo->prepare("
            SELECT fa.item_id, fa.nota, fi.nome as item_nome
            FROM feedback_avaliacoes fa
            INNER JOIN feedback_itens fi ON fa.item_id = fi.id
            WHERE fa.feedback_id = ?
        ");
        $stmt_av->execute([$fb['id']]);
        $fb['avaliacoes'] = $stmt_av->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 9. REUNIÕES 1:1 COM DETALHES
    $where_reun = ["(r.lider_id = ? OR r.liderado_id = ?)"];
    $params_reun = [$colaborador_id, $colaborador_id];
    if ($data_inicio) {
        $where_reun[] = "r.data_reuniao >= ?";
        $params_reun[] = $data_inicio;
    }
    if ($data_fim) {
        $where_reun[] = "r.data_reuniao <= ?";
        $params_reun[] = $data_fim;
    }
    
    $stmt = $pdo->prepare("
        SELECT r.*,
               cl.nome_completo as lider_nome,
               cl.foto as lider_foto,
               cd.nome_completo as liderado_nome,
               cd.foto as liderado_foto
        FROM reunioes_1on1 r
        INNER JOIN colaboradores cl ON r.lider_id = cl.id
        INNER JOIN colaboradores cd ON r.liderado_id = cd.id
        WHERE " . implode(' AND ', $where_reun) . "
        ORDER BY r.data_reuniao DESC
        LIMIT 50
    ");
    $stmt->execute($params_reun);
    $reunioes_detalhes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 10. PDIs
    $stmt = $pdo->prepare("
        SELECT p.*,
               COUNT(DISTINCT po.id) as total_objetivos,
               COUNT(DISTINCT pa.id) as total_acoes
        FROM pdis p
        LEFT JOIN pdi_objetivos po ON p.id = po.pdi_id
        LEFT JOIN pdi_acoes pa ON p.id = pa.pdi_id
        WHERE p.colaborador_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$colaborador_id]);
    $pdis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'metricas' => [
            'feedbacks_enviados' => (int)$feedbacks_enviados,
            'feedbacks_recebidos' => (int)$feedbacks_recebidos,
            'humores_respondidos' => (int)$humores_respondidos,
            'media_humor' => $media_humor,
            'reunioes_1on1_colaborador' => (int)$reunioes_1on1_colaborador,
            'reunioes_1on1_gestor' => (int)$reunioes_1on1_gestor,
            'celebracoes_enviadas' => (int)$celebracoes_enviadas
        ],
        'historico_humores' => $historico_humores,
        'feedbacks_recebidos' => $feedbacks_recebidos_detalhes,
        'reunioes_1on1' => $reunioes_detalhes,
        'pdis' => $pdis
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

