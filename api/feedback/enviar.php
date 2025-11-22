<?php
/**
 * API para Enviar Feedback
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
    // Log de entrada da API
    error_log("=== FEEDBACK API CHAMADA ===");
    error_log("Request ID: " . ($_POST['request_id'] ?? 'N/A'));
    error_log("Timestamp: " . date('Y-m-d H:i:s'));
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Verifica permissão
    if (!can_access_page('feedback_enviar.php')) {
        throw new Exception('Você não tem permissão para enviar feedbacks.');
    }
    
    $remetente_usuario_id = $usuario['id'] ?? null;
    $remetente_colaborador_id = $usuario['colaborador_id'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    error_log("Remetente Usuario ID: " . ($remetente_usuario_id ?? 'NULL'));
    error_log("Remetente Colaborador ID: " . ($remetente_colaborador_id ?? 'NULL'));
    
    // Proteção contra requisições duplicadas usando request_id na sessão
    $session_key = 'last_feedback_request';
    $session_time_key = 'last_feedback_time';
    
    // Limpa requisições antigas (mais de 5 segundos)
    if (isset($_SESSION[$session_time_key])) {
        $time_diff = time() - $_SESSION[$session_time_key];
        if ($time_diff > 5) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
    }
    
    // Verifica se é requisição duplicada
    if ($request_id && isset($_SESSION[$session_key]) && $_SESSION[$session_key] === $request_id) {
        error_log("❌ BLOQUEADO: Requisição duplicada via session (Request ID: $request_id)");
        throw new Exception('Requisição duplicada detectada. Feedback já está sendo processado.');
    }
    
    error_log("✅ Request ID validado, continuando...");
    
    $destinatario_colaborador_id = $_POST['destinatario_colaborador_id'] ?? null;
    $template_id = $_POST['template_id'] ?? 0;
    $conteudo = trim($_POST['conteudo'] ?? '');
    $anonimo = isset($_POST['anonimo']) && $_POST['anonimo'] == '1';
    $presencial = isset($_POST['presencial']) && $_POST['presencial'] == '1';
    $anotacoes_internas = trim($_POST['anotacoes_internas'] ?? '');
    $avaliacoes = $_POST['avaliacoes'] ?? [];
    
    // Validações
    if (empty($destinatario_colaborador_id)) {
        throw new Exception('Selecione um colaborador para enviar o feedback.');
    }
    
    if (empty($conteudo)) {
        throw new Exception('O conteúdo do feedback é obrigatório.');
    }
    
    // Verifica se colaborador existe e está ativo
    $stmt = $pdo->prepare("SELECT id, status FROM colaboradores WHERE id = ?");
    $stmt->execute([$destinatario_colaborador_id]);
    $destinatario = $stmt->fetch();
    
    if (!$destinatario) {
        throw new Exception('Colaborador não encontrado.');
    }
    
    if ($destinatario['status'] !== 'ativo') {
        throw new Exception('Não é possível enviar feedback para colaborador inativo.');
    }
    
    // Não permite enviar feedback para si mesmo
    if ($remetente_colaborador_id && $remetente_colaborador_id == $destinatario_colaborador_id) {
        throw new Exception('Você não pode enviar feedback para si mesmo.');
    }
    
    // Verifica permissão para acessar este colaborador (se aplicável)
    if (!has_role(['ADMIN']) && !empty($usuario['empresa_id'])) {
        $stmt = $pdo->prepare("SELECT empresa_id FROM colaboradores WHERE id = ?");
        $stmt->execute([$destinatario_colaborador_id]);
        $dest_empresa = $stmt->fetch();
        
        if ($dest_empresa && $dest_empresa['empresa_id'] != $usuario['empresa_id']) {
            throw new Exception('Você não tem permissão para enviar feedback para este colaborador.');
        }
    }
    
    // Marca esta requisição como processada com timestamp
    if ($request_id) {
        $_SESSION[$session_key] = $request_id;
        $_SESSION[$session_time_key] = time();
    }
    
    // Verifica duplicação: feedback idêntico nos últimos 30 segundos
    $stmt_check = $pdo->prepare("
        SELECT id FROM feedbacks 
        WHERE remetente_usuario_id = ? 
        AND remetente_colaborador_id = ?
        AND destinatario_colaborador_id = ?
        AND conteudo = ?
        AND COALESCE(template_id, 0) = COALESCE(?, 0)
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmt_check->execute([
        $remetente_usuario_id,
        $remetente_colaborador_id,
        $destinatario_colaborador_id,
        $conteudo,
        $template_id > 0 ? $template_id : 0
    ]);
    if ($stmt_check->fetch()) {
        error_log("❌ BLOQUEADO: Feedback duplicado detectado no banco (30s)");
        unset($_SESSION[$session_key]);
        unset($_SESSION[$session_time_key]);
        throw new Exception('Feedback duplicado detectado. Aguarde alguns segundos antes de enviar novamente.');
    }
    
    error_log("✅ Verificação de duplicação passou, iniciando transação...");
    
    // Busca usuario_id do destinatário se existir
    $destinatario_usuario_id = null;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
    $stmt->execute([$destinatario_colaborador_id]);
    $dest_usuario = $stmt->fetch();
    if ($dest_usuario) {
        $destinatario_usuario_id = $dest_usuario['id'];
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("
            SELECT id FROM feedbacks 
            WHERE remetente_usuario_id = ? 
            AND remetente_colaborador_id = ?
            AND destinatario_colaborador_id = ?
            AND conteudo = ?
            AND COALESCE(template_id, 0) = COALESCE(?, 0)
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
            FOR UPDATE
        ");
        $stmt_check2->execute([
            $remetente_usuario_id,
            $remetente_colaborador_id,
            $destinatario_colaborador_id,
            $conteudo,
            $template_id > 0 ? $template_id : 0
        ]);
        if ($stmt_check2->fetch()) {
            error_log("❌ BLOQUEADO: Feedback duplicado detectado no double-check com lock");
            $pdo->rollBack();
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
            throw new Exception('Feedback duplicado detectado. Aguarde alguns segundos antes de enviar novamente.');
        }
        
        error_log("✅ Double-check passou, inserindo feedback no banco...");
        
        // Insere feedback
        $stmt = $pdo->prepare("
            INSERT INTO feedbacks (
                remetente_usuario_id, remetente_colaborador_id,
                destinatario_usuario_id, destinatario_colaborador_id,
                template_id, conteudo, anonimo, presencial, anotacoes_internas, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
        ");
        
        $stmt->execute([
            $remetente_usuario_id,
            $remetente_colaborador_id,
            $destinatario_usuario_id,
            $destinatario_colaborador_id,
            $template_id > 0 ? $template_id : null,
            $conteudo,
            $anonimo ? 1 : 0,
            $presencial ? 1 : 0,
            !empty($anotacoes_internas) ? $anotacoes_internas : null
        ]);
        
        $feedback_id = $pdo->lastInsertId();
        
        error_log("✅ Feedback inserido com ID: $feedback_id");
        
        // Insere avaliações (estrelas)
        if (!empty($avaliacoes) && is_array($avaliacoes)) {
            $stmt_avaliacao = $pdo->prepare("
                INSERT INTO feedback_avaliacoes (feedback_id, item_id, nota)
                VALUES (?, ?, ?)
            ");
            
            foreach ($avaliacoes as $item_id => $nota) {
                $item_id = (int)$item_id;
                $nota = (int)$nota;
                
                if ($item_id > 0 && $nota >= 1 && $nota <= 5) {
                    // Verifica se item existe
                    $stmt_check = $pdo->prepare("SELECT id FROM feedback_itens WHERE id = ? AND status = 'ativo'");
                    $stmt_check->execute([$item_id]);
                    if ($stmt_check->fetch()) {
                        $stmt_avaliacao->execute([$feedback_id, $item_id, $nota]);
                    }
                }
            }
        }
        
        $pdo->commit();
        
        error_log("✅ Transação committed com sucesso");
        
        // Limpa a flag de requisição após sucesso
        if (isset($session_key)) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
        
        // Adiciona pontos por enviar feedback
        require_once __DIR__ . '/../../includes/pontuacao.php';
        adicionar_pontos('enviar_feedback', $remetente_usuario_id, $remetente_colaborador_id, $feedback_id, 'feedback');
        
        error_log("✅ Pontos adicionados");
        
        // Notifica destinatário (email, push, notificação interna)
        require_once __DIR__ . '/../../includes/feedback_notificacoes.php';
        notificar_feedback_recebido($feedback_id);
        
        error_log("✅ Notificações enviadas");
        error_log("=== FEEDBACK API FINALIZADA COM SUCESSO ===");
        
        echo json_encode([
            'success' => true,
            'message' => 'Feedback enviado com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Limpa a flag de requisição em caso de erro também
        if (isset($session_key)) {
            unset($_SESSION[$session_key]);
            unset($_SESSION[$session_time_key]);
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Limpa a flag de requisição em caso de erro
    $session_key = 'last_feedback_request';
    $session_time_key = 'last_feedback_time';
    if (isset($_SESSION[$session_key])) {
        unset($_SESSION[$session_key]);
    }
    if (isset($_SESSION[$session_time_key])) {
        unset($_SESSION[$session_time_key]);
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

