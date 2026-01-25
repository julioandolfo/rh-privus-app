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
    // Funções auxiliares dentro do escopo try
    function logFeedback($message) {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/feedback.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    function releaseFeedbackLock($pdoInstance, $requestId, $context = '') {
        if (!$requestId || !$pdoInstance) {
            return;
        }
        try {
            $lockName = 'feedback_' . $requestId;
            $stmtRelease = $pdoInstance->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmtRelease->execute([$lockName]);
            logFeedback("🔓 Lock liberado" . ($context ? " ($context)" : ""));
        } catch (Exception $releaseEx) {
            logFeedback("⚠️ Erro ao liberar lock ($context): " . $releaseEx->getMessage());
        }
    }
    
    // Log de entrada da API
    logFeedback("=== FEEDBACK API CHAMADA ===");
    logFeedback("Request ID: " . ($_POST['request_id'] ?? 'N/A'));
    logFeedback("Method: " . $_SERVER['REQUEST_METHOD']);
    logFeedback("Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    // Verifica permissão
    if (!can_access_page('feedback_enviar.php')) {
        throw new Exception('Você não tem permissão para enviar feedbacks.');
    }
    
    $remetente_usuario_id = $usuario['id'] ?? null;
    $remetente_colaborador_id = $usuario['colaborador_id'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    logFeedback("Remetente Usuario ID: " . ($remetente_usuario_id ?? 'NULL'));
    logFeedback("Remetente Colaborador ID: " . ($remetente_colaborador_id ?? 'NULL'));
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    // GET_LOCK é atômico e perfeito para evitar race conditions
    if ($request_id) {
        logFeedback("🔒 Tentando obter lock atômico do MySQL para Request ID: $request_id");
        
        // Timeout de 0 = não espera, retorna imediatamente se já estiver locked
        $lockName = 'feedback_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            logFeedback("❌ BLOQUEADO: Lock já obtido por outra requisição (Request ID: $request_id). Respondendo sucesso idempotente.");
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Feedback já está sendo processado.'
            ]);
            return;
        }
        
        logFeedback("✅ Lock obtido com sucesso! Continuando...");
    }
    
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
    
    // Verifica duplicação: feedback idêntico nos últimos 30 segundos
    $stmt_check = $pdo->prepare("
        SELECT id FROM feedbacks 
        WHERE remetente_usuario_id = ? 
        AND COALESCE(remetente_colaborador_id, 0) = COALESCE(?, 0)
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
        logFeedback("❌ BLOQUEADO: Feedback duplicado detectado no banco (30s). Respondendo sucesso idempotente.");
        releaseFeedbackLock($pdo, $request_id, 'duplicacao_pre_tx');
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'Feedback já foi registrado recentemente.'
        ]);
        return;
    }
    
    logFeedback("✅ Verificação de duplicação passou, iniciando transação...");
    
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
            AND COALESCE(remetente_colaborador_id, 0) = COALESCE(?, 0)
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
            logFeedback("❌ BLOQUEADO: Feedback duplicado detectado no double-check com lock. Respondendo sucesso idempotente.");
            $pdo->rollBack();
            releaseFeedbackLock($pdo, $request_id, 'duplicacao_double_check');
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Feedback já foi registrado recentemente.'
            ]);
            return;
        }
        
        logFeedback("✅ Double-check passou, inserindo feedback no banco...");
        
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
        
        logFeedback("✅ Feedback inserido com ID: $feedback_id");
        
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
        
        logFeedback("✅ Transação committed com sucesso");
        
        // Libera o lock do MySQL
        if ($request_id) {
            $lockName = 'feedback_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
            logFeedback("🔓 Lock liberado");
        }
        
        // Adiciona pontos por enviar feedback
        require_once __DIR__ . '/../../includes/pontuacao.php';
        $pontos_ganhos = adicionar_pontos('enviar_feedback', $remetente_usuario_id, $remetente_colaborador_id, $feedback_id, 'feedback');
        
        // Busca quantidade de pontos da ação
        $stmt_pontos = $pdo->prepare("SELECT pontos FROM pontos_config WHERE acao = 'enviar_feedback' AND ativo = 1");
        $stmt_pontos->execute();
        $config_pontos = $stmt_pontos->fetch();
        $pontos_valor = $config_pontos ? $config_pontos['pontos'] : 30;
        
        // Busca novo total de pontos
        $novos_pontos = obter_pontos($remetente_usuario_id, $remetente_colaborador_id);
        
        logFeedback("✅ Pontos adicionados");
        
        // Notifica destinatário (email, push, notificação interna)
        require_once __DIR__ . '/../../includes/feedback_notificacoes.php';
        notificar_feedback_recebido($feedback_id);
        
        logFeedback("✅ Notificações enviadas");
        logFeedback("=== FEEDBACK API FINALIZADA COM SUCESSO ===");
        logFeedback(""); // Linha em branco para separar logs
        
        $response = [
            'success' => true,
            'message' => 'Feedback enviado com sucesso!'
        ];
        
        // Adiciona info de pontos se ganhou
        if ($pontos_ganhos) {
            $response['pontos_ganhos'] = $pontos_valor;
            $response['pontos_totais'] = $novos_pontos['pontos_totais'] ?? 0;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        logFeedback("❌ ERRO na transação: " . $e->getMessage());
        $pdo->rollBack();
        
        // Libera o lock em caso de erro também
        if (isset($request_id) && $request_id) {
            try {
                $lockName = 'feedback_' . $request_id;
                $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
                $stmt->execute([$lockName]);
                logFeedback("🔓 Lock liberado após erro");
            } catch (Exception $lockEx) {
                logFeedback("⚠️ Erro ao liberar lock: " . $lockEx->getMessage());
            }
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Libera o lock em caso de erro geral
    if (isset($request_id) && $request_id && isset($pdo)) {
        try {
            $lockName = 'feedback_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
            if (function_exists('logFeedback')) {
                logFeedback("🔓 Lock liberado após erro geral");
            }
        } catch (Exception $lockEx) {
            // Ignora erro ao liberar lock
        }
    }
    
    if (function_exists('logFeedback')) {
        logFeedback("❌ ERRO GERAL: " . $e->getMessage());
        logFeedback("=== FEEDBACK API FINALIZADA COM ERRO ===");
        logFeedback(""); // Linha em branco
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

