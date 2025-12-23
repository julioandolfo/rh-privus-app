<?php
/**
 * API para marcar candidato/entrevista como pendente de cadastro de colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = $_POST['candidatura_id'] ?? '';
    $is_entrevista = (bool)($_POST['is_entrevista'] ?? false);
    
    if (empty($candidatura_id)) {
        throw new Exception('ID é obrigatório');
    }
    
    if ($is_entrevista) {
        // É uma entrevista manual
        $entrevista_id = (int)str_replace('entrevista_', '', $candidatura_id);
        
        // Verifica se existe
        $stmt = $pdo->prepare("SELECT id FROM entrevistas WHERE id = ? AND candidatura_id IS NULL");
        $stmt->execute([$entrevista_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Entrevista não encontrada');
        }
        
        // Atualiza para coluna contratado e marca como pendente
        $stmt = $pdo->prepare("
            UPDATE entrevistas SET 
                coluna_kanban = 'contratado',
                status = 'contratado_pendente',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$entrevista_id]);
        
    } else {
        // É uma candidatura normal
        $candidatura_id = (int)$candidatura_id;
        
        // Verifica se existe
        $stmt = $pdo->prepare("SELECT id FROM candidaturas WHERE id = ?");
        $stmt->execute([$candidatura_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Candidatura não encontrada');
        }
        
        // Atualiza para coluna contratado e marca como pendente
        $stmt = $pdo->prepare("
            UPDATE candidaturas SET 
                coluna_kanban = 'contratado',
                status = 'contratado_pendente',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$candidatura_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Marcado como pendente com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

