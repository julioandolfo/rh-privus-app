<?php
/**
 * API para Solicitar Feedback
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
    
    $solicitante_usuario_id = $usuario['id'] ?? null;
    $solicitante_colaborador_id = $usuario['colaborador_id'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    $solicitado_colaborador_id = $_POST['solicitado_colaborador_id'] ?? null;
    $mensagem = trim($_POST['mensagem'] ?? '');
    $prazo = $_POST['prazo'] ?? null;
    
    // Validações
    if (empty($solicitado_colaborador_id)) {
        throw new Exception('Selecione para quem você quer solicitar feedback.');
    }
    
    // Verifica se colaborador existe e está ativo
    $stmt = $pdo->prepare("SELECT id, status FROM colaboradores WHERE id = ?");
    $stmt->execute([$solicitado_colaborador_id]);
    $solicitado = $stmt->fetch();
    
    if (!$solicitado) {
        throw new Exception('Colaborador não encontrado.');
    }
    
    if ($solicitado['status'] !== 'ativo') {
        throw new Exception('Não é possível solicitar feedback de colaborador inativo.');
    }
    
    // Não permite solicitar feedback para si mesmo
    if ($solicitante_colaborador_id && $solicitante_colaborador_id == $solicitado_colaborador_id) {
        throw new Exception('Você não pode solicitar feedback para si mesmo.');
    }
    
    // Verifica prazo (se informado, deve ser futuro e máximo 90 dias)
    if ($prazo) {
        $prazo_timestamp = strtotime($prazo);
        $hoje = strtotime(date('Y-m-d'));
        $max_prazo = strtotime('+90 days', $hoje);
        
        if ($prazo_timestamp <= $hoje) {
            throw new Exception('O prazo deve ser uma data futura.');
        }
        
        if ($prazo_timestamp > $max_prazo) {
            throw new Exception('O prazo não pode ser superior a 90 dias.');
        }
    }
    
    // Proteção contra requisições duplicadas
    if ($request_id) {
        $lockName = 'feedback_solicitar_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Solicitação já está sendo processada.'
            ]);
            return;
        }
    }
    
    // Verifica duplicação: solicitação idêntica nos últimos 5 minutos
    $stmt_check = $pdo->prepare("
        SELECT id FROM feedback_solicitacoes 
        WHERE solicitante_usuario_id <=> ? 
        AND COALESCE(solicitante_colaborador_id, 0) = COALESCE(?, 0)
        AND solicitado_colaborador_id = ?
        AND status = 'pendente'
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 1
    ");
    $stmt_check->execute([
        $solicitante_usuario_id,
        $solicitante_colaborador_id,
        $solicitado_colaborador_id
    ]);
    if ($stmt_check->fetch()) {
        if ($request_id) {
            $lockName = 'feedback_solicitar_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
        }
        throw new Exception('Você já tem uma solicitação pendente para este colaborador.');
    }
    
    // Busca usuario_id do solicitado se existir
    $solicitado_usuario_id = null;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
    $stmt->execute([$solicitado_colaborador_id]);
    $dest_usuario = $stmt->fetch();
    if ($dest_usuario) {
        $solicitado_usuario_id = $dest_usuario['id'];
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Insere solicitação
        $stmt = $pdo->prepare("
            INSERT INTO feedback_solicitacoes (
                solicitante_usuario_id, solicitante_colaborador_id,
                solicitado_usuario_id, solicitado_colaborador_id,
                mensagem, prazo, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pendente')
        ");
        
        $stmt->execute([
            $solicitante_usuario_id,
            $solicitante_colaborador_id,
            $solicitado_usuario_id,
            $solicitado_colaborador_id,
            !empty($mensagem) ? $mensagem : null,
            !empty($prazo) ? $prazo : null
        ]);
        
        $solicitacao_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Libera lock
        if ($request_id) {
            $lockName = 'feedback_solicitar_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
        }
        
        // Adiciona pontos por solicitar feedback
        require_once __DIR__ . '/../../includes/pontuacao.php';
        $pontos_ganhos = adicionar_pontos('solicitar_feedback', $solicitante_usuario_id, $solicitante_colaborador_id, $solicitacao_id, 'feedback_solicitacao');
        
        // Busca quantidade de pontos da ação
        $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'solicitar_feedback' AND ativo = 1");
        $stmt_pontos->execute();
        $config_pontos = $stmt_pontos->fetch();
        $pontos_valor = $config_pontos ? $config_pontos['pontos'] : 10;
        
        // Busca novo total de pontos
        $novos_pontos = obter_pontos($solicitante_usuario_id, $solicitante_colaborador_id);
        
        // Notifica solicitado (email, push, notificação interna)
        require_once __DIR__ . '/../../includes/feedback_notificacoes.php';
        notificar_solicitacao_feedback($solicitacao_id);
        
        $response = [
            'success' => true,
            'message' => 'Solicitação de feedback enviada com sucesso!'
        ];
        
        // Adiciona info de pontos se ganhou
        if ($pontos_ganhos) {
            $response['pontos_ganhos'] = $pontos_valor;
            $response['pontos_totais'] = $novos_pontos['pontos_totais'] ?? 0;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if (isset($request_id) && $request_id) {
            try {
                $lockName = 'feedback_solicitar_' . $request_id;
                $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
                $stmt->execute([$lockName]);
            } catch (Exception $lockEx) {
                // Ignora erro ao liberar lock
            }
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Libera lock em caso de erro geral
    if (isset($request_id) && $request_id && isset($pdo)) {
        try {
            $lockName = 'feedback_solicitar_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
        } catch (Exception $lockEx) {
            // Ignora erro ao liberar lock
        }
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
