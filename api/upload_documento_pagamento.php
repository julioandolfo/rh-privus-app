<?php
/**
 * API para upload de documento de pagamento
 * Com validação automática de NFS-e (data e valor)
 */

// DEBUG - Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_documento.php';
require_once __DIR__ . '/../includes/validar_nfse.php';

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

// Valida se é colaborador ou admin/rh
if ($usuario['role'] !== 'COLABORADOR' && !in_array($usuario['role'], ['ADMIN', 'RH'])) {
    $response['message'] = 'Você não tem permissão para fazer upload de documentos';
    echo json_encode($response);
    exit;
}

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$fechamento_id = isset($_POST['fechamento_id']) ? (int)$_POST['fechamento_id'] : 0;

if (empty($item_id) || empty($fechamento_id)) {
    $response['message'] = 'ID do item e fechamento são obrigatórios';
    echo json_encode($response);
    exit;
}

try {
    // Busca item do fechamento
    $stmt = $pdo->prepare("
        SELECT i.*, f.empresa_id, f.status as fechamento_status, c.id as colaborador_id
        FROM fechamentos_pagamento_itens i
        INNER JOIN fechamentos_pagamento f ON i.fechamento_id = f.id
        INNER JOIN colaboradores c ON i.colaborador_id = c.id
        WHERE i.id = ? AND i.fechamento_id = ?
    ");
    $stmt->execute([$item_id, $fechamento_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        $response['message'] = 'Item não encontrado';
        echo json_encode($response);
        exit;
    }
    
    // Verifica permissão: colaborador só pode enviar para seus próprios itens
    if ($usuario['role'] === 'COLABORADOR') {
        if ($item['colaborador_id'] != $usuario['colaborador_id']) {
            $response['message'] = 'Você não tem permissão para enviar documento para este item';
            echo json_encode($response);
            exit;
        }
    }
    
    // Verifica se fechamento está fechado (só permite upload se fechado)
    if ($item['fechamento_status'] !== 'fechado') {
        $response['message'] = 'Apenas fechamentos fechados permitem upload de documentos';
        echo json_encode($response);
        exit;
    }
    
    // Verifica se arquivo foi enviado
    if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Nenhum arquivo enviado ou erro no upload';
        echo json_encode($response);
        exit;
    }
    
    // Faz upload do documento
    $upload_result = upload_documento_pagamento($_FILES['documento'], $fechamento_id, $item_id);
    
    if (!$upload_result['success']) {
        $response['message'] = $upload_result['error'];
        echo json_encode($response);
        exit;
    }
    
    // Remove documento antigo se existir
    if (!empty($item['documento_anexo'])) {
        delete_documento_pagamento($item['documento_anexo']);
    }
    
    // Define status inicial
    $documento_status = 'enviado';
    $documento_observacoes = '';
    $auto_aprovado = false;
    $validacao = ['aprovado' => false, 'motivos' => [], 'dados_extraidos' => []];
    
    // Tenta validar a NFS-e automaticamente (apenas para PDFs)
    $extensao = strtolower(pathinfo($upload_result['path'], PATHINFO_EXTENSION));
    if ($extensao === 'pdf') {
        try {
            // Caminho completo do arquivo para validação
            $pdf_path = __DIR__ . '/../' . $upload_result['path'];
            
            // Valor esperado é o valor_total do item
            $valor_esperado = (float)($item['valor_total'] ?? 0);
            
            // Valida a NFS-e automaticamente
            $validacao = validar_nfse($pdf_path, $valor_esperado, 30, 0.02); // 30 dias, 2% tolerância
            
            if ($validacao['aprovado']) {
                // Aprovação automática - data e valor OK
                $documento_status = 'aprovado';
                $documento_observacoes = 'Aprovado automaticamente pelo sistema. ';
                $documento_observacoes .= 'Data NFS-e: ' . ($validacao['dados_extraidos']['data_emissao_formatada'] ?? '-') . '. ';
                $documento_observacoes .= 'Valor NFS-e: ' . ($validacao['dados_extraidos']['valor_liquido_formatado'] ?? '-') . '.';
                $auto_aprovado = true;
            } elseif (!empty($validacao['motivos']) && $validacao['dados_extraidos']['texto_extraido']) {
                // Rejeição automática - tem problemas e conseguiu ler o PDF
                $documento_status = 'rejeitado';
                $documento_observacoes = formatar_motivos_rejeicao($validacao['motivos']);
            }
            // Se não conseguiu extrair dados, fica como 'enviado' para análise manual
        } catch (Exception $e) {
            // Erro na validação - deixa como 'enviado' para análise manual
            error_log("Erro ao validar NFS-e: " . $e->getMessage());
        }
    }
    // Outros tipos de arquivo ficam como 'enviado' para análise manual
    
    // Atualiza item com novo documento e resultado da validação
    $stmt = $pdo->prepare("
        UPDATE fechamentos_pagamento_itens 
        SET documento_anexo = ?,
            documento_status = ?,
            documento_data_envio = NOW(),
            documento_observacoes = ?,
            documento_data_aprovacao = " . ($auto_aprovado ? "NOW()" : "NULL") . ",
            documento_aprovado_por = " . ($auto_aprovado ? "?" : "NULL") . "
        WHERE id = ?
    ");
    
    if ($auto_aprovado) {
        $stmt->execute([$upload_result['path'], $documento_status, $documento_observacoes, $usuario['id'], $item_id]);
    } else {
        $stmt->execute([$upload_result['path'], $documento_status, $documento_observacoes, $item_id]);
    }
    
    // Registra no histórico
    $acao_historico = 'enviado';
    $obs_historico = 'Documento enviado pelo colaborador';
    
    if ($auto_aprovado) {
        $acao_historico = 'aprovado_auto';
        $obs_historico = 'Aprovado automaticamente: ' . $documento_observacoes;
    } elseif ($documento_status === 'rejeitado') {
        $acao_historico = 'rejeitado_auto';
        $obs_historico = 'Rejeitado automaticamente: ' . $documento_observacoes;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO fechamentos_pagamento_documentos_historico 
        (item_id, acao, documento_anexo, usuario_id, observacoes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $item_id,
        $acao_historico,
        $upload_result['path'],
        $usuario['id'],
        $obs_historico
    ]);
    
    // Envia notificação para admin/rh (se OneSignal configurado)
    try {
        require_once __DIR__ . '/../includes/push_preferences.php';
        require_once __DIR__ . '/../includes/onesignal_service.php';
        
        // Busca usuários admin/rh da empresa
        $stmt_notif = $pdo->prepare("
            SELECT DISTINCT u.id
            FROM usuarios u
            WHERE u.empresa_id = ? AND u.role IN ('ADMIN', 'RH') AND u.status = 'ativo'
        ");
        $stmt_notif->execute([$item['empresa_id']]);
        $admins = $stmt_notif->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $admin_id) {
            try {
                // Verifica preferência antes de enviar
                if (verificar_preferencia_push($admin_id, null, 'documento_pagamento_enviado')) {
                    onesignal_send_notification([
                        'usuario_id' => $admin_id,
                        'titulo' => 'Novo Documento de Pagamento',
                        'mensagem' => 'Colaborador enviou documento para fechamento de pagamento',
                        'url' => get_base_url() . '/pages/fechamento_pagamentos.php?view=' . $fechamento_id
                    ]);
                }
            } catch (Exception $e) {
                // Ignora erros de notificação
            }
        }
    } catch (Exception $e) {
        // Ignora erros de notificação
    }
    
    $response['success'] = true;
    
    // Mensagem baseada no resultado da validação
    if ($auto_aprovado) {
        $response['message'] = '✅ Documento APROVADO automaticamente! Data e valor conferidos com sucesso.';
    } elseif ($documento_status === 'rejeitado') {
        $response['message'] = '❌ Documento REJEITADO automaticamente. Verifique os motivos abaixo e envie novamente.';
    } else {
        $response['message'] = '📄 Documento enviado! Aguardando análise manual.';
    }
    
    $response['data'] = [
        'documento_path' => $upload_result['path'],
        'documento_status' => $documento_status,
        'documento_data_envio' => date('Y-m-d H:i:s'),
        'validacao' => [
            'aprovado' => $validacao['aprovado'],
            'motivos' => $validacao['motivos'] ?? [],
            'dados_extraidos' => $validacao['dados_extraidos'] ?? [],
            'observacoes' => $documento_observacoes
        ]
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Erro ao processar: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response);

