<?php
/**
 * API para Responder Feedback
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

// Função auxiliar para log em arquivo customizado
function logFeedbackResposta($message) {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/feedback_respostas.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    logFeedbackResposta("=== FEEDBACK RESPOSTA API CHAMADA ===");
    logFeedbackResposta("Request ID: " . ($_POST['request_id'] ?? 'N/A'));
    logFeedbackResposta("Method: " . $_SERVER['REQUEST_METHOD']);
    logFeedbackResposta("Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    $request_id = $_POST['request_id'] ?? null;
    
    logFeedbackResposta("Usuario ID: " . ($usuario_id ?? 'NULL'));
    logFeedbackResposta("Colaborador ID: " . ($colaborador_id ?? 'NULL'));
    
    $feedback_id = $_POST['feedback_id'] ?? null;
    $resposta = trim($_POST['resposta'] ?? '');
    $resposta_pai_id = $_POST['resposta_pai_id'] ?? null;
    
    logFeedbackResposta("Feedback ID: " . ($feedback_id ?? 'NULL'));
    logFeedbackResposta("Resposta (primeiros 50 chars): " . substr($resposta, 0, 50));
    
    // Validações
    if (empty($feedback_id)) {
        throw new Exception('ID do feedback é obrigatório.');
    }
    
    if (empty($resposta)) {
        throw new Exception('A resposta não pode estar vazia.');
    }
    
    // Verifica se feedback existe e se usuário pode responder
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            destinatario_usuario_id, 
            destinatario_colaborador_id,
            remetente_usuario_id,
            remetente_colaborador_id,
            status
        FROM feedbacks 
        WHERE id = ?
    ");
    $stmt->execute([$feedback_id]);
    $feedback = $stmt->fetch();
    
    if (!$feedback) {
        throw new Exception('Feedback não encontrado.');
    }
    
    if ($feedback['status'] !== 'ativo') {
        throw new Exception('Não é possível responder a um feedback inativo.');
    }
    
    // Verifica se usuário é destinatário ou remetente do feedback
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    $pode_responder = false;
    
    if ($usuario_id) {
        $pode_responder = ($feedback['destinatario_usuario_id'] == $usuario_id) || 
                         ($feedback['remetente_usuario_id'] == $usuario_id);
    } elseif ($colaborador_id) {
        $pode_responder = ($feedback['destinatario_colaborador_id'] == $colaborador_id) || 
                         ($feedback['remetente_colaborador_id'] == $colaborador_id);
    }
    
    if (!$pode_responder) {
        logFeedbackResposta("❌ BLOQUEADO: Usuário sem permissão para responder");
        throw new Exception('Você não tem permissão para responder este feedback.');
    }
    
    // Se houver resposta_pai_id, verifica se existe
    if ($resposta_pai_id) {
        $stmt = $pdo->prepare("SELECT id FROM feedback_respostas WHERE id = ? AND feedback_id = ? AND status = 'ativo'");
        $stmt->execute([$resposta_pai_id, $feedback_id]);
        if (!$stmt->fetch()) {
            logFeedbackResposta("❌ ERRO: Resposta pai não encontrada");
            throw new Exception('Resposta pai não encontrada.');
        }
    }
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    if ($request_id) {
        logFeedbackResposta("🔒 Tentando obter lock atômico do MySQL para Request ID: $request_id");
        
        $lockName = 'feedback_resposta_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            logFeedbackResposta("❌ BLOQUEADO: Lock já obtido por outra requisição. Respondendo sucesso idempotente.");
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Resposta já está sendo processada.'
            ]);
            return;
        }
        
        logFeedbackResposta("✅ Lock obtido com sucesso! Continuando...");
    }
    
    // Verifica duplicação: resposta idêntica nos últimos 30 segundos
    $where_duplicacao = [];
    $params_duplicacao = [$feedback_id, $resposta];
    
    if ($usuario_id) {
        $where_duplicacao[] = "usuario_id = ?";
        $params_duplicacao[] = $usuario_id;
    }
    if ($colaborador_id) {
        $where_duplicacao[] = "colaborador_id = ?";
        $params_duplicacao[] = $colaborador_id;
    }
    
    if (!empty($where_duplicacao)) {
        $where_sql_dup = " AND (" . implode(" OR ", $where_duplicacao) . ")";
        $stmt_check = $pdo->prepare("
            SELECT id FROM feedback_respostas 
            WHERE feedback_id = ? 
            AND resposta = ?
            $where_sql_dup
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
        ");
        $stmt_check->execute($params_duplicacao);
        if ($stmt_check->fetch()) {
            logFeedbackResposta("❌ BLOQUEADO: Resposta duplicada detectada no banco (30s)");
            if ($request_id) {
                $lockName = 'feedback_resposta_' . $request_id;
                $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
                $stmt->execute([$lockName]);
            }
            throw new Exception('Resposta duplicada detectada. Aguarde alguns segundos antes de responder novamente.');
        }
    }
    
    logFeedbackResposta("✅ Verificação de duplicação passou, iniciando transação...");
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        if (!empty($where_duplicacao)) {
            $where_sql_dup = " AND (" . implode(" OR ", $where_duplicacao) . ")";
            $stmt_check2 = $pdo->prepare("
                SELECT id FROM feedback_respostas 
                WHERE feedback_id = ? 
                AND resposta = ?
                $where_sql_dup
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
                LIMIT 1
                FOR UPDATE
            ");
            $stmt_check2->execute($params_duplicacao);
            if ($stmt_check2->fetch()) {
                $pdo->rollBack();
                logFeedbackResposta("❌ BLOQUEADO: Resposta duplicada detectada no double-check com lock");
                if ($request_id) {
                    $lockName = 'feedback_resposta_' . $request_id;
                    $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
                    $stmt->execute([$lockName]);
                }
                throw new Exception('Resposta duplicada detectada. Aguarde alguns segundos antes de responder novamente.');
            }
        }
        
        logFeedbackResposta("✅ Double-check passou, inserindo resposta no banco...");
        
        // Insere resposta
        $stmt = $pdo->prepare("
            INSERT INTO feedback_respostas (
                feedback_id, usuario_id, colaborador_id, 
                resposta, resposta_pai_id, status
            ) VALUES (?, ?, ?, ?, ?, 'ativo')
        ");
        
        $stmt->execute([
            $feedback_id,
            $usuario_id,
            $colaborador_id,
            $resposta,
            $resposta_pai_id ?: null
        ]);
        
        $resposta_id = $pdo->lastInsertId();
        
        logFeedbackResposta("✅ Resposta inserida com ID: $resposta_id");
        
        $pdo->commit();
        
        logFeedbackResposta("✅ Transação committed com sucesso");
        
        // Libera o lock do MySQL
        if ($request_id) {
            $lockName = 'feedback_resposta_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
            logFeedbackResposta("🔓 Lock liberado");
        }
        
        logFeedbackResposta("=== FEEDBACK RESPOSTA API FINALIZADA COM SUCESSO ===");
        logFeedbackResposta("");
        
        echo json_encode([
            'success' => true,
            'message' => 'Resposta enviada com sucesso!'
        ]);
        
    } catch (Exception $e) {
        logFeedbackResposta("❌ ERRO na transação: " . $e->getMessage());
        $pdo->rollBack();
        
        // Libera o lock em caso de erro também
        if ($request_id) {
            try {
                $lockName = 'feedback_resposta_' . $request_id;
                $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
                $stmt->execute([$lockName]);
                logFeedbackResposta("🔓 Lock liberado após erro");
            } catch (Exception $lockEx) {
                logFeedbackResposta("⚠️ Erro ao liberar lock: " . $lockEx->getMessage());
            }
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    // Libera o lock em caso de erro geral
    if (isset($request_id) && $request_id && isset($pdo)) {
        try {
            $lockName = 'feedback_resposta_' . $request_id;
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?) as release_result");
            $stmt->execute([$lockName]);
            if (function_exists('logFeedbackResposta')) {
                logFeedbackResposta("🔓 Lock liberado após erro geral");
            }
        } catch (Exception $lockEx) {
            // Ignora erro ao liberar lock
        }
    }
    
    if (function_exists('logFeedbackResposta')) {
        logFeedbackResposta("❌ ERRO GERAL: " . $e->getMessage());
        logFeedbackResposta("=== FEEDBACK RESPOSTA API FINALIZADA COM ERRO ===");
        logFeedbackResposta("");
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

