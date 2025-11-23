<?php
/**
 * API: Buscar Novas Mensagens (Polling)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

$response = ['success' => false, 'novas_mensagens' => [], 'total_nao_lidas' => 0];

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    $conversa_id = (int)($_GET['conversa_id'] ?? 0);
    $ultima_mensagem_id = (int)($_GET['ultima_mensagem_id'] ?? 0);
    
    if (empty($conversa_id)) {
        throw new Exception('ID da conversa é obrigatório');
    }
    
    $pdo = getDB();
    
    // Busca novas mensagens
    $sql = "
        SELECT m.*,
               u.nome as usuario_nome,
               u.foto as usuario_foto,
               col.nome_completo as colaborador_nome,
               col.foto as colaborador_foto
        FROM chat_mensagens m
        LEFT JOIN usuarios u ON m.enviado_por_usuario_id = u.id
        LEFT JOIN colaboradores col ON m.enviado_por_colaborador_id = col.id
        WHERE m.conversa_id = ? 
        AND m.deletada = FALSE
    ";
    
    $params = [$conversa_id];
    
    if ($ultima_mensagem_id > 0) {
        $sql .= " AND m.id > ?";
        $params[] = $ultima_mensagem_id;
    }
    
    $sql .= " ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mensagens = $stmt->fetchAll();
    
    // Busca total de não lidas
    $campo_lida = is_colaborador() ? 'lida_por_colaborador' : 'lida_por_rh';
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM chat_mensagens
        WHERE conversa_id = ? 
        AND {$campo_lida} = FALSE
        AND deletada = FALSE
    ");
    $stmt->execute([$conversa_id]);
    $total_nao_lidas = $stmt->fetch()['total'];
    
    // Formata resposta
    $data = [];
    foreach ($mensagens as $msg) {
        $data[] = [
            'id' => $msg['id'],
            'tipo' => $msg['tipo'],
            'mensagem' => $msg['mensagem'],
            'enviado_por_usuario_id' => $msg['enviado_por_usuario_id'],
            'enviado_por_colaborador_id' => $msg['enviado_por_colaborador_id'],
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
            'created_at' => $msg['created_at']
        ];
    }
    
    $response = [
        'success' => true,
        'novas_mensagens' => $data,
        'total_nao_lidas' => $total_nao_lidas
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

