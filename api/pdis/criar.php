<?php
/**
 * API para Criar PDI (Plano de Desenvolvimento Individual)
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
if (!engajamento_modulo_ativo('pdis')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Módulo de PDIs está desativado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $request_id = $_POST['request_id'] ?? null;
    $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $objetivo_geral = trim($_POST['objetivo_geral'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim_prevista = !empty($_POST['data_fim_prevista']) ? $_POST['data_fim_prevista'] : null;
    $status = $_POST['status'] ?? 'rascunho';
    $enviar_email = isset($_POST['enviar_email']) ? (int)$_POST['enviar_email'] : 1;
    $enviar_push = isset($_POST['enviar_push']) ? (int)$_POST['enviar_push'] : 1;
    
    // Valida status
    $statuses_validos = ['rascunho', 'ativo', 'concluido', 'cancelado', 'pausado'];
    if (!in_array($status, $statuses_validos)) {
        $status = 'rascunho';
    }
    
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    if ($colaborador_id <= 0) {
        throw new Exception('Colaborador é obrigatório');
    }
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    if ($request_id) {
        $lockName = 'pdi_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'PDI já está sendo processado.'
            ]);
            return;
        }
    }
    
    // Verifica duplicação: PDI idêntico nos últimos 30 segundos
    $stmt_check = $pdo->prepare("
        SELECT id FROM pdis 
        WHERE criado_por = ? 
        AND colaborador_id = ?
        AND titulo = ?
        AND data_inicio = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmt_check->execute([
        $usuario['id'],
        $colaborador_id,
        $titulo,
        $data_inicio
    ]);
    if ($stmt_check->fetch()) {
        if ($request_id) {
            $lockName = 'pdi_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'PDI já foi registrado recentemente.'
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        $stmt_check2 = $pdo->prepare("
            SELECT id FROM pdis 
            WHERE criado_por = ? 
            AND colaborador_id = ?
            AND titulo = ?
            AND data_inicio = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
            FOR UPDATE
        ");
        $stmt_check2->execute([
            $usuario['id'],
            $colaborador_id,
            $titulo,
            $data_inicio
        ]);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            if ($request_id) {
                $lockName = 'pdi_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            }
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'PDI já foi registrado recentemente.'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO pdis (
                colaborador_id, titulo, descricao, objetivo_geral,
                data_inicio, data_fim_prevista, status, criado_por,
                enviar_email, enviar_push
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $colaborador_id, $titulo, $descricao, $objetivo_geral,
            $data_inicio, $data_fim_prevista, $status, $usuario['id'],
            $enviar_email, $enviar_push
        ]);
        
        $pdi_id = $pdo->lastInsertId();
        
        // Adiciona objetivos se fornecidos
        $objetivos = $_POST['objetivos'] ?? [];
        if (is_string($objetivos)) {
            $objetivos = json_decode($objetivos, true);
        }
        
        if (is_array($objetivos) && !empty($objetivos)) {
            $stmt_obj = $pdo->prepare("
                INSERT INTO pdi_objetivos (pdi_id, objetivo, descricao, prazo, ordem)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($objetivos as $index => $obj) {
                if (!empty($obj['objetivo'])) {
                    $stmt_obj->execute([
                        $pdi_id,
                        $obj['objetivo'],
                        $obj['descricao'] ?? null,
                        $obj['prazo'] ?? null,
                        $obj['ordem'] ?? $index
                    ]);
                }
            }
        }
        
        // Adiciona ações se fornecidas
        $acoes = $_POST['acoes'] ?? [];
        if (is_string($acoes)) {
            $acoes = json_decode($acoes, true);
        }
        
        if (is_array($acoes) && !empty($acoes)) {
            $stmt_acao = $pdo->prepare("
                INSERT INTO pdi_acoes (pdi_id, objetivo_id, acao, descricao, prazo, ordem)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($acoes as $index => $acao) {
                if (!empty($acao['acao'])) {
                    $stmt_acao->execute([
                        $pdi_id,
                        $acao['objetivo_id'] ?? null,
                        $acao['acao'],
                        $acao['descricao'] ?? null,
                        $acao['prazo'] ?? null,
                        $acao['ordem'] ?? $index
                    ]);
                }
            }
        }
        
        // Atualiza progresso
        calcular_progresso_pdi($pdi_id);
        
        // Se for ativo, envia notificações
        if ($status === 'ativo') {
            // Envia notificações
            $enviar_email_final = engajamento_enviar_email('pdis', $enviar_email);
            $enviar_push_final = engajamento_enviar_push('pdis', $enviar_push);
            
            if ($enviar_email_final || $enviar_push_final) {
                require_once __DIR__ . '/../../includes/push_notifications.php';
                require_once __DIR__ . '/../../includes/email.php';
                
                $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
                $stmt->execute([$colaborador_id]);
                $colaborador = $stmt->fetch();
                
                $base_url = get_base_url();
                $link_pdi = $base_url . '/pages/pdi_view.php?id=' . $pdi_id;
                
                if ($enviar_email_final && !empty($colaborador['email_pessoal'])) {
                    $assunto = "Novo PDI Criado";
                    $mensagem = "
                        <h2>Olá, {$colaborador['nome_completo']}!</h2>
                        <p>Um novo Plano de Desenvolvimento Individual (PDI) foi criado para você.</p>
                        <h3>{$titulo}</h3>
                        " . ($descricao ? "<p>" . nl2br(htmlspecialchars($descricao)) . "</p>" : "") . "
                        <p><a href='{$link_pdi}' style='background-color: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Ver PDI</a></p>
                    ";
                    enviar_email($colaborador['email_pessoal'], $assunto, $mensagem);
                }
                
                if ($enviar_push_final) {
                    $titulo_push = "Novo PDI Criado";
                    $mensagem_push = $titulo;
                    enviar_push_colaborador($colaborador_id, $titulo_push, $mensagem_push, $link_pdi);
                }
            }
        }
        
        $pdo->commit();
        
        // Libera o lock do MySQL
        if ($request_id) {
            $lockName = 'pdi_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PDI criado com sucesso!',
            'pdi_id' => $pdi_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($request_id) {
            try {
                $lockName = 'pdi_' . $request_id;
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
            $lockName = 'pdi_' . $request_id;
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

