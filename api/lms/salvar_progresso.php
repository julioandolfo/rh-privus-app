<?php
/**
 * API: Salvar Progresso da Aula
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lms_functions.php';

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
    $sessao_id = (int)($_POST['sessao_id'] ?? 0);
    $posicao = (int)($_POST['posicao'] ?? 0);
    $percentual = (float)($_POST['percentual'] ?? 0);
    
    if ($progresso_id <= 0) {
        throw new Exception('Progresso inválido');
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
    
    // Atualiza progresso
    if ($sessao_id > 0) {
        atualizar_progresso_aula($progresso_id, $sessao_id);
    } else {
        // Atualização manual
        $stmt = $pdo->prepare("
            UPDATE progresso_colaborador 
            SET ultima_posicao = ?,
                percentual_conclusao = ?,
                data_ultimo_acesso = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $posicao,
            $percentual,
            $progresso_id
        ]);
    }
    
    // Busca progresso atualizado
    $stmt = $pdo->prepare("SELECT * FROM progresso_colaborador WHERE id = ?");
    $stmt->execute([$progresso_id]);
    $progresso_atualizado = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'progresso' => $progresso_atualizado
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

