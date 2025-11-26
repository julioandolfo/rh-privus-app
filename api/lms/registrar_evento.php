<?php
/**
 * API: Registrar Evento do Player
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
    
    $sessao_id = (int)($_POST['sessao_id'] ?? 0);
    $progresso_id = (int)($_POST['progresso_id'] ?? 0);
    $aula_id = (int)($_POST['aula_id'] ?? 0);
    $tipo_evento = $_POST['tipo_evento'] ?? '';
    $posicao_video = (int)($_POST['posicao_video'] ?? 0);
    $dados_adicionais = $_POST['dados_adicionais'] ?? [];
    
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    if (!$colaborador_id) {
        throw new Exception('Colaborador não encontrado');
    }
    
    // Valida tipo de evento
    $tipos_validos = ['play', 'pause', 'seek', 'ended', 'timeupdate', 'focus', 'blur', 'visibilitychange', 'interaction'];
    if (!in_array($tipo_evento, $tipos_validos)) {
        throw new Exception('Tipo de evento inválido');
    }
    
    // Verifica se sessão pertence ao colaborador
    $stmt = $pdo->prepare("
        SELECT * FROM lms_sessoes_aula 
        WHERE id = ? AND colaborador_id = ?
    ");
    $stmt->execute([$sessao_id, $colaborador_id]);
    $sessao = $stmt->fetch();
    
    if (!$sessao) {
        throw new Exception('Sessão não encontrada');
    }
    
    // Registra evento
    $evento_id = registrar_evento_player(
        $sessao_id,
        $progresso_id,
        $colaborador_id,
        $aula_id,
        $tipo_evento,
        $posicao_video,
        $dados_adicionais
    );
    
    // Atualiza progresso
    atualizar_progresso_aula($progresso_id, $sessao_id);
    
    echo json_encode([
        'success' => true,
        'evento_id' => $evento_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

