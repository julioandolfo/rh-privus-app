<?php
/**
 * API para Criar Reunião 1:1
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

// Verifica se módulo está ativo
if (!engajamento_modulo_ativo('reunioes_1on1')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Módulo de reuniões 1:1 está desativado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $request_id = $_POST['request_id'] ?? null;
    $lider_id = (int)($_POST['lider_id'] ?? 0);
    $liderado_id = (int)($_POST['liderado_id'] ?? 0);
    $data_reuniao = $_POST['data_reuniao'] ?? date('Y-m-d');
    $hora_inicio = $_POST['hora_inicio'] ?? null;
    $hora_fim = $_POST['hora_fim'] ?? null;
    $assuntos_tratados = trim($_POST['assuntos_tratados'] ?? '');
    $proximos_passos = trim($_POST['proximos_passos'] ?? '');
    $status = $_POST['status'] ?? 'agendada'; // 'agendada' ou 'solicitada'
    
    if ($lider_id <= 0 || $liderado_id <= 0) {
        throw new Exception('Líder e liderado são obrigatórios');
    }
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    if ($request_id) {
        $lockName = 'reuniao_1on1_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Reunião já está sendo processada.'
            ]);
            return;
        }
    }
    
    // Verifica duplicação: reunião idêntica nos últimos 30 segundos
    $stmt_check = $pdo->prepare("
        SELECT id FROM reunioes_1on1 
        WHERE created_by = ? 
        AND lider_id = ?
        AND liderado_id = ?
        AND data_reuniao = ?
        AND COALESCE(assuntos_tratados, '') = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmt_check->execute([
        $usuario['id'],
        $lider_id,
        $liderado_id,
        $data_reuniao,
        $assuntos_tratados
    ]);
    if ($stmt_check->fetch()) {
        if ($request_id) {
            $lockName = 'reuniao_1on1_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'Reunião já foi registrada recentemente.'
        ]);
        return;
    }
    
    // Se for colaborador solicitando, verifica se é ele mesmo
    if ($usuario['role'] === 'COLABORADOR' && $usuario['colaborador_id']) {
        if ($liderado_id != $usuario['colaborador_id']) {
            throw new Exception('Você só pode solicitar reuniões para si mesmo');
        }
        $status = 'solicitada';
    }
    
    // Verifica se líder existe
    $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$lider_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Líder inválido ou inativo');
    }
    
    // Verifica se liderado existe
    $stmt = $pdo->prepare("SELECT id, lider_id FROM colaboradores WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$liderado_id]);
    $liderado = $stmt->fetch();
    if (!$liderado) {
        throw new Exception('Liderado inválido ou inativo');
    }
    
    // Se o liderado tem um líder diferente, apenas avisa mas permite (para flexibilidade)
    if ($liderado['lider_id'] && $liderado['lider_id'] != $lider_id) {
        // Apenas loga, mas não bloqueia - permite flexibilidade para reuniões especiais
        error_log("Aviso: Reunião 1:1 criada entre líder {$lider_id} e liderado {$liderado_id} que tem líder diferente ({$liderado['lider_id']})");
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("
            SELECT id FROM reunioes_1on1 
            WHERE created_by = ? 
            AND lider_id = ?
            AND liderado_id = ?
            AND data_reuniao = ?
            AND COALESCE(assuntos_tratados, '') = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
            FOR UPDATE
        ");
        $stmt_check2->execute([
            $usuario['id'],
            $lider_id,
            $liderado_id,
            $data_reuniao,
            $assuntos_tratados
        ]);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            if ($request_id) {
                $lockName = 'reuniao_1on1_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            }
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Reunião já foi registrada recentemente.'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO reunioes_1on1 (
                lider_id, liderado_id, data_reuniao, hora_inicio, hora_fim,
                status, assuntos_tratados, proximos_passos, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $lider_id, $liderado_id, $data_reuniao, $hora_inicio, $hora_fim,
            $status, $assuntos_tratados, $proximos_passos, $usuario['id']
        ]);
        
        $reuniao_id = $pdo->lastInsertId();
        
        // Envia notificações
        $enviar_email = engajamento_enviar_email('reunioes_1on1');
        $enviar_push = engajamento_enviar_push('reunioes_1on1');
        
        if ($enviar_email || $enviar_push) {
            require_once __DIR__ . '/../../includes/push_notifications.php';
            require_once __DIR__ . '/../../includes/email.php';
            
            // Busca dados do liderado
            $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
            $stmt->execute([$liderado_id]);
            $liderado = $stmt->fetch();
            
            // Busca dados do líder
            $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
            $stmt->execute([$lider_id]);
            $lider = $stmt->fetch();
            
            $base_url = get_base_url();
            $link_reuniao = $base_url . '/pages/reuniao_1on1_view.php?id=' . $reuniao_id;
            
            if ($status === 'solicitada') {
                // Notifica líder sobre solicitação
                if ($enviar_email && !empty($lider['email_pessoal'])) {
                    $assunto = "Solicitação de Reunião 1:1";
                    $mensagem = "
                        <h2>Olá, {$lider['nome_completo']}!</h2>
                        <p><strong>{$liderado['nome_completo']}</strong> solicitou uma reunião 1:1 com você.</p>
                        <p><strong>Data Sugerida:</strong> " . date('d/m/Y', strtotime($data_reuniao)) . "</p>
                        " . ($hora_inicio ? "<p><strong>Horário Sugerido:</strong> {$hora_inicio}" . ($hora_fim ? " às {$hora_fim}" : "") . "</p>" : "") . "
                        " . ($assuntos_tratados ? "<p><strong>Motivo:</strong> " . nl2br(htmlspecialchars($assuntos_tratados)) . "</p>" : "") . "
                        <p><a href='{$link_reuniao}' style='background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Ver Solicitação</a></p>
                    ";
                    enviar_email($lider['email_pessoal'], $assunto, $mensagem);
                }
                
                if ($enviar_push) {
                    $titulo = "Solicitação de Reunião 1:1";
                    $mensagem_push = "{$liderado['nome_completo']} solicitou uma reunião para " . date('d/m/Y', strtotime($data_reuniao));
                    enviar_push_colaborador($lider_id, $titulo, $mensagem_push, $link_reuniao);
                }
            } else {
                // Notifica liderado sobre agendamento
                if ($enviar_email && !empty($liderado['email_pessoal'])) {
                    $assunto = "Reunião 1:1 Agendada";
                    $mensagem = "
                        <h2>Olá, {$liderado['nome_completo']}!</h2>
                        <p>Uma reunião 1:1 foi agendada com seu líder <strong>{$lider['nome_completo']}</strong>.</p>
                        <p><strong>Data:</strong> " . date('d/m/Y', strtotime($data_reuniao)) . "</p>
                        " . ($hora_inicio ? "<p><strong>Horário:</strong> {$hora_inicio}" . ($hora_fim ? " às {$hora_fim}" : "") . "</p>" : "") . "
                        " . ($assuntos_tratados ? "<p><strong>Assuntos:</strong> " . nl2br(htmlspecialchars($assuntos_tratados)) . "</p>" : "") . "
                        <p><a href='{$link_reuniao}' style='background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Ver Reunião</a></p>
                    ";
                    enviar_email($liderado['email_pessoal'], $assunto, $mensagem);
                }
                
                if ($enviar_push) {
                    $titulo = "Reunião 1:1 Agendada";
                    $mensagem_push = "Reunião agendada com {$lider['nome_completo']} para " . date('d/m/Y', strtotime($data_reuniao));
                    enviar_push_colaborador($liderado_id, $titulo, $mensagem_push, $link_reuniao);
                }
            }
        }
        
        $pdo->commit();
        
        // Libera o lock do MySQL
        if ($request_id) {
            $lockName = 'reuniao_1on1_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Reunião 1:1 criada com sucesso!',
            'reuniao_id' => $reuniao_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($request_id) {
            try {
                $lockName = 'reuniao_1on1_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            } catch (Exception $lockEx) {
                // Ignora erro ao liberar lock
            }
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Libera o lock em caso de erro geral
    if (isset($request_id) && $request_id && isset($pdo)) {
        try {
            $lockName = 'reuniao_1on1_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
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

