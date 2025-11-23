<?php
/**
 * API: Salvar Preferências do Chat
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Não autenticado');
    }
    
    $usuario = $_SESSION['usuario'];
    $pdo = getDB();
    
    $usuario_id = null;
    $colaborador_id = null;
    
    if (is_colaborador() && !empty($usuario['colaborador_id'])) {
        $colaborador_id = $usuario['colaborador_id'];
    } else {
        $usuario_id = $usuario['id'];
    }
    
    // Busca preferências existentes
    if ($usuario_id) {
        $stmt = $pdo->prepare("SELECT id FROM chat_preferencias_usuario WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM chat_preferencias_usuario WHERE colaborador_id = ?");
        $stmt->execute([$colaborador_id]);
    }
    $prefs_existentes = $stmt->fetch();
    
    // Campos atualizáveis
    $campos = [
        'notificacoes_push' => 'boolean',
        'notificacoes_email' => 'boolean',
        'notificacoes_sonoras' => 'boolean',
        'som_notificacao' => 'string',
        'status_online' => 'boolean',
        'status_mensagem' => 'string',
        'auto_resposta' => 'string',
        'auto_resposta_ativa' => 'boolean'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($campos as $campo => $tipo) {
        if (isset($_POST[$campo])) {
            $valor = $_POST[$campo];
            
            if ($tipo === 'boolean') {
                $valor = $valor === 'true' || $valor === true || $valor === '1' ? 1 : 0;
            }
            
            $updates[] = "{$campo} = ?";
            $params[] = $valor;
        }
    }
    
    if (empty($updates)) {
        throw new Exception('Nenhum campo para atualizar');
    }
    
    // Atualiza ou insere
    if ($prefs_existentes) {
        $params[] = $prefs_existentes['id'];
        $sql = "UPDATE chat_preferencias_usuario SET " . implode(', ', $updates) . " WHERE id = ?";
    } else {
        if ($usuario_id) {
            $updates[] = "usuario_id = ?";
            $params[] = $usuario_id;
        } else {
            $updates[] = "colaborador_id = ?";
            $params[] = $colaborador_id;
        }
        $sql = "INSERT INTO chat_preferencias_usuario SET " . implode(', ', $updates);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $response = [
        'success' => true,
        'message' => 'Preferências salvas com sucesso'
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

