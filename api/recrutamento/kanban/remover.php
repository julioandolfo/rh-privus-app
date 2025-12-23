<?php
/**
 * API: Remover Candidatura/Entrevista do Kanban
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão. Apenas ADMIN e RH podem remover.']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $id = trim($_POST['id'] ?? '');
    $is_entrevista = !empty($_POST['is_entrevista']) && $_POST['is_entrevista'] === '1';
    $confirmar = !empty($_POST['confirmar']) && $_POST['confirmar'] === '1';
    
    if (empty($id)) {
        throw new Exception('ID é obrigatório');
    }
    
    if (!$confirmar) {
        throw new Exception('Confirmação é obrigatória');
    }
    
    if ($is_entrevista) {
        // É uma entrevista manual
        $entrevista_id = (int)str_replace('entrevista_', '', $id);
        
        // Busca entrevista
        $stmt = $pdo->prepare("
            SELECT e.*, v.empresa_id
            FROM entrevistas e
            LEFT JOIN vagas v ON e.vaga_id_manual = v.id
            WHERE e.id = ? AND e.candidatura_id IS NULL
        ");
        $stmt->execute([$entrevista_id]);
        $entrevista = $stmt->fetch();
        
        if (!$entrevista) {
            throw new Exception('Entrevista não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && $entrevista['empresa_id'] && !can_access_empresa($entrevista['empresa_id'])) {
            throw new Exception('Você não tem permissão para remover esta entrevista');
        }
        
        // Remove onboarding associado (se existir)
        $stmt = $pdo->prepare("DELETE FROM onboarding WHERE entrevista_id = ?");
        $stmt->execute([$entrevista_id]);
        
        // Remove entrevista
        $stmt = $pdo->prepare("DELETE FROM entrevistas WHERE id = ? AND candidatura_id IS NULL");
        $stmt->execute([$entrevista_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Entrevista removida com sucesso'
        ]);
        
    } else {
        // É uma candidatura normal
        $candidatura_id = (int)$id;
        
        // Busca candidatura
        $stmt = $pdo->prepare("
            SELECT c.*, v.empresa_id
            FROM candidaturas c
            INNER JOIN vagas v ON c.vaga_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$candidatura_id]);
        $candidatura = $stmt->fetch();
        
        if (!$candidatura) {
            throw new Exception('Candidatura não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
            throw new Exception('Você não tem permissão para remover esta candidatura');
        }
        
        // Remove onboarding associado (se existir)
        $stmt = $pdo->prepare("DELETE FROM onboarding WHERE candidatura_id = ?");
        $stmt->execute([$candidatura_id]);
        
        // Remove entrevistas associadas
        $stmt = $pdo->prepare("DELETE FROM entrevistas WHERE candidatura_id = ?");
        $stmt->execute([$candidatura_id]);
        
        // Remove histórico
        $stmt = $pdo->prepare("DELETE FROM candidaturas_historico WHERE candidatura_id = ?");
        $stmt->execute([$candidatura_id]);
        
        // Remove candidatura
        $stmt = $pdo->prepare("DELETE FROM candidaturas WHERE id = ?");
        $stmt->execute([$candidatura_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Candidatura removida com sucesso'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

