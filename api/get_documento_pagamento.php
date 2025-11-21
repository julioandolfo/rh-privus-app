<?php
/**
 * API para obter informações do documento de pagamento
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_documento.php';

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$response = ['success' => false, 'message' => '', 'data' => null];

$fechamento_id = isset($_GET['fechamento_id']) ? (int)$_GET['fechamento_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if (empty($item_id) || empty($fechamento_id)) {
    $response['message'] = 'ID do item e fechamento são obrigatórios';
    echo json_encode($response);
    exit;
}

try {
    // Busca item do fechamento
    $stmt = $pdo->prepare("
        SELECT i.*, f.empresa_id, c.id as colaborador_id
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
    
    // Verifica permissão
    if ($usuario['role'] === 'COLABORADOR') {
        if ($item['colaborador_id'] != $usuario['colaborador_id']) {
            $response['message'] = 'Você não tem permissão para visualizar este documento';
            echo json_encode($response);
            exit;
        }
    } elseif ($usuario['role'] === 'RH') {
        if ($item['empresa_id'] != $usuario['empresa_id']) {
            $response['message'] = 'Você não tem permissão para visualizar este documento';
            echo json_encode($response);
            exit;
        }
    }
    
    if (empty($item['documento_anexo'])) {
        $response['message'] = 'Documento não encontrado';
        echo json_encode($response);
        exit;
    }
    
    // Verifica se arquivo existe
    $filepath = __DIR__ . '/../' . $item['documento_anexo'];
    if (!file_exists($filepath)) {
        $response['message'] = 'Arquivo não encontrado no servidor';
        echo json_encode($response);
        exit;
    }
    
    $response['success'] = true;
    $response['data'] = [
        'documento_anexo' => $item['documento_anexo'],
        'documento_nome' => basename($item['documento_anexo']),
        'documento_status' => $item['documento_status'],
        'documento_data_envio' => $item['documento_data_envio'],
        'documento_data_aprovacao' => $item['documento_data_aprovacao'],
        'documento_observacoes' => $item['documento_observacoes'],
        'is_image' => is_image_file($item['documento_anexo']),
        'file_size' => filesize($filepath),
        'file_size_formatted' => format_file_size(filesize($filepath))
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Erro ao processar: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response);

