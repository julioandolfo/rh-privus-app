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
    
    // Verifica se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'anotacoes_sistema'");
    if ($stmt_check->rowCount() === 0) {
        throw new Exception('Tabela anotacoes_sistema não existe no banco de dados');
    }
    
    // Verifica se usuário está na sessão
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Usuário não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Valida campos obrigatórios do usuário
    if (!isset($usuario['id']) || !isset($usuario['role'])) {
        throw new Exception('Dados do usuário incompletos');
    }
    
    $filtro_status = $_GET['status'] ?? 'ativa';
    $filtro_tipo = $_GET['tipo'] ?? null;
    $filtro_prioridade = $_GET['prioridade'] ?? null;
    $filtro_categoria = $_GET['categoria'] ?? null;
    $apenas_minhas = isset($_GET['minhas']) ? (int)$_GET['minhas'] : 0;
    $apenas_fixadas = isset($_GET['fixadas']) ? (int)$_GET['fixadas'] : 0;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    
    // Valida limite
    if ($limite < 1 || $limite > 1000) {
        $limite = 50;
    }
    
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
        $empresa_id = $usuario['empresa_id'] ?? null;
        if ($empresa_id) {
            $where[] = "(a.publico_alvo = 'todos' OR (a.publico_alvo = 'empresa' AND a.empresa_id = ?) OR a.usuario_id = ?)";
            $params[] = $empresa_id;
            $params[] = $usuario['id'];
        } else {
            // Se não tem empresa_id, mostra apenas todas ou suas próprias
            $where[] = "(a.publico_alvo = 'todos' OR a.usuario_id = ?)";
            $params[] = $usuario['id'];
        }
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? null;
        
        if ($setor_id) {
            $where[] = "(a.publico_alvo = 'todos' OR (a.publico_alvo = 'setor' AND a.setor_id = ?) OR a.usuario_id = ?)";
            $params[] = $setor_id;
            $params[] = $usuario['id'];
        } else {
            // Se não tem setor_id, mostra apenas todas ou suas próprias
            $where[] = "(a.publico_alvo = 'todos' OR a.usuario_id = ?)";
            $params[] = $usuario['id'];
        }
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
        ORDER BY 
            COALESCE(a.fixada, 0) DESC, 
            CASE 
                WHEN a.prioridade = 'urgente' THEN 4
                WHEN a.prioridade = 'alta' THEN 3
                WHEN a.prioridade = 'media' THEN 2
                WHEN a.prioridade = 'baixa' THEN 1
                ELSE 0
            END DESC,
            a.created_at DESC
        LIMIT ?
    ";
    
    $params[] = $limite;
    
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query SQL: ' . implode(', ', $pdo->errorInfo()));
    }
    
    $executed = $stmt->execute($params);
    if (!$executed) {
        throw new Exception('Erro ao executar query SQL: ' . implode(', ', $stmt->errorInfo()));
    }
    
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
        try {
            $stmt_viz = $pdo->prepare("
                SELECT COUNT(*) as visualizada
                FROM anotacoes_visualizacoes
                WHERE anotacao_id = ? AND (usuario_id = ? OR colaborador_id = ?)
            ");
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            $stmt_viz->execute([$anotacao['id'], $usuario['id'], $colaborador_id]);
            $viz = $stmt_viz->fetch();
            $anotacao['visualizada'] = ($viz && $viz['visualizada'] > 0);
        } catch (Exception $e) {
            // Se houver erro ao verificar visualização, assume como não visualizada
            $anotacao['visualizada'] = false;
            error_log('Erro ao verificar visualização da anotação ' . $anotacao['id'] . ': ' . $e->getMessage());
        }
        
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
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Erro PDO ao listar anotações: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo ?? [], true));
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar anotações. Verifique os logs do servidor.',
        'error' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro ao listar anotações: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar anotações: ' . $e->getMessage(),
        'error' => (defined('DEBUG') && DEBUG) ? [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
}

