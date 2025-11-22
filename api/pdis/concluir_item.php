<?php
/**
 * API para Marcar Objetivo ou Ação do PDI como Concluído
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

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $tipo = $_POST['tipo'] ?? ''; // 'objetivo' ou 'acao'
    $item_id = (int)($_POST['item_id'] ?? 0);
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    if (!in_array($tipo, ['objetivo', 'acao'])) {
        throw new Exception('Tipo inválido');
    }
    
    if ($item_id <= 0) {
        throw new Exception('ID do item inválido');
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($tipo === 'objetivo') {
            // Busca objetivo e PDI
            $stmt = $pdo->prepare("
                SELECT po.*, p.colaborador_id, p.status as pdi_status
                FROM pdi_objetivos po
                INNER JOIN pdis p ON po.pdi_id = p.id
                WHERE po.id = ?
            ");
            $stmt->execute([$item_id]);
            $objetivo = $stmt->fetch();
            
            if (!$objetivo) {
                throw new Exception('Objetivo não encontrado');
            }
            
            // Verifica permissão (colaborador do PDI ou RH/ADMIN)
            $pode_concluir = false;
            if ($usuario['colaborador_id'] == $objetivo['colaborador_id'] ||
                $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
                $pode_concluir = true;
            }
            
            if (!$pode_concluir) {
                throw new Exception('Sem permissão para concluir este objetivo');
            }
            
            // Atualiza objetivo
            $stmt = $pdo->prepare("
                UPDATE pdi_objetivos 
                SET status = 'concluido', 
                    data_conclusao = CURDATE(),
                    observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$observacoes, $item_id]);
            
            $pdi_id = $objetivo['pdi_id'];
            
        } else {
            // Busca ação e PDI
            $stmt = $pdo->prepare("
                SELECT pa.*, p.colaborador_id, p.status as pdi_status
                FROM pdi_acoes pa
                INNER JOIN pdis p ON pa.pdi_id = p.id
                WHERE pa.id = ?
            ");
            $stmt->execute([$item_id]);
            $acao = $stmt->fetch();
            
            if (!$acao) {
                throw new Exception('Ação não encontrada');
            }
            
            // Verifica permissão
            $pode_concluir = false;
            if ($usuario['colaborador_id'] == $acao['colaborador_id'] ||
                $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
                $pode_concluir = true;
            }
            
            if (!$pode_concluir) {
                throw new Exception('Sem permissão para concluir esta ação');
            }
            
            // Atualiza ação
            $stmt = $pdo->prepare("
                UPDATE pdi_acoes 
                SET status = 'concluido', 
                    data_conclusao = CURDATE(),
                    observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$observacoes, $item_id]);
            
            $pdi_id = $acao['pdi_id'];
        }
        
        // Recalcula progresso do PDI
        calcular_progresso_pdi($pdi_id);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($tipo) . ' marcado como concluído!'
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

