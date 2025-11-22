<?php
/**
 * API para listar anotações
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

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar anotações']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $filtro_status = $_GET['status'] ?? 'ativa';
    $filtro_tipo = $_GET['tipo'] ?? null;
    $filtro_prioridade = $_GET['prioridade'] ?? null;
    $filtro_categoria = $_GET['categoria'] ?? null;
    $apenas_minhas = isset($_GET['minhas']) ? (int)$_GET['minhas'] : 0;
    $apenas_fixadas = isset($_GET['fixadas']) ? (int)$_GET['fixadas'] : 0;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    
    $where = [];
    $params = [];
    
    // Filtro de status
    if ($filtro_status === 'todas') {
        // Não filtra por status
    } else {
        $where[] = "a.status = ?";
        $params[] = $filtro_status;
    }
    
    // Filtro de tipo
    if ($filtro_tipo) {
        $where[] = "a.tipo = ?";
        $params[] = $filtro_tipo;
    }
    
    // Filtro de prioridade
    if ($filtro_prioridade) {
        $where[] = "a.prioridade = ?";
        $params[] = $filtro_prioridade;
    }
    
    // Filtro de categoria
    if ($filtro_categoria) {
        $where[] = "a.categoria = ?";
        $params[] = $filtro_categoria;
    }
    
    // Apenas minhas
    if ($apenas_minhas) {
        $where[] = "a.usuario_id = ?";
        $params[] = $usuario['id'];
    }
    
    // Apenas fixadas
    if ($apenas_fixadas) {
        $where[] = "a.fixada = 1";
    }
    
    // Filtros por permissão
    if ($usuario['role'] === 'RH') {
        $where[] = "(a.publico_alvo = 'todos' OR (a.publico_alvo = 'empresa' AND a.empresa_id = ?) OR a.usuario_id = ?)";
        $params[] = $usuario['empresa_id'];
        $params[] = $usuario['id'];
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
        
        $where[] = "(a.publico_alvo = 'todos' OR (a.publico_alvo = 'setor' AND a.setor_id = ?) OR a.usuario_id = ?)";
        $params[] = $setor_id;
        $params[] = $usuario['id'];
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT a.*,
               u.nome as usuario_nome,
               u.foto as usuario_foto,
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo,
               (SELECT COUNT(*) FROM anotacoes_comentarios WHERE anotacao_id = a.id) as total_comentarios,
               (SELECT COUNT(*) FROM anotacoes_visualizacoes WHERE anotacao_id = a.id) as total_visualizacoes
        FROM anotacoes_sistema a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN empresas e ON a.empresa_id = e.id
        LEFT JOIN setores s ON a.setor_id = s.id
        LEFT JOIN cargos car ON a.cargo_id = car.id
        $where_sql
        ORDER BY a.fixada DESC, a.prioridade DESC, a.created_at DESC
        LIMIT ?
    ";
    
    $params[] = $limite;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $anotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processa anotações
    foreach ($anotacoes as &$anotacao) {
        // Decodifica JSONs
        if ($anotacao['tags']) {
            $anotacao['tags'] = json_decode($anotacao['tags'], true) ?: [];
        } else {
            $anotacao['tags'] = [];
        }
        
        if ($anotacao['destinatarios_usuarios']) {
            $anotacao['destinatarios_usuarios'] = json_decode($anotacao['destinatarios_usuarios'], true) ?: [];
        } else {
            $anotacao['destinatarios_usuarios'] = [];
        }
        
        if ($anotacao['destinatarios_colaboradores']) {
            $anotacao['destinatarios_colaboradores'] = json_decode($anotacao['destinatarios_colaboradores'], true) ?: [];
        } else {
            $anotacao['destinatarios_colaboradores'] = [];
        }
        
        if ($anotacao['anexos']) {
            $anotacao['anexos'] = json_decode($anotacao['anexos'], true) ?: [];
        } else {
            $anotacao['anexos'] = [];
        }
        
        // Verifica se foi visualizada pelo usuário atual
        $stmt_viz = $pdo->prepare("
            SELECT COUNT(*) as visualizada
            FROM anotacoes_visualizacoes
            WHERE anotacao_id = ? AND (usuario_id = ? OR colaborador_id = ?)
        ");
        $colaborador_id = $usuario['colaborador_id'] ?? null;
        $stmt_viz->execute([$anotacao['id'], $usuario['id'], $colaborador_id]);
        $viz = $stmt_viz->fetch();
        $anotacao['visualizada'] = $viz['visualizada'] > 0;
        
        // Formata datas
        if ($anotacao['data_notificacao']) {
            $anotacao['data_notificacao_formatada'] = date('d/m/Y H:i', strtotime($anotacao['data_notificacao']));
        }
        if ($anotacao['data_vencimento']) {
            $anotacao['data_vencimento_formatada'] = date('d/m/Y', strtotime($anotacao['data_vencimento']));
        }
    }
    unset($anotacao);
    
    echo json_encode([
        'success' => true,
        'anotacoes' => $anotacoes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar anotações: ' . $e->getMessage()
    ]);
}

