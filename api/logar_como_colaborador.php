<?php
/**
 * API para fazer login como colaborador (impersonation)
 * Permite que ADMIN, RH e GESTOR façam login como um colaborador específico
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario_atual = $_SESSION['usuario'];

// Apenas ADMIN, RH e GESTOR podem fazer impersonation
if (!in_array($usuario_atual['role'], ['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para fazer login como colaborador']);
    exit;
}

$colaborador_id = $_POST['colaborador_id'] ?? 0;

if (empty($colaborador_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do colaborador não informado']);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica se o usuário atual tem acesso ao colaborador
    if (!can_access_colaborador($colaborador_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Você não tem acesso a este colaborador']);
        exit;
    }
    
    // Busca dados do colaborador
    $stmt = $pdo->prepare("
        SELECT c.*, u.id as usuario_id, u.role as usuario_role, u.empresa_id as usuario_empresa_id
        FROM colaboradores c
        LEFT JOIN usuarios u ON c.id = u.colaborador_id
        WHERE c.id = ? AND c.status = 'ativo'
    ");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Colaborador não encontrado']);
        exit;
    }
    
    // Salva dados do usuário original para voltar depois
    $usuario_original = [
        'id' => $usuario_atual['id'],
        'nome' => $usuario_atual['nome'],
        'email' => $usuario_atual['email'],
        'role' => $usuario_atual['role'],
        'empresa_id' => $usuario_atual['empresa_id'] ?? null,
        'empresas_ids' => $usuario_atual['empresas_ids'] ?? [],
        'setor_id' => $usuario_atual['setor_id'] ?? null,
        'colaborador_id' => $usuario_atual['colaborador_id'] ?? null
    ];
    
    // Cria sessão como colaborador
    if ($colaborador['usuario_id']) {
        // Se tem usuário vinculado, busca dados completos
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$colaborador['usuario_id']]);
        $usuario_colaborador = $stmt->fetch();
        
        // Busca empresas do usuário
        $stmt_empresas = $pdo->prepare("
            SELECT empresa_id 
            FROM usuarios_empresas 
            WHERE usuario_id = ?
        ");
        $stmt_empresas->execute([$usuario_colaborador['id']]);
        $empresas_ids = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
        
        $_SESSION['usuario'] = [
            'id' => $usuario_colaborador['id'],
            'nome' => $usuario_colaborador['nome'],
            'email' => $usuario_colaborador['email'],
            'role' => $usuario_colaborador['role'],
            'empresa_id' => $usuario_colaborador['empresa_id'],
            'empresas_ids' => $empresas_ids,
            'setor_id' => $usuario_colaborador['setor_id'] ?? null,
            'colaborador_id' => $colaborador['id']
        ];
    } else {
        // Colaborador sem usuário vinculado
        $_SESSION['usuario'] = [
            'id' => null,
            'nome' => $colaborador['nome_completo'],
            'email' => $colaborador['email_pessoal'] ?? '',
            'role' => 'COLABORADOR',
            'empresa_id' => $colaborador['empresa_id'],
            'setor_id' => $colaborador['setor_id'],
            'colaborador_id' => $colaborador['id']
        ];
    }
    
    // Salva dados do usuário original na sessão para poder voltar
    $_SESSION['usuario_original'] = $usuario_original;
    $_SESSION['impersonating'] = true;
    
    // Define timestamp de login
    $_SESSION['login_time'] = time();
    $_SESSION['ultima_atividade'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'redirect' => 'dashboard.php'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao fazer login: ' . $e->getMessage()
    ]);
}
