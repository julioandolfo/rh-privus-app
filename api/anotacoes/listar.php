<?php
/**
 * API para listar anotações
 */

// Ativa exibição de erros temporariamente para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibe no navegador, apenas nos logs
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    // Carrega includes com tratamento de erro
    if (!file_exists(__DIR__ . '/../../includes/functions.php')) {
        throw new Exception('Arquivo functions.php não encontrado');
    }
    require_once __DIR__ . '/../../includes/functions.php';
    
    if (!file_exists(__DIR__ . '/../../includes/auth.php')) {
        throw new Exception('Arquivo auth.php não encontrado');
    }
    require_once __DIR__ . '/../../includes/auth.php';
    
    if (!file_exists(__DIR__ . '/../../includes/permissions.php')) {
        throw new Exception('Arquivo permissions.php não encontrado');
    }
    require_once __DIR__ . '/../../includes/permissions.php';
    
    require_login();
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro ao carregar includes: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao inicializar sistema: ' . $e->getMessage(),
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}

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
    
    // Verifica se as tabelas necessárias existem
    $tabelas_necessarias = ['anotacoes_sistema', 'usuarios'];
    foreach ($tabelas_necessarias as $tabela) {
        $stmt_check = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt_check->rowCount() === 0) {
            throw new Exception("Tabela $tabela não existe no banco de dados");
        }
    }
    
    // Verifica se tabelas opcionais existem (para evitar erros nas subqueries)
    $tabelas_opcionais = ['anotacoes_comentarios', 'anotacoes_visualizacoes'];
    $tabelas_existentes = [];
    foreach ($tabelas_opcionais as $tabela) {
        $stmt_check = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt_check->rowCount() > 0) {
            $tabelas_existentes[] = $tabela;
        }
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
    
    // Monta subqueries de contagem apenas se as tabelas existirem
    $subquery_comentarios = "0";
    $subquery_visualizacoes = "0";
    
    if (in_array('anotacoes_comentarios', $tabelas_existentes)) {
        $subquery_comentarios = "(SELECT COUNT(*) FROM anotacoes_comentarios WHERE anotacao_id = a.id)";
    }
    
    if (in_array('anotacoes_visualizacoes', $tabelas_existentes)) {
        $subquery_visualizacoes = "(SELECT COUNT(*) FROM anotacoes_visualizacoes WHERE anotacao_id = a.id)";
    }
    
    $sql = "
        SELECT a.*,
               u.nome as usuario_nome,
               u.foto as usuario_foto,
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo,
               $subquery_comentarios as total_comentarios,
               $subquery_visualizacoes as total_visualizacoes
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
            COALESCE(a.created_at, NOW()) DESC
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
        
        // Verifica se foi visualizada pelo usuário atual (apenas se a tabela existir)
        if (in_array('anotacoes_visualizacoes', $tabelas_existentes)) {
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
        } else {
            // Se a tabela não existe, assume como não visualizada
            $anotacao['visualizada'] = false;
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
    $errorInfo = $e->errorInfo ?? [];
    error_log('Erro PDO ao listar anotações: ' . $e->getMessage());
    error_log('SQL Error Code: ' . ($errorInfo[0] ?? 'N/A'));
    error_log('SQL Error: ' . ($errorInfo[2] ?? 'N/A'));
    error_log('SQL Error Info completo: ' . print_r($errorInfo, true));
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar anotações. Verifique os logs do servidor.',
        'error' => [
            'message' => $e->getMessage(),
            'code' => $errorInfo[0] ?? null,
            'sql_error' => $errorInfo[2] ?? null,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    // Captura erros fatais do PHP (PHP 7+)
    http_response_code(500);
    error_log('Erro fatal ao listar anotações: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro fatal ao listar anotações: ' . $e->getMessage(),
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'type' => get_class($e)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro ao listar anotações: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao listar anotações: ' . $e->getMessage(),
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'type' => get_class($e)
        ]
    ]);
}

