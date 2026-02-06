<?php
/**
 * API para Responder Solicitação de Feedback (Aceitar ou Recusar)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $solicitacao_id = $_POST['solicitacao_id'] ?? null;
    $acao = $_POST['acao'] ?? null; // 'aceitar' ou 'recusar'
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    // Validações
    if (empty($solicitacao_id)) {
        throw new Exception('ID da solicitação é obrigatório.');
    }
    
    if (!in_array($acao, ['aceitar', 'recusar'])) {
        throw new Exception('Ação inválida.');
    }
    
    // Busca solicitação
    $stmt = $pdo->prepare("
        SELECT * FROM feedback_solicitacoes 
        WHERE id = ?
    ");
    $stmt->execute([$solicitacao_id]);
    $solicitacao = $stmt->fetch();
    
    if (!$solicitacao) {
        throw new Exception('Solicitação não encontrada.');
    }
    
    // Verifica se o usuário é o solicitado
    $pode_responder = false;
    if ($usuario_id && $solicitacao['solicitado_usuario_id'] == $usuario_id) {
        $pode_responder = true;
    } elseif ($colaborador_id && $solicitacao['solicitado_colaborador_id'] == $colaborador_id) {
        $pode_responder = true;
    }
    
    if (!$pode_responder) {
        throw new Exception('Você não tem permissão para responder esta solicitação.');
    }
    
    // Verifica se já foi respondida
    if ($solicitacao['status'] !== 'pendente') {
        throw new Exception('Esta solicitação já foi respondida.');
    }
    
    // Atualiza status da solicitação
    $novo_status = $acao === 'aceitar' ? 'aceita' : 'recusada';
    
    $stmt = $pdo->prepare("
        UPDATE feedback_solicitacoes 
        SET status = ?,
            resposta_mensagem = ?,
            respondida_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $novo_status,
        !empty($mensagem) ? $mensagem : null,
        $solicitacao_id
    ]);
    
    // Adiciona pontos por responder solicitação de feedback
    require_once __DIR__ . '/../../includes/pontuacao.php';
    $pontos_ganhos = adicionar_pontos('responder_solicitacao_feedback', $usuario_id, $colaborador_id, $solicitacao_id, 'feedback_solicitacao');
    
    // Busca quantidade de pontos da ação
    $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'responder_solicitacao_feedback' AND ativo = 1");
    $stmt_pontos->execute();
    $config_pontos = $stmt_pontos->fetch();
    $pontos_valor = $config_pontos ? $config_pontos['pontos'] : 20;
    
    // Busca novo total de pontos
    $novos_pontos = obter_pontos($usuario_id, $colaborador_id);
    
    // Notifica solicitante sobre a resposta
    require_once __DIR__ . '/../../includes/feedback_notificacoes.php';
    notificar_resposta_solicitacao($solicitacao_id, $acao);
    
    // Se aceitar, redireciona para enviar feedback
    if ($acao === 'aceitar') {
        $response = [
            'success' => true,
            'message' => 'Solicitação aceita! Você será redirecionado para enviar o feedback.',
            'redirect' => '../pages/feedback_enviar.php?solicitacao_id=' . $solicitacao_id . '&destinatario_id=' . $solicitacao['solicitante_colaborador_id']
        ];
    } else {
        $response = [
            'success' => true,
            'message' => 'Solicitação recusada com sucesso.'
        ];
    }
    
    // Adiciona info de pontos se ganhou
    if ($pontos_ganhos) {
        $response['pontos_ganhos'] = $pontos_valor;
        $response['pontos_totais'] = $novos_pontos['pontos_totais'] ?? 0;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
