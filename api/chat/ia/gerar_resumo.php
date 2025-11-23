<?php
/**
 * API: Gerar Resumo com IA
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/chatgpt_service.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Apenas RH pode gerar resumo
    if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
        throw new Exception('Apenas RH pode gerar resumos');
    }
    
    $conversa_id = (int)($_POST['conversa_id'] ?? 0);
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Gera resumo
    $resultado = gerar_resumo_conversa_ia($conversa_id);
    
    if (!$resultado['success']) {
        throw new Exception($resultado['error'] ?? 'Erro ao gerar resumo');
    }
    
    $response = [
        'success' => true,
        'message' => 'Resumo gerado com sucesso',
        'resumo' => $resultado['resumo'],
        'tokens_usados' => $resultado['tokens_usados'] ?? null,
        'custo_estimado' => $resultado['custo_estimado'] ?? null
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

