<?php
/**
 * API: Iniciar Aula
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
    
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $aula_id = (int)($_POST['aula_id'] ?? 0);
    
    if ($curso_id <= 0 || $aula_id <= 0) {
        throw new Exception('IDs inválidos');
    }
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Verifica se pode acessar o curso
    if (!pode_acessar_curso($colaborador_id, $curso_id)) {
        throw new Exception('Você não tem permissão para acessar este curso');
    }
    
    // Inicia progresso
    $progresso_id = iniciar_progresso_aula($colaborador_id, $curso_id, $aula_id);
    
    // Cria sessão
    $sessao_id = criar_sessao_aula($progresso_id, $colaborador_id, $aula_id, $curso_id);
    
    // Busca dados da aula
    $stmt = $pdo->prepare("SELECT * FROM aulas WHERE id = ?");
    $stmt->execute([$aula_id]);
    $aula = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'progresso_id' => $progresso_id,
        'sessao_id' => $sessao_id,
        'aula' => $aula
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

