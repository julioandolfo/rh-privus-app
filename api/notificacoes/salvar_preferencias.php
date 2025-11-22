<?php
/**
 * API para Salvar Preferências de Notificações Push
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    $preferencias = $_POST['preferencias'] ?? [];
    
    if (!$usuario_id && !$colaborador_id) {
        throw new Exception('Usuário não identificado.');
    }
    
    // Tipos de notificações válidos
    $tipos_validos = [
        'feedback_recebido',
        'documento_pagamento_enviado',
        'documento_pagamento_aprovado',
        'documento_pagamento_rejeitado'
    ];
    
    $pdo->beginTransaction();
    
    try {
        foreach ($tipos_validos as $tipo) {
            $ativo = isset($preferencias[$tipo]) && $preferencias[$tipo] == '1' ? 1 : 0;
            
            // Verifica se já existe preferência
            $where_conditions = [];
            $params = [$tipo];
            
            if ($usuario_id) {
                $where_conditions[] = "usuario_id = ?";
                $params[] = $usuario_id;
            } else {
                $where_conditions[] = "usuario_id IS NULL";
            }
            
            if ($colaborador_id) {
                $where_conditions[] = "colaborador_id = ?";
                $params[] = $colaborador_id;
            } else {
                $where_conditions[] = "colaborador_id IS NULL";
            }
            
            $where_sql = implode(" AND ", $where_conditions);
            
            $stmt = $pdo->prepare("
                SELECT id FROM push_notification_preferences 
                WHERE tipo_notificacao = ? 
                AND $where_sql
                LIMIT 1
            ");
            $stmt->execute($params);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE push_notification_preferences 
                    SET ativo = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$ativo, $existing['id']]);
            } else {
                // Insere nova
                $stmt = $pdo->prepare("
                    INSERT INTO push_notification_preferences 
                    (usuario_id, colaborador_id, tipo_notificacao, ativo)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $usuario_id ?: null,
                    $colaborador_id ?: null,
                    $tipo,
                    $ativo
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Preferências salvas com sucesso!'
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

