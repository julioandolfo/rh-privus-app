<?php
/**
 * API: Validar Conclusão de Aula
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lms_functions.php';
require_once __DIR__ . '/../../includes/lms_security.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $progresso_id = (int)($_POST['progresso_id'] ?? 0);
    $aula_id = (int)($_POST['aula_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    
    if ($progresso_id <= 0 || $aula_id <= 0 || $curso_id <= 0) {
        throw new Exception('IDs inválidos');
    }
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Verifica se progresso pertence ao colaborador
    $stmt = $pdo->prepare("
        SELECT * FROM progresso_colaborador 
        WHERE id = ? AND colaborador_id = ?
    ");
    $stmt->execute([$progresso_id, $colaborador_id]);
    $progresso = $stmt->fetch();
    
    if (!$progresso) {
        throw new Exception('Progresso não encontrado');
    }
    
    // Verifica se já está bloqueado
    if ($progresso['bloqueado_por_fraude']) {
        echo json_encode([
            'success' => false,
            'pode_concluir' => false,
            'motivo' => 'Aula bloqueada por suspeita de fraude: ' . ($progresso['motivo_bloqueio'] ?? ''),
            'bloqueado' => true
        ]);
        exit;
    }
    
    // Valida conclusão
    $validacao = validar_conclusao_aula($progresso_id, $colaborador_id, $aula_id, $curso_id);
    
    // Se aprovado, marca como concluído
    if ($validacao['pode_concluir'] && !$validacao['requer_aprovacao']) {
        marcar_aula_concluida($progresso_id, $colaborador_id, $aula_id, $curso_id);
        
        // Verifica se curso está completo
        $curso_completo = verificar_curso_completo($colaborador_id, $curso_id);
        
        echo json_encode([
            'success' => true,
            'pode_concluir' => true,
            'aula_concluida' => true,
            'curso_completo' => $curso_completo,
            'validacao' => $validacao
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'pode_concluir' => false,
            'aula_concluida' => false,
            'motivo' => $validacao['motivo'],
            'requer_aprovacao' => $validacao['requer_aprovacao'],
            'validacao' => $validacao
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

