<?php
/**
 * API: Listar Mensagens de uma Conversa
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/chat_functions.php';

$response = ['success' => false, 'data' => []];

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $conversa_id = (int)($_GET['conversa_id'] ?? 0);
    $page = (int)($_GET['page'] ?? 1);
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    // Verifica permissão
    $usuario = $_SESSION['usuario'];
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT colaborador_id FROM chat_conversas WHERE id = ?");
    $stmt->execute([$conversa_id]);
    $conversa = $stmt->fetch();
    
    if (!$conversa) {
        throw new Exception('Conversa não encontrada');
    }
    
    // Verifica se usuário tem acesso
    if (is_colaborador() && $conversa['colaborador_id'] != $usuario['colaborador_id']) {
        throw new Exception('Você não tem permissão para ver esta conversa');
    }
    
    // Busca mensagens
    $mensagens = buscar_mensagens_conversa($conversa_id, $page, 50);
    
    // Formata resposta
    $data = [];
    foreach ($mensagens as $msg) {
        $data[] = [
            'id' => $msg['id'],
            'tipo' => $msg['tipo'],
            'mensagem' => $msg['mensagem'],
            'enviado_por_usuario_id' => $msg['enviado_por_usuario_id'],
            'enviado_por_colaborador_id' => $msg['enviado_por_colaborador_id'],
            'anexo' => $msg['anexo_caminho'] ? [
                'caminho' => $msg['anexo_caminho'],
                'nome' => $msg['anexo_nome_original'],
                'tamanho' => $msg['anexo_tamanho']
            ] : null,
            'voz' => $msg['voz_caminho'] ? [
                'caminho' => $msg['voz_caminho'],
                'duracao' => $msg['voz_duracao_segundos'],
                'transcricao' => $msg['voz_transcricao']
            ] : null,
            'autor' => [
                'tipo' => $msg['enviado_por_usuario_id'] ? 'rh' : 'colaborador',
                'nome' => $msg['usuario_nome'] ?? $msg['colaborador_nome'],
                'foto' => $msg['usuario_foto'] ?? $msg['colaborador_foto']
            ],
            'lida' => is_colaborador() ? $msg['lida_por_colaborador'] : $msg['lida_por_rh'],
            'created_at' => $msg['created_at']
        ];
    }
    
    // Marca mensagens como lidas
    if (is_colaborador()) {
        marcar_mensagens_lidas($conversa_id, null, $usuario['colaborador_id']);
    } else {
        marcar_mensagens_lidas($conversa_id, $usuario['id'], null);
    }
    
    $response = [
        'success' => true,
        'data' => $data
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

