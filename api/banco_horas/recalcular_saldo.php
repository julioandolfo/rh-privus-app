<?php
/**
 * API: Recalcular Saldo do Banco de Horas
 * Recalcula o saldo baseado em todas as movimentações do histórico
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/banco_horas_functions.php';

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
    echo json_encode(['success' => false, 'error' => 'Sem permissão para recalcular saldo']);
    exit;
}

try {
    $pdo = getDB();
    
    // Lê dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $colaborador_id = (int)($input['colaborador_id'] ?? 0);
    
    if (!$colaborador_id) {
        throw new Exception('ID do colaborador inválido');
    }
    
    // Busca colaborador
    $stmt_colab = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
    $stmt_colab->execute([$colaborador_id]);
    $colaborador = $stmt_colab->fetch();
    
    if (!$colaborador) {
        throw new Exception('Colaborador não encontrado');
    }
    
    $pdo->beginTransaction();
    
    // Busca todas as movimentações ordenadas por data
    $stmt = $pdo->prepare("
        SELECT * FROM banco_horas_movimentacoes 
        WHERE colaborador_id = ? 
        ORDER BY data_movimentacao ASC, created_at ASC, id ASC
    ");
    $stmt->execute([$colaborador_id]);
    $movimentacoes = $stmt->fetchAll();
    
    // Calcula saldo correto
    $saldo_atual = 0;
    $movimentacoes_atualizadas = 0;
    
    foreach ($movimentacoes as $mov) {
        $quantidade = (float)$mov['quantidade_horas'];
        $saldo_anterior = $saldo_atual;
        
        // Aplica movimentação
        if ($mov['tipo'] === 'credito') {
            $saldo_atual += $quantidade;
        } else {
            $saldo_atual -= $quantidade;
        }
        
        $saldo_posterior = $saldo_atual;
        
        // Atualiza movimentação com saldos corretos
        $stmt_update = $pdo->prepare("
            UPDATE banco_horas_movimentacoes 
            SET saldo_anterior = ?, saldo_posterior = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$saldo_anterior, $saldo_posterior, $mov['id']]);
        $movimentacoes_atualizadas++;
    }
    
    // Converte saldo final para horas e minutos
    $horas_inteiras = floor(abs($saldo_atual));
    $minutos = (abs($saldo_atual) - $horas_inteiras) * 60;
    
    // Se negativo, armazena como negativo nas horas
    if ($saldo_atual < 0) {
        $horas_inteiras = -$horas_inteiras;
    }
    
    // Atualiza ou cria registro de saldo
    $stmt_check = $pdo->prepare("SELECT id FROM banco_horas WHERE colaborador_id = ?");
    $stmt_check->execute([$colaborador_id]);
    $saldo_exists = $stmt_check->fetch();
    
    if ($saldo_exists) {
        $stmt_update_saldo = $pdo->prepare("
            UPDATE banco_horas 
            SET saldo_horas = ?, saldo_minutos = ?, ultima_atualizacao = NOW()
            WHERE colaborador_id = ?
        ");
        $stmt_update_saldo->execute([$horas_inteiras, $minutos, $colaborador_id]);
    } else {
        $stmt_insert_saldo = $pdo->prepare("
            INSERT INTO banco_horas (colaborador_id, saldo_horas, saldo_minutos)
            VALUES (?, ?, ?)
        ");
        $stmt_insert_saldo->execute([$colaborador_id, $horas_inteiras, $minutos]);
    }
    
    // Registra log
    $log_msg = sprintf(
        'Saldo recalculado - Colaborador: %s - Movimentações: %d - Saldo final: %.2fh',
        $colaborador['nome_completo'],
        $movimentacoes_atualizadas,
        $saldo_atual
    );
    error_log($log_msg);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Saldo recalculado com sucesso!',
        'dados' => [
            'movimentacoes_atualizadas' => $movimentacoes_atualizadas,
            'saldo_final' => number_format($saldo_atual, 2, ',', '.') . 'h',
            'saldo_horas' => $horas_inteiras,
            'saldo_minutos' => round($minutos)
        ]
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
