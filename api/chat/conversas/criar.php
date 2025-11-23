<?php
/**
 * API: Criar Nova Conversa
 */

// Desabilita exibição de erros para não quebrar JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/permissions.php';
    require_once __DIR__ . '/../../../includes/chat_functions.php';
    // Verifica login
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Verifica se é colaborador
    if (!is_colaborador() || empty($usuario['colaborador_id'])) {
        throw new Exception('Apenas colaboradores podem criar conversas');
    }
    
    // Verifica se chat está ativo
    if (!chat_ativo()) {
        throw new Exception('Sistema de chat está temporariamente desativado');
    }
    
    // Valida dados
    $titulo = trim($_POST['titulo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $categoria_id = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $prioridade = $_POST['prioridade'] ?? 'normal';
    
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    if (empty($mensagem)) {
        throw new Exception('Mensagem é obrigatória');
    }
    
    if (!in_array($prioridade, ['baixa', 'normal', 'alta', 'urgente'])) {
        $prioridade = 'normal';
    }
    
    // Cria conversa
    $resultado = criar_conversa(
        $usuario['colaborador_id'],
        $titulo,
        $mensagem,
        $categoria_id,
        $prioridade
    );
    
    if (!$resultado['success']) {
        throw new Exception($resultado['error'] ?? 'Erro ao criar conversa');
    }
    
    // Envia notificações para RHs
    require_once __DIR__ . '/../../../includes/chat_notifications.php';
    enviar_notificacao_nova_conversa($resultado['conversa_id']);
    
    $response = [
        'success' => true,
        'message' => 'Conversa criada com sucesso',
        'conversa_id' => $resultado['conversa_id']
    ];
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
} catch (Error $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
}

// Garante que sempre retorna JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

