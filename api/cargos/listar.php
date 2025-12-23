<?php
/**
 * API: Listar Cargos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $setor_id = !empty($_GET['setor_id']) ? (int)$_GET['setor_id'] : null;
    $empresa_id = !empty($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
    
    // Verifica permissão através do setor ou empresa
    if ($setor_id) {
        $stmt = $pdo->prepare("SELECT empresa_id FROM setores WHERE id = ?");
        $stmt->execute([$setor_id]);
        $setor = $stmt->fetch();
        
        if (!$setor) {
            throw new Exception('Setor não encontrado');
        }
        
        $empresa_id = $setor['empresa_id'];
    }
    
    if (!$empresa_id && !$setor_id) {
        throw new Exception('Setor ou empresa não informados');
    }
    
    if ($usuario['role'] === 'RH' && $empresa_id && !can_access_empresa($empresa_id)) {
        throw new Exception('Sem permissão');
    }
    
    // Verifica se a tabela cargos tem coluna setor_id
    $stmt_check = $pdo->query("SHOW COLUMNS FROM cargos LIKE 'setor_id'");
    $has_setor_column = $stmt_check->fetch() !== false;
    
    // Verifica se a tabela cargos tem coluna status
    $stmt_check = $pdo->query("SHOW COLUMNS FROM cargos LIKE 'status'");
    $has_status_column = $stmt_check->fetch() !== false;
    
    // Monta a query baseada na estrutura da tabela
    $where = [];
    $params = [];
    
    if ($has_setor_column && $setor_id) {
        // Se tem setor_id na tabela e foi informado setor, busca por setor
        $where[] = "setor_id = ?";
        $params[] = $setor_id;
    } elseif ($empresa_id) {
        // Senão, busca por empresa
        $where[] = "empresa_id = ?";
        $params[] = $empresa_id;
    }
    
    if ($has_status_column) {
        $where[] = "(status = 'ativo' OR status IS NULL)";
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("SELECT id, nome_cargo FROM cargos $where_sql ORDER BY nome_cargo");
    $stmt->execute($params);
    $cargos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'cargos' => $cargos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

