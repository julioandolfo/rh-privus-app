<?php
/**
 * API: Deletar Ocorrência
 */

// Desabilita exibição de erros para não quebrar o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inicia output buffering para capturar qualquer output indesejado
ob_start();

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/permissions.php';
    
    require_login();
    
    // Apenas ADMIN e RH podem deletar
    if (!has_role(['ADMIN', 'RH'])) {
        throw new Exception('Você não tem permissão para deletar ocorrências.');
    }
    
    // Carrega banco_horas_functions apenas se necessário
    require_once __DIR__ . '/../../includes/banco_horas_functions.php';
    
    // Lê dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    $ocorrencia_id = $data['ocorrencia_id'] ?? null;
    
    if (empty($ocorrencia_id)) {
        throw new Exception('ID da ocorrência não informado.');
    }
    
    $ocorrencia_id = (int)$ocorrencia_id;
    
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Busca dados da ocorrência antes de deletar
    $stmt = $pdo->prepare("
        SELECT o.*, t.codigo as tipo_codigo
        FROM ocorrencias o
        LEFT JOIN tipos_ocorrencias t ON o.tipo_ocorrencia_id = t.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ocorrencia_id]);
    $ocorrencia = $stmt->fetch();
    
    if (!$ocorrencia) {
        throw new Exception('Ocorrência não encontrada.');
    }
    
    // Verifica permissão para acessar o colaborador
    if (!can_access_colaborador($ocorrencia['colaborador_id'])) {
        throw new Exception('Você não tem permissão para deletar esta ocorrência.');
    }
    
    // Se tinha desconto no banco de horas, reverte a movimentação
    if (!empty($ocorrencia['banco_horas_movimentacao_id'])) {
        // Busca a movimentação
        $stmt_mov = $pdo->prepare("SELECT * FROM banco_horas_movimentacoes WHERE id = ?");
        $stmt_mov->execute([$ocorrencia['banco_horas_movimentacao_id']]);
        $movimentacao = $stmt_mov->fetch();
        
        if ($movimentacao) {
            // Reverte o desconto (adiciona as horas de volta)
            $stmt_reverter = $pdo->prepare("
                UPDATE banco_horas 
                SET saldo_total_horas = saldo_total_horas + ?
                WHERE colaborador_id = ?
            ");
            $stmt_reverter->execute([
                $movimentacao['quantidade_horas'],
                $ocorrencia['colaborador_id']
            ]);
            
            // Marca a movimentação como cancelada (ou deleta)
            $stmt_del_mov = $pdo->prepare("DELETE FROM banco_horas_movimentacoes WHERE id = ?");
            $stmt_del_mov->execute([$ocorrencia['banco_horas_movimentacao_id']]);
        }
    }
    
    // Deleta anexos
    $stmt = $pdo->prepare("DELETE FROM ocorrencias_anexos WHERE ocorrencia_id = ?");
    $stmt->execute([$ocorrencia_id]);
    
    // Deleta comentários
    $stmt = $pdo->prepare("DELETE FROM ocorrencias_comentarios WHERE ocorrencia_id = ?");
    $stmt->execute([$ocorrencia_id]);
    
    // Deleta histórico
    $stmt = $pdo->prepare("DELETE FROM ocorrencias_historico WHERE ocorrencia_id = ?");
    $stmt->execute([$ocorrencia_id]);
    
    // Deleta a ocorrência
    $stmt = $pdo->prepare("DELETE FROM ocorrencias WHERE id = ?");
    $stmt->execute([$ocorrencia_id]);
    
    $pdo->commit();
    
    $response = [
        'success' => true,
        'message' => 'Ocorrência deletada com sucesso.'
    ];
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = 'Erro ao deletar ocorrência: ' . $e->getMessage();
    
    // Log do erro para debug
    error_log('Erro ao deletar ocorrência: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Log do erro para debug
    error_log('Erro ao deletar ocorrência: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    
} catch (Error $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
    
    // Log do erro para debug
    error_log('Erro fatal ao deletar ocorrência: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
}

// Limpa qualquer output antes de enviar JSON
ob_clean();

// Garante que sempre retorna JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
