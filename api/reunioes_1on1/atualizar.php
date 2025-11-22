<?php
/**
 * API para Atualizar Reunião 1:1 (marcar como realizada, avaliar, etc)
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
    
    $reuniao_id = (int)($_POST['reuniao_id'] ?? 0);
    
    if ($reuniao_id <= 0) {
        throw new Exception('ID da reunião inválido');
    }
    
    // Busca reunião
    $stmt = $pdo->prepare("SELECT * FROM reunioes_1on1 WHERE id = ?");
    $stmt->execute([$reuniao_id]);
    $reuniao = $stmt->fetch();
    
    if (!$reuniao) {
        throw new Exception('Reunião não encontrada');
    }
    
    // Verifica permissão (só líder, liderado ou criador podem atualizar)
    $pode_atualizar = false;
    if ($usuario['colaborador_id'] == $reuniao['lider_id'] || 
        $usuario['colaborador_id'] == $reuniao['liderado_id'] ||
        $usuario['id'] == $reuniao['created_by'] ||
        $usuario['role'] === 'ADMIN' || $usuario['role'] === 'RH') {
        $pode_atualizar = true;
    }
    
    if (!$pode_atualizar) {
        throw new Exception('Sem permissão para atualizar esta reunião');
    }
    
    $pdo->beginTransaction();
    
    try {
        $updates = [];
        $params = [];
        
        // Status
        if (isset($_POST['status'])) {
            $updates[] = "status = ?";
            $params[] = $_POST['status'];
        }
        
        // Assuntos tratados
        if (isset($_POST['assuntos_tratados'])) {
            $updates[] = "assuntos_tratados = ?";
            $params[] = $_POST['assuntos_tratados'];
        }
        
        // Próximos passos
        if (isset($_POST['proximos_passos'])) {
            $updates[] = "proximos_passos = ?";
            $params[] = $_POST['proximos_passos'];
        }
        
        // Avaliação do liderado
        if (isset($_POST['avaliacao_liderado'])) {
            $updates[] = "avaliacao_liderado = ?";
            $params[] = (int)$_POST['avaliacao_liderado'];
        }
        
        // Avaliação do líder
        if (isset($_POST['avaliacao_lider'])) {
            $updates[] = "avaliacao_lider = ?";
            $params[] = (int)$_POST['avaliacao_lider'];
        }
        
        // Observações
        if (isset($_POST['observacoes'])) {
            $updates[] = "observacoes = ?";
            $params[] = $_POST['observacoes'];
        }
        
        if (empty($updates)) {
            throw new Exception('Nenhum campo para atualizar');
        }
        
        $params[] = $reuniao_id;
        
        $sql = "UPDATE reunioes_1on1 SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Se marcou como realizada, envia notificação
        if (isset($_POST['status']) && $_POST['status'] === 'realizada') {
            $enviar_email = engajamento_enviar_email('reunioes_1on1');
            $enviar_push = engajamento_enviar_push('reunioes_1on1');
            
            if ($enviar_email || $enviar_push) {
                require_once __DIR__ . '/../../includes/push_notifications.php';
                require_once __DIR__ . '/../../includes/email.php';
                
                // Busca dados
                $stmt = $pdo->prepare("
                    SELECT cl.*, cd.* 
                    FROM reunioes_1on1 r
                    INNER JOIN colaboradores cl ON r.lider_id = cl.id
                    INNER JOIN colaboradores cd ON r.liderado_id = cd.id
                    WHERE r.id = ?
                ");
                $stmt->execute([$reuniao_id]);
                $dados = $stmt->fetch();
                
                $base_url = get_base_url();
                $link_reuniao = $base_url . '/pages/reuniao_1on1_view.php?id=' . $reuniao_id;
                
                // Notifica ambos
                if ($enviar_email) {
                    $assunto = "Reunião 1:1 Realizada";
                    $mensagem = "
                        <h2>Reunião 1:1 Realizada</h2>
                        <p>A reunião entre <strong>{$dados['lider_nome']}</strong> e <strong>{$dados['liderado_nome']}</strong> foi marcada como realizada.</p>
                        <p><a href='{$link_reuniao}'>Ver Detalhes</a></p>
                    ";
                    
                    if (!empty($dados['email_pessoal'])) {
                        enviar_email($dados['email_pessoal'], $assunto, $mensagem);
                    }
                }
                
                if ($enviar_push) {
                    $titulo = "Reunião 1:1 Realizada";
                    $mensagem_push = "Reunião realizada em " . date('d/m/Y', strtotime($reuniao['data_reuniao']));
                    enviar_push_colaborador($reuniao['liderado_id'], $titulo, $mensagem_push, $link_reuniao);
                    enviar_push_colaborador($reuniao['lider_id'], $titulo, $mensagem_push, $link_reuniao);
                }
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Reunião atualizada com sucesso!'
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

