<?php
/**
 * API: Deletar Movimentação do Banco de Horas
 * Permite deletar uma movimentação incorreta do histórico
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verifica autenticação
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Verifica permissão (apenas RH e ADMIN)
if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para deletar movimentações']);
    exit;
}

try {
    $pdo = getDB();
    
    // Lê dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $movimentacao_id = (int)($input['movimentacao_id'] ?? 0);
    $colaborador_id = (int)($input['colaborador_id'] ?? 0);
    
    if (!$movimentacao_id || !$colaborador_id) {
        throw new Exception('Dados inválidos');
    }
    
    // Busca a movimentação
    $stmt = $pdo->prepare("
        SELECT m.*, c.nome_completo
        FROM banco_horas_movimentacoes m
        INNER JOIN colaboradores c ON m.colaborador_id = c.id
        WHERE m.id = ? AND m.colaborador_id = ?
    ");
    $stmt->execute([$movimentacao_id, $colaborador_id]);
    $movimentacao = $stmt->fetch();
    
    if (!$movimentacao) {
        throw new Exception('Movimentação não encontrada');
    }
    
    $pdo->beginTransaction();
    
    // Verifica se há referências em horas_extras
    $stmt_ref = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM horas_extras 
        WHERE banco_horas_movimentacao_id = ?
    ");
    $stmt_ref->execute([$movimentacao_id]);
    $ref_count = $stmt_ref->fetch()['total'];
    
    if ($ref_count > 0) {
        // Remove a referência antes de deletar
        $stmt_update = $pdo->prepare("
            UPDATE horas_extras 
            SET banco_horas_movimentacao_id = NULL 
            WHERE banco_horas_movimentacao_id = ?
        ");
        $stmt_update->execute([$movimentacao_id]);
    }
    
    // Verifica se há referências em ocorrencias
    $stmt_ref_oc = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM ocorrencias 
        WHERE banco_horas_movimentacao_id = ?
    ");
    $stmt_ref_oc->execute([$movimentacao_id]);
    $ref_oc_count = $stmt_ref_oc->fetch()['total'];
    
    if ($ref_oc_count > 0) {
        // Remove a referência antes de deletar
        $stmt_update_oc = $pdo->prepare("
            UPDATE ocorrencias 
            SET banco_horas_movimentacao_id = NULL 
            WHERE banco_horas_movimentacao_id = ?
        ");
        $stmt_update_oc->execute([$movimentacao_id]);
    }
    
    // Deleta a movimentação
    $stmt_delete = $pdo->prepare("DELETE FROM banco_horas_movimentacoes WHERE id = ?");
    $stmt_delete->execute([$movimentacao_id]);
    
    // Registra log
    $log_msg = sprintf(
        'Movimentação deletada: %s - %s (%s) - %.2fh - Colaborador: %s',
        $movimentacao['tipo'],
        $movimentacao['origem'],
        date('d/m/Y', strtotime($movimentacao['data_movimentacao'])),
        $movimentacao['quantidade_horas'],
        $movimentacao['nome_completo']
    );
    error_log($log_msg);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Movimentação excluída com sucesso!',
        'aviso' => 'Não esqueça de recalcular o saldo do banco de horas!'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
