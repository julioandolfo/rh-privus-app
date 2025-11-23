<?php
/**
 * API: Listar Conversas
 */

// Desabilita exibição de erros para não quebrar JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'total' => 0];

try {
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../includes/auth.php';
    require_once __DIR__ . '/../../../includes/permissions.php';
    require_once __DIR__ . '/../../../includes/chat_functions.php';
    // Verifica login
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Prepara filtros
    $filtros = [];
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $filtros['status'] = $_GET['status'];
    }
    // Se não especificar status, não filtra (mostra todas)
    
    if (isset($_GET['atribuido_para'])) {
        $filtros['atribuido_para'] = $_GET['atribuido_para'];
    }
    
    if (isset($_GET['prioridade'])) {
        $filtros['prioridade'] = $_GET['prioridade'];
    }
    
    if (isset($_GET['categoria_id'])) {
        $filtros['categoria_id'] = (int)$_GET['categoria_id'];
    }
    
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }
    
    $filtros['limit'] = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    // Busca conversas
    if (is_colaborador() && !empty($usuario['colaborador_id'])) {
        // Colaborador: só vê suas próprias conversas
        $conversas = buscar_conversas_colaborador(
            $usuario['colaborador_id'],
            $filtros['status'] ?? null,
            $filtros['limit']
        );
    } else {
        // RH/ADMIN: busca com filtros (passa informações do usuário para filtro automático)
        $conversas = buscar_conversas_rh($filtros, $usuario);
    }
    
    // Formata resposta
    $data = [];
    foreach ($conversas as $conv) {
        $data[] = [
            'id' => $conv['id'],
            'titulo' => $conv['titulo'],
            'status' => $conv['status'],
            'prioridade' => $conv['prioridade'],
            'categoria_id' => $conv['categoria_id'],
            'categoria_nome' => $conv['categoria_nome'] ?? null,
            'total_mensagens_nao_lidas' => is_colaborador() 
                ? $conv['total_mensagens_nao_lidas_colaborador'] 
                : $conv['total_mensagens_nao_lidas_rh'],
            'ultima_mensagem_at' => $conv['ultima_mensagem_at'],
            'colaborador' => [
                'id' => $conv['colaborador_id'],
                'nome' => $conv['colaborador_nome'] ?? null,
                'foto' => $conv['colaborador_foto'] ?? null
            ],
            'atribuido_para_nome' => $conv['atribuido_para_nome'] ?? null,
            'atribuido_para' => $conv['atribuido_para_usuario_id'] ? [
                'id' => $conv['atribuido_para_usuario_id'],
                'nome' => $conv['atribuido_para_nome'] ?? null
            ] : null
        ];
    }
    
    $response = [
        'success' => true,
        'data' => $data,
        'total' => count($data)
    ];
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['data'] = [];
    $response['total'] = 0;
} catch (Error $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
    $response['data'] = [];
    $response['total'] = 0;
}

// Garante que sempre retorna JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

