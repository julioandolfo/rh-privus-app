<?php
/**
 * API para buscar usuários e colaboradores disponíveis como destinatários
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
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $usuarios = [];
    $colaboradores = [];
    
    // Busca usuários
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->query("
            SELECT u.id, u.nome, u.email, u.role,
                   c.nome_completo as colaborador_nome, c.foto as colaborador_foto
            FROM usuarios u
            LEFT JOIN colaboradores c ON u.colaborador_id = c.id
            WHERE u.role IN ('ADMIN', 'RH', 'GESTOR')
            ORDER BY u.nome
        ");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT id, nome_completo, foto, email_pessoal
            FROM colaboradores
            WHERE status = 'ativo'
            ORDER BY nome_completo
        ");
        $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($usuario['role'] === 'RH') {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome, u.email, u.role,
                   c.nome_completo as colaborador_nome, c.foto as colaborador_foto
            FROM usuarios u
            LEFT JOIN colaboradores c ON u.colaborador_id = c.id
            WHERE u.role IN ('ADMIN', 'RH', 'GESTOR')
            AND (u.empresa_id = ? OR c.empresa_id = ?)
            ORDER BY u.nome
        ");
        $stmt->execute([$usuario['empresa_id'], $usuario['empresa_id']]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT id, nome_completo, foto, email_pessoal
            FROM colaboradores
            WHERE empresa_id = ? AND status = 'ativo'
            ORDER BY nome_completo
        ");
        $stmt->execute([$usuario['empresa_id']]);
        $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
        
        if ($setor_id) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.role,
                       c.nome_completo as colaborador_nome, c.foto as colaborador_foto
                FROM usuarios u
                LEFT JOIN colaboradores c ON u.colaborador_id = c.id
                WHERE u.role IN ('ADMIN', 'RH', 'GESTOR')
                AND (u.id = ? OR c.setor_id = ?)
                ORDER BY u.nome
            ");
            $stmt->execute([$usuario['id'], $setor_id]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT id, nome_completo, foto, email_pessoal
                FROM colaboradores
                WHERE setor_id = ? AND status = 'ativo'
                ORDER BY nome_completo
            ");
            $stmt->execute([$setor_id]);
            $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Processa dados
    foreach ($usuarios as &$u) {
        $u['display_name'] = $u['colaborador_nome'] ?: $u['nome'];
        $u['foto'] = $u['colaborador_foto'] ?: null;
    }
    unset($u);
    
    foreach ($colaboradores as &$c) {
        if (empty($c['foto'])) {
            $c['foto'] = null;
        }
    }
    unset($c);
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'colaboradores' => $colaboradores
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar destinatários: ' . $e->getMessage()
    ]);
}

