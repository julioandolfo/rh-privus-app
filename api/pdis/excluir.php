<?php
/**
 * API para Excluir PDI (Plano de Desenvolvimento Individual)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/engajamento.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verifica se módulo está ativo
if (!engajamento_modulo_ativo('pdis')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Módulo de PDIs está desativado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $data = json_decode(file_get_contents('php://input'), true);
    $pdi_id = (int)($data['pdi_id'] ?? 0);
    
    if ($pdi_id <= 0) {
        throw new Exception('ID do PDI inválido');
    }
    
    // Verifica se PDI existe e se usuário tem permissão para excluir
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome_completo as colaborador_nome
        FROM pdis p
        INNER JOIN colaboradores c ON p.colaborador_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pdi_id]);
    $pdi = $stmt->fetch();
    
    if (!$pdi) {
        throw new Exception('PDI não encontrado');
    }
    
    // Verifica permissão
    $pode_excluir = false;
    if ($usuario['id'] == $pdi['criado_por'] ||
        $usuario['role'] === 'ADMIN' || 
        $usuario['role'] === 'RH') {
        $pode_excluir = true;
    }
    
    if (!$pode_excluir) {
        throw new Exception('Você não tem permissão para excluir este PDI');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Exclui objetivos
        $stmt = $pdo->prepare("DELETE FROM pdi_objetivos WHERE pdi_id = ?");
        $stmt->execute([$pdi_id]);
        
        // Exclui ações
        $stmt = $pdo->prepare("DELETE FROM pdi_acoes WHERE pdi_id = ?");
        $stmt->execute([$pdi_id]);
        
        // Exclui PDI
        $stmt = $pdo->prepare("DELETE FROM pdis WHERE id = ?");
        $stmt->execute([$pdi_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'PDI excluído com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

