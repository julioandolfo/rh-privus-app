<?php
/**
 * API: Excluir Vaga
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $vaga_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$vaga_id) {
        throw new Exception('Vaga não informada');
    }
    
    // Busca vaga
    $stmt = $pdo->prepare("SELECT * FROM vagas WHERE id = ?");
    $stmt->execute([$vaga_id]);
    $vaga = $stmt->fetch();
    
    if (!$vaga) {
        throw new Exception('Vaga não encontrada');
    }
    
    // Verifica permissão de empresa
    if (!can_access_empresa($vaga['empresa_id'])) {
        throw new Exception('Você não tem permissão para excluir esta vaga');
    }
    
    // Verifica se há candidaturas aprovadas (opcional - pode querer permitir exclusão mesmo assim)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM candidaturas WHERE vaga_id = ? AND status = 'aprovada'");
    $stmt->execute([$vaga_id]);
    $candidaturas_aprovadas = $stmt->fetchColumn();
    
    // Verifica total de candidaturas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM candidaturas WHERE vaga_id = ?");
    $stmt->execute([$vaga_id]);
    $total_candidaturas = $stmt->fetchColumn();
    
    // Se houver candidaturas aprovadas, pode bloquear ou apenas avisar
    // Por enquanto, vamos apenas avisar mas permitir exclusão
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Exclui vaga (CASCADE vai excluir automaticamente):
        // - candidaturas
        // - vagas_etapas
        // - vagas_landing_pages e componentes
        // - candidaturas_etapas (via candidaturas)
        // - candidaturas_anexos (via candidaturas)
        // - entrevistas (via candidaturas)
        
        $stmt = $pdo->prepare("DELETE FROM vagas WHERE id = ?");
        $stmt->execute([$vaga_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vaga excluída com sucesso',
            'candidaturas_excluidas' => $total_candidaturas
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro PDO ao excluir vaga: ' . $e->getMessage());
    
    $mensagem = $e->getMessage();
    if (strpos($mensagem, 'Lock wait timeout') !== false || strpos($mensagem, '1205') !== false) {
        $mensagem = 'O banco de dados está ocupado. Por favor, aguarde alguns segundos e tente novamente.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir vaga: ' . $mensagem
    ]);
} catch (Exception $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro ao excluir vaga: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

