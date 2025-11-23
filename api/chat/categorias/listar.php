<?php
/**
 * API: Listar Categorias do Chat
 */

// Desabilita exibição de erros para não quebrar JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => []];

try {
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/permissions.php';
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT id, nome, descricao, cor, icone
        FROM chat_categorias
        WHERE ativo = TRUE
        ORDER BY ordem ASC, nome ASC
    ");
    $categorias = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => $categorias
    ];
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['data'] = [];
} catch (Error $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
    $response['data'] = [];
}

// Garante que sempre retorna JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

