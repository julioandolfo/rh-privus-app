<?php
/**
 * API: Avaliar Entrevista
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

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $entrevista_id = (int)($_POST['entrevista_id'] ?? 0);
    
    if (empty($entrevista_id)) {
        throw new Exception('Entrevista não informada');
    }
    
    // Busca entrevista
    $stmt = $pdo->prepare("SELECT * FROM entrevistas WHERE id = ?");
    $stmt->execute([$entrevista_id]);
    $entrevista = $stmt->fetch();
    
    if (!$entrevista) {
        throw new Exception('Entrevista não encontrada');
    }
    
    // Verifica se é o entrevistador ou tem permissão
    if ($entrevista['entrevistador_id'] != $usuario['id'] && !has_role(['ADMIN', 'RH'])) {
        throw new Exception('Você não tem permissão para avaliar esta entrevista');
    }
    
    // Atualiza avaliação
    $stmt = $pdo->prepare("
        UPDATE entrevistas SET
        nota_entrevistador = ?,
        avaliacao_entrevistador = ?,
        feedback_candidato = ?,
        observacoes = ?,
        status = ?,
        data_realizacao = CASE WHEN ? = 'realizada' AND data_realizacao IS NULL THEN NOW() ELSE data_realizacao END,
        updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        !empty($_POST['nota_entrevistador']) ? (float)$_POST['nota_entrevistador'] : null,
        $_POST['avaliacao_entrevistador'] ?? null,
        $_POST['feedback_candidato'] ?? null,
        $_POST['observacoes'] ?? null,
        $_POST['status'] ?? $entrevista['status'],
        $_POST['status'] ?? $entrevista['status'],
        $entrevista_id
    ]);
    
    // Se entrevista realizada, atualiza etapa da candidatura
    if (($_POST['status'] ?? $entrevista['status']) === 'realizada' && $entrevista['etapa_id']) {
        $stmt = $pdo->prepare("
            UPDATE candidaturas_etapas 
            SET status = 'concluida', 
                data_conclusao = NOW(),
                avaliador_id = ?,
                nota = ?,
                feedback = ?
            WHERE candidatura_id = ? AND etapa_id = ?
        ");
        $stmt->execute([
            $usuario['id'],
            !empty($_POST['nota_entrevistador']) ? (float)$_POST['nota_entrevistador'] : null,
            $_POST['avaliacao_entrevistador'] ?? null,
            $entrevista['candidatura_id'],
            $entrevista['etapa_id']
        ]);
        
        // Recalcula nota geral
        require_once __DIR__ . '/../../../includes/recrutamento_functions.php';
        calcular_nota_geral_candidatura($entrevista['candidatura_id']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Avaliação salva com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

