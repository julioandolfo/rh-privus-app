<?php
/**
 * API para Publicar Pesquisa (ativar e enviar notificações)
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
    
    $pesquisa_id = (int)($_POST['pesquisa_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'satisfacao';
    
    if ($pesquisa_id <= 0) {
        throw new Exception('ID da pesquisa inválido');
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($tipo === 'satisfacao') {
            // Verifica se pesquisa existe e pertence ao usuário
            $stmt = $pdo->prepare("SELECT * FROM pesquisas_satisfacao WHERE id = ? AND created_by = ?");
            $stmt->execute([$pesquisa_id, $usuario['id']]);
            $pesquisa = $stmt->fetch();
            
            if (!$pesquisa) {
                throw new Exception('Pesquisa não encontrada');
            }
            
            if ($pesquisa['status'] !== 'rascunho') {
                throw new Exception('Pesquisa já foi publicada');
            }
            
            // Atualiza status
            $stmt = $pdo->prepare("UPDATE pesquisas_satisfacao SET status = 'ativa' WHERE id = ?");
            $stmt->execute([$pesquisa_id]);
            
            // Envia notificações
            $enviar_email = engajamento_enviar_email('pesquisas_satisfacao', $pesquisa['enviar_email']);
            $enviar_push = engajamento_enviar_push('pesquisas_satisfacao', $pesquisa['enviar_push']);
            
            if ($enviar_email || $enviar_push) {
                enviar_notificacao_pesquisa($pesquisa_id, 'satisfacao', $enviar_email, $enviar_push);
            }
            
        } else {
            // Pesquisa rápida
            $stmt = $pdo->prepare("SELECT * FROM pesquisas_rapidas WHERE id = ? AND created_by = ?");
            $stmt->execute([$pesquisa_id, $usuario['id']]);
            $pesquisa = $stmt->fetch();
            
            if (!$pesquisa) {
                throw new Exception('Pesquisa não encontrada');
            }
            
            if ($pesquisa['status'] !== 'rascunho') {
                throw new Exception('Pesquisa já foi publicada');
            }
            
            $stmt = $pdo->prepare("UPDATE pesquisas_rapidas SET status = 'ativa' WHERE id = ?");
            $stmt->execute([$pesquisa_id]);
            
            $enviar_email = engajamento_enviar_email('pesquisas_rapidas', $pesquisa['enviar_email']);
            $enviar_push = engajamento_enviar_push('pesquisas_rapidas', $pesquisa['enviar_push']);
            
            if ($enviar_email || $enviar_push) {
                enviar_notificacao_pesquisa($pesquisa_id, 'rapida', $enviar_email, $enviar_push);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesquisa publicada com sucesso!'
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

