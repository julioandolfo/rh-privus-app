<?php
/**
 * API para buscar detalhes de uma anotação específica
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
    
    $anotacao_id = (int)($_GET['id'] ?? 0);
    
    if ($anotacao_id <= 0) {
        throw new Exception('ID da anotação inválido');
    }
    
    $stmt = $pdo->prepare("
        SELECT a.*,
               u.nome as usuario_nome,
               u.foto as usuario_foto,
               e.nome_fantasia as empresa_nome,
               s.nome_setor,
               car.nome_cargo
        FROM anotacoes_sistema a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN empresas e ON a.empresa_id = e.id
        LEFT JOIN setores s ON a.setor_id = s.id
        LEFT JOIN cargos car ON a.cargo_id = car.id
        WHERE a.id = ?
    ");
    $stmt->execute([$anotacao_id]);
    $anotacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anotacao) {
        throw new Exception('Anotação não encontrada');
    }
    
    // Verifica permissão
    if ($usuario['role'] === 'RH') {
        if ($anotacao['publico_alvo'] !== 'todos' && 
            !($anotacao['publico_alvo'] === 'empresa' && $anotacao['empresa_id'] == $usuario['empresa_id']) &&
            $anotacao['usuario_id'] != $usuario['id']) {
            throw new Exception('Você não tem permissão para visualizar esta anotação');
        }
    } elseif ($usuario['role'] === 'GESTOR') {
        $stmt_setor = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt_setor->execute([$usuario['id']]);
        $user_data = $stmt_setor->fetch();
        $setor_id = $user_data['setor_id'] ?? 0;
        
        if ($anotacao['publico_alvo'] !== 'todos' && 
            !($anotacao['publico_alvo'] === 'setor' && $anotacao['setor_id'] == $setor_id) &&
            $anotacao['usuario_id'] != $usuario['id']) {
            throw new Exception('Você não tem permissão para visualizar esta anotação');
        }
    }
    
    // Processa JSONs
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
    
    // Processa múltiplos IDs se existirem
    if (isset($anotacao['empresas_ids']) && !empty($anotacao['empresas_ids'])) {
        $anotacao['empresas_ids'] = json_decode($anotacao['empresas_ids'], true) ?: [];
    } else {
        $anotacao['empresas_ids'] = [];
    }
    
    if (isset($anotacao['setores_ids']) && !empty($anotacao['setores_ids'])) {
        $anotacao['setores_ids'] = json_decode($anotacao['setores_ids'], true) ?: [];
    } else {
        $anotacao['setores_ids'] = [];
    }
    
    if (isset($anotacao['cargos_ids']) && !empty($anotacao['cargos_ids'])) {
        $anotacao['cargos_ids'] = json_decode($anotacao['cargos_ids'], true) ?: [];
    } else {
        $anotacao['cargos_ids'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'anotacao' => $anotacao
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

