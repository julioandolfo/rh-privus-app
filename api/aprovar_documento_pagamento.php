<?php
/**
 * API para aprovar/rejeitar documento de pagamento
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$response = ['success' => false, 'message' => '', 'data' => null];

// Apenas ADMIN e RH podem aprovar/rejeitar
if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
    $response['message'] = 'Você não tem permissão para esta ação';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$acao = isset($_POST['acao']) ? $_POST['acao'] : ''; // 'aprovar' ou 'rejeitar'
$observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

if (empty($item_id) || empty($acao)) {
    $response['message'] = 'ID do item e ação são obrigatórios';
    echo json_encode($response);
    exit;
}

if (!in_array($acao, ['aprovar', 'rejeitar'])) {
    $response['message'] = 'Ação inválida';
    echo json_encode($response);
    exit;
}

// Se rejeitar, observações são obrigatórias
if ($acao === 'rejeitar' && empty($observacoes)) {
    $response['message'] = 'Observações são obrigatórias ao rejeitar';
    echo json_encode($response);
    exit;
}

try {
    // Busca item do fechamento
    $stmt = $pdo->prepare("
        SELECT i.*, f.empresa_id, f.status as fechamento_status, c.id as colaborador_id, c.nome_completo as colaborador_nome
        FROM fechamentos_pagamento_itens i
        INNER JOIN fechamentos_pagamento f ON i.fechamento_id = f.id
        INNER JOIN colaboradores c ON i.colaborador_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        $response['message'] = 'Item não encontrado';
        echo json_encode($response);
        exit;
    }
    
    // Verifica permissão (RH só pode aprovar da sua empresa)
    if ($usuario['role'] === 'RH' && $item['empresa_id'] != $usuario['empresa_id']) {
        $response['message'] = 'Você não tem permissão para aprovar documentos desta empresa';
        echo json_encode($response);
        exit;
    }
    
    // Verifica se documento foi enviado
    if (empty($item['documento_anexo']) || $item['documento_status'] !== 'enviado') {
        $response['message'] = 'Documento não foi enviado ou já foi processado';
        echo json_encode($response);
        exit;
    }
    
    // Atualiza status do documento
    $novo_status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
    
    $stmt = $pdo->prepare("
        UPDATE fechamentos_pagamento_itens 
        SET documento_status = ?,
            documento_data_aprovacao = NOW(),
            documento_aprovado_por = ?,
            documento_observacoes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $novo_status,
        $usuario['id'],
        $observacoes ?: null,
        $item_id
    ]);
    
    // Registra no histórico
    $stmt = $pdo->prepare("
        INSERT INTO fechamentos_pagamento_documentos_historico 
        (item_id, acao, documento_anexo, usuario_id, observacoes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $item_id,
        $novo_status,
        $item['documento_anexo'],
        $usuario['id'],
        $observacoes ?: ($acao === 'aprovar' ? 'Documento aprovado' : 'Documento rejeitado')
    ]);
    
    // Envia notificação para o colaborador
    try {
        require_once __DIR__ . '/../includes/push_preferences.php';
        require_once __DIR__ . '/../includes/onesignal_service.php';
        
        // Busca usuario_id do colaborador se existir
        $usuario_id_colab = null;
        $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ? LIMIT 1");
        $stmt_user->execute([$item['colaborador_id']]);
        $user_colab = $stmt_user->fetch();
        if ($user_colab) {
            $usuario_id_colab = $user_colab['id'];
        }
        
        $tipo_notificacao = $acao === 'aprovar' 
            ? 'documento_pagamento_aprovado' 
            : 'documento_pagamento_rejeitado';
        
        // Verifica preferência antes de enviar
        if (verificar_preferencia_push($usuario_id_colab, $item['colaborador_id'], $tipo_notificacao)) {
            $mensagem = $acao === 'aprovar' 
                ? 'Seu documento de pagamento foi aprovado!'
                : 'Seu documento de pagamento foi rejeitado. Motivo: ' . $observacoes;
            
            onesignal_send_notification([
                'colaborador_id' => $item['colaborador_id'],
                'titulo' => $acao === 'aprovar' ? 'Documento Aprovado' : 'Documento Rejeitado',
                'mensagem' => $mensagem,
                'url' => get_base_url() . '/pages/meus_pagamentos.php'
            ]);
        }
    } catch (Exception $e) {
        // Ignora erros de notificação
    }
    
    $response['success'] = true;
    $response['message'] = $acao === 'aprovar' ? 'Documento aprovado com sucesso!' : 'Documento rejeitado.';
    $response['data'] = [
        'documento_status' => $novo_status,
        'documento_data_aprovacao' => date('Y-m-d H:i:s'),
        'documento_aprovado_por' => $usuario['nome']
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Erro ao processar: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response);

